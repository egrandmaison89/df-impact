#!/usr/bin/env python3
"""
Refresh migration source data from the live WordPress model site.

Steps:
  1. Crawl the WordPress core sitemap (post type *post* only) and record slugs/URLs.
  2. Paginate the WordPress REST API for categories, tags, pages, posts, and media.
  3. Run process_wp_data.py to regenerate articles.json, in_brief.json, redirects.json, etc.
  4. Copy generated JSON into web/modules/custom/df_migrate/data/ for Drush/migrate.

Usage (from repo drupal/migration-data):
  python3 fetch_wordpress_model.py              # full sync + process + copy
  python3 fetch_wordpress_model.py --sitemap-only
  python3 fetch_wordpress_model.py --skip-process   # fetch raw JSON only; run process_wp_data.py yourself

Environment:
  WP_BASE_URL  override base URL (default https://danafarberimpact.org)
  WP_SITEMAP_INDEX_URL  full URL to sitemap index (optional; use if default discovery fails)
  WP_HTTP_COOKIE  optional raw Cookie header (DevTools) for urllib / curl -H
  WP_COOKIES_FILE  Netscape cookies.txt path (recommended for SiteGround sg-captcha); used with curl -b
  WP_HTTP_VIA_JINA  If set to 1/true: fetch WordPress via https://r.jina.ai/https://... so local curl/Python bypass
    SiteGround sg-captcha without browser cookies (third-party reader; ok for public REST/sitemap).

This site uses SiteGround bot protection (sg-captcha). Without browser cookies, HTTP clients get 403 or a
202 challenge page instead of XML/JSON. Options: (1) export cookies (e.g. extension "Get cookies.txt LOCALLY")
and set WP_COOKIES_FILE, or (2) set WP_HTTP_VIA_JINA=1 to tunnel through r.jina.ai (sitemap may fall back to
REST for slug list if XML is not readable).
"""

from __future__ import annotations

import argparse
import http.cookiejar
import json
import os
import re
import shutil
import ssl
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import urlparse

MIGRATION_DATA = Path(__file__).resolve().parent
DRUPAL_ROOT = MIGRATION_DATA.parent
DF_MIGRATE_DATA = DRUPAL_ROOT / "web/modules/custom/df_migrate/data"

DEFAULT_BASE = "https://danafarberimpact.org"
# Use a stock browser UA only; custom suffixes are often blocked by WAF / bot rules.
USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)

# Child sitemap URLs that list blog *posts* (core WP, Yoast, RankMath, etc.).
# Note: /sitemap-N.xml is a common Yoast/AIOSEO chunk name for posts+pages; we skip
# image-sitemap-*.xml via explicit exclude (those are image URLs, not the post index).
POST_CHILD_SITEMAP_RES = [
    re.compile(r"/wp-sitemap-posts-post-\d+\.xml(\?|$)", re.I),
    re.compile(r"/post-sitemap\d*\.xml(\?|$)", re.I),
    re.compile(r"/post-sitemap-\d+\.xml(\?|$)", re.I),
    re.compile(r"/posts-sitemap\.xml(\?|$)", re.I),
    re.compile(r"/sitemap-pt-post-[^/]+\.xml(\?|$)", re.I),
    # e.g. https://example.org/sitemap-1.xml (not image-sitemap-1.xml; '/' before 'sitemap').
    re.compile(r"/sitemap-\d+\.xml(\?|$)", re.I),
]

# Exclude these child sitemaps when using broad fallback.
CHILD_SITEMAP_EXCLUDE_RE = re.compile(
    r"category-sitemap|author-sitemap|tag-sitemap|post_tag|taxonomy-sitemap|"
    r"attachment|wp-sitemap-users|page-sitemap|news-sitemap|sitemap-pt-page|"
    r"forum|product-sitemap|local-sitemap|image-sitemap|video-sitemap",
    re.I,
)

SITEMAP_INDEX_PATHS = (
    "/sitemap_index.xml",
    "/wp-sitemap.xml",
    "/sitemap.xml",
)


class SitemapDiscoveryError(RuntimeError):
    """Could not read any sitemap index / child URLs (WAF, captcha, or layout)."""


def get_base() -> str:
    return os.environ.get("WP_BASE_URL", DEFAULT_BASE).rstrip("/")


def get_sitemap_index_candidates(base: str) -> list[str]:
    """Ordered list of sitemap index URLs to try (Yoast often uses sitemap_index.xml)."""
    out: list[str] = []
    override = os.environ.get("WP_SITEMAP_INDEX_URL", "").strip()
    if override:
        out.append(override.rstrip("/"))
    for path in SITEMAP_INDEX_PATHS:
        u = f"{base}{path}"
        if u not in out:
            out.append(u)
    return out


_SSL_CONTEXT = ssl.create_default_context()
_COOKIE_JAR = http.cookiejar.CookieJar()
_URL_OPENER = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(_COOKIE_JAR),
    urllib.request.HTTPSHandler(context=_SSL_CONTEXT),
)


def _cookie_header_from_env() -> dict[str, str]:
    raw = os.environ.get("WP_HTTP_COOKIE", "").strip()
    if raw:
        return {"Cookie": raw}
    return {}


def _cookies_file_path() -> str:
    path = os.environ.get("WP_COOKIES_FILE", "").strip()
    if path and os.path.isfile(path):
        return path
    if path:
        print(f"Warning: WP_COOKIES_FILE not found: {path}", file=sys.stderr)
    return ""


def _http_via_jina_enabled() -> bool:
    return os.environ.get("WP_HTTP_VIA_JINA", "").strip().lower() in ("1", "true", "yes", "y", "on")


def _jina_proxy_url(target_url: str) -> str:
    if target_url.startswith("https://"):
        return "https://r.jina.ai/" + target_url
    if target_url.startswith("http://"):
        return "https://r.jina.ai/" + target_url
    raise ValueError(f"Jina proxy needs absolute URL, got: {target_url[:80]}")


def _decode_jina_reader_body(raw: bytes) -> bytes:
    """r.jina.ai wraps the page in a short markdown document; strip it for JSON/XML/plain."""
    if not raw or not raw.strip():
        raise RuntimeError("Jina reader returned an empty body (rate limit or error). Retry later.")
    text = raw.decode("utf-8", errors="replace")
    t = text.strip()
    if t.startswith(("[", "{")):
        return t.encode("utf-8")
    marker = "Markdown Content:"
    i = text.find(marker)
    if i < 0:
        raise RuntimeError(f"Jina reader response missing expected wrapper: {t[:500]!r}")
    text = text[i + len(marker) :].lstrip()
    if text.startswith("\n"):
        text = text[1:]
    if text.startswith("```"):
        lines = text.split("\n")
        if len(lines) >= 2 and lines[-1].strip() == "```":
            text = "\n".join(lines[1:-1])
    out = text.strip()
    if not out:
        raise RuntimeError("Jina reader markdown section was empty.")
    return out.encode("utf-8")


def _response_is_blocked(status: int, headers: dict[str, str], body: bytes) -> bool:
    """SiteGround returns 202 + sg-captcha; WAF often uses 403 HTML pages."""
    if status == 403 or status == 401:
        return True
    h = {k.lower(): v for k, v in headers.items()}
    if status == 202 and "challenge" in (h.get("sg-captcha") or "").lower():
        return True
    probe = body[:1600].lower()
    if b"sgcaptcha" in probe or b"sg-captcha" in probe or b"well-known/sgcaptcha" in probe:
        return True
    return False


def _raise_fetch_blocked(url: str, status: int, body: bytes) -> None:
    preview = body[:280].decode("utf-8", errors="replace").replace("\n", " ")
    msg = (
        f"Request blocked or challenged ({status}) for {url}.\n\n"
        "This host uses SiteGround sg-captcha / bot protection. Browser-only cookies defeat it.\n\n"
        "Do this once:\n"
        "  1. Open the site in Chrome and complete any challenge.\n"
        "  2. Export cookies (e.g. browser extension \"Get cookies.txt LOCALLY\").\n"
        "  3. export WP_COOKIES_FILE=/path/to/cookies.txt\n"
        "     python3 fetch_wordpress_model.py\n\n"
        "Or try:  WP_HTTP_VIA_JINA=1 python3 fetch_wordpress_model.py\n"
        "(tunnels via r.jina.ai; public content only.)\n\n"
        f"Response preview: {preview}\n"
    )
    raise RuntimeError(msg)


def _urllib_fetch(url: str, timeout: int, accept: str) -> tuple[int, dict[str, str], bytes]:
    parsed = urlparse(url)
    referer = f"{parsed.scheme}://{parsed.netloc}/"
    headers = {
        "User-Agent": USER_AGENT,
        "Accept": accept,
        "Accept-Language": "en-US,en;q=0.9",
        "Referer": referer,
        **_cookie_header_from_env(),
    }
    req = urllib.request.Request(url, headers=headers)
    try:
        with _URL_OPENER.open(req, timeout=timeout) as r:
            body = r.read()
            hdrs = {k.lower(): v for k, v in r.headers.items()}
            return r.status, hdrs, body
    except urllib.error.HTTPError as e:
        try:
            body = e.read()
        except Exception:
            body = b""
        hdrs = {k.lower(): v for k, v in e.headers.items()} if e.headers else {}
        return e.code, hdrs, body


def _curl_fetch(url: str, timeout: int, accept: str, cookie_file: str | None) -> tuple[int, dict[str, str], bytes]:
    if not shutil.which("curl"):
        raise RuntimeError("internal: _curl_fetch called without curl in PATH")
    parsed = urlparse(url)
    referer = f"{parsed.scheme}://{parsed.netloc}/"
    fd, tmp_path = tempfile.mkstemp(prefix="wp-fetch-", suffix=".bin")
    os.close(fd)
    try:
        cmd = [
            "curl",
            "-sS",
            "-L",
            "--compressed",
            "--max-time",
            str(timeout),
            "-o",
            tmp_path,
            "-w",
            "%{http_code}",
            "-A",
            USER_AGENT,
            "-H",
            f"Accept: {accept}",
            "-H",
            "Accept-Language: en-US,en;q=0.9",
            "-e",
            referer,
        ]
        ck = _cookie_header_from_env().get("Cookie")
        if ck:
            cmd.extend(["-H", f"Cookie: {ck}"])
        if cookie_file:
            cmd.extend(["-b", cookie_file])
        cmd.append(url)
        try:
            out = subprocess.check_output(cmd, stderr=subprocess.PIPE, timeout=timeout + 30)
        except subprocess.CalledProcessError as e:
            err = (e.stderr or b"").decode("utf-8", errors="replace")
            raise RuntimeError(f"curl failed (exit {e.returncode}): {err}") from e
        except subprocess.TimeoutExpired as e:
            raise RuntimeError("curl timed out") from e
        status = int(out.decode("ascii", errors="replace").strip() or "0")
        body = Path(tmp_path).read_bytes()
        return status, {}, body
    finally:
        Path(tmp_path).unlink(missing_ok=True)


def fetch(url: str, timeout: int = 120, *, accept: str | None = None) -> bytes:
    """HTTP GET: optional Jina tunnel, else urllib + curl + cookies."""
    if accept is None:
        accept = "application/xml,application/xhtml+xml,text/xml;q=0.9,*/*;q=0.8"

    if _http_via_jina_enabled():
        proxied = _jina_proxy_url(url)
        last_err: Exception | None = None
        t_out = max(timeout, 240)
        for attempt in range(4):
            try:
                status, _hdrs, body = _urllib_fetch(proxied, min(t_out, 300), accept)
                if status != 200:
                    raise RuntimeError(f"Jina reader HTTP {status} while fetching {url}")
                decoded = _decode_jina_reader_body(body)
                if not decoded.strip():
                    raise RuntimeError("Jina decode produced empty bytes")
                return decoded
            except Exception as e:
                last_err = e
                time.sleep(1.5 * (attempt + 1))
        raise RuntimeError(f"Jina fetch failed after retries: {url}") from last_err

    cookie_file = _cookies_file_path()
    if os.environ.get("WP_COOKIES_FILE", "").strip() and not cookie_file:
        raise RuntimeError(
            "WP_COOKIES_FILE is set but that path does not exist. Fix the path or unset it."
        )
    if cookie_file and not shutil.which("curl"):
        raise RuntimeError(
            "WP_COOKIES_FILE is set but curl is not installed. Install curl or unset WP_COOKIES_FILE."
        )

    if cookie_file:
        status, hdrs, body = _curl_fetch(url, timeout, accept, cookie_file)
        if _response_is_blocked(status, hdrs, body):
            _raise_fetch_blocked(url, status, body)
        return body

    status, hdrs, body = _urllib_fetch(url, timeout, accept)
    if not _response_is_blocked(status, hdrs, body):
        return body

    if shutil.which("curl"):
        status2, hdrs2, body2 = _curl_fetch(url, timeout, accept, None)
        if not _response_is_blocked(status2, hdrs2, body2):
            return body2
        _raise_fetch_blocked(url, status2, body2)

    _raise_fetch_blocked(url, status, body)
    return body  # unreachable


def iter_loc_elements(xml_bytes: bytes):
    root = ET.fromstring(xml_bytes)
    for elem in root.iter():
        if elem.tag.endswith("loc") and elem.text:
            yield elem.text.strip()


def _normalize_sitemap_loc(loc: str) -> str:
    loc = (loc or "").strip()
    if not loc:
        return ""
    return loc.split("?")[0].split("#")[0]


def _is_child_sitemap_url(loc: str) -> bool:
    return _normalize_sitemap_loc(loc).lower().endswith(".xml")


def discover_post_child_sitemaps(base: str) -> tuple[str, list[str]]:
    """Return (index_url_used, child_sitemap_urls_for_posts)."""
    base_host = urlparse(base).netloc
    last_error: Exception | None = None

    for index_url in get_sitemap_index_candidates(base):
        try:
            index_xml = fetch(index_url)
        except SitemapDiscoveryError:
            raise
        except RuntimeError:
            raise
        except Exception as e:
            last_error = e
            continue

        try:
            root = ET.fromstring(index_xml)
        except ET.ParseError as pe:
            last_error = pe
            continue
        # One file listing all URLs (no index).
        if root.tag.endswith("urlset"):
            print(f"Sitemap: single urlset at {index_url}", file=sys.stderr)
            return index_url, [_normalize_sitemap_loc(index_url)]

        locs = [_normalize_sitemap_loc(x) for x in iter_loc_elements(index_xml)]
        child_xml = [x for x in locs if x and _is_child_sitemap_url(x)]

        selected: list[str] = []
        for loc in child_xml:
            if any(r.search(loc) for r in POST_CHILD_SITEMAP_RES):
                selected.append(loc)

        if not selected:
            for loc in child_xml:
                if CHILD_SITEMAP_EXCLUDE_RE.search(loc):
                    continue
                if urlparse(loc).netloc and urlparse(loc).netloc.lower() != base_host.lower():
                    continue
                selected.append(loc)

        if not selected:
            last_error = RuntimeError(f"No usable post sitemaps linked from {index_url}")
            continue

        print(f"Sitemap index: {index_url}", file=sys.stderr)
        print(
            f"Post sitemaps ({len(selected)}): {selected[:5]}{'…' if len(selected) > 5 else ''}",
            file=sys.stderr,
        )
        return index_url, selected

    msg = (
        "Could not discover a WordPress sitemap. Tried WP_SITEMAP_INDEX_URL (if set), "
        f"then {', '.join(SITEMAP_INDEX_PATHS)} under {base}. "
        "See WP_COOKIES_FILE or WP_HTTP_VIA_JINA in the script docstring if HTTP requests return a captcha page."
    )
    if last_error:
        msg += f" Last error: {last_error}"
    raise SitemapDiscoveryError(msg)


def collect_post_urls_from_sitemap(base: str) -> tuple[list[str], set[str]]:
    """Return (urls, slugs) from post sitemap child documents."""
    _index_url, post_sitemap_urls = discover_post_child_sitemaps(base)

    all_urls: list[str] = []
    for sm_url in post_sitemap_urls:
        sm_xml = fetch(sm_url)
        for loc in iter_loc_elements(sm_xml):
            loc_n = _normalize_sitemap_loc(loc)
            if not loc_n:
                continue
            if _is_child_sitemap_url(loc_n):
                continue
            parsed = urlparse(loc_n)
            if parsed.netloc and parsed.netloc.lower() != urlparse(base).netloc.lower():
                continue
            all_urls.append(loc_n)

    slugs: set[str] = set()
    for url in all_urls:
        path = urlparse(url).path.strip("/")
        if not path:
            continue
        slug = path.split("/")[-1].lower()
        if slug:
            slugs.add(slug)

    return all_urls, slugs


def url_slug(url: str) -> str:
    path = urlparse(url).path.strip("/")
    return path.split("/")[-1].lower() if path else ""


def wp_get_json_page(base: str, path: str, page: int, per_page: int = 100, extra: dict | None = None) -> list | dict:
    qs = {"per_page": per_page, "page": page}
    if extra:
        qs.update(extra)
    url = f"{base}{path}?{urllib.parse.urlencode(qs)}"
    raw = fetch(url, timeout=180, accept="application/json, */*;q=0.1")
    text = raw.decode("utf-8", errors="replace").strip()
    try:
        return json.loads(text)
    except json.JSONDecodeError as err:
        if _http_via_jina_enabled():
            for pattern in (r"\[[\s\S]*\]", r"\{[\s\S]*\}"):
                m = re.search(pattern, text)
                if m:
                    try:
                        return json.loads(m.group(0))
                    except json.JSONDecodeError:
                        continue
        preview = text[:500].replace("\n", " ")
        raise RuntimeError(
            f"Invalid JSON from {path} page={page}: {err}; preview={preview!r}"
        ) from err


def wp_collect_paginated(base: str, api_path: str, extra: dict | None = None) -> list:
    out: list = []
    page = 1
    while True:
        chunk = wp_get_json_page(base, api_path, page, per_page=100, extra=extra)
        if not isinstance(chunk, list):
            raise RuntimeError(f"Expected list from {api_path} page {page}, got {type(chunk)}")
        if not chunk:
            break
        out.extend(chunk)
        if len(chunk) < 100:
            break
        page += 1
        time.sleep(0.55 if _http_via_jina_enabled() else 0.25)
    return out


def post_urls_and_slugs_from_rest_payload(posts: list) -> tuple[list[str], set[str]]:
    """Build permalink + slug set from wp/v2/posts objects (same as sitemap truth for public posts)."""
    urls: list[str] = []
    slugs: set[str] = set()
    for p in posts:
        if not isinstance(p, dict):
            continue
        slug = (p.get("slug") or "").strip().lower()
        link = (p.get("link") or "").strip()
        if slug:
            slugs.add(slug)
        if link:
            urls.append(link)
    return urls, slugs


def clear_old_raw_files() -> None:
    for pattern in ("posts_page_*_raw.json", "media_page_*_raw.json"):
        for p in MIGRATION_DATA.glob(pattern):
            p.unlink()


def save_posts_chunked(posts: list) -> None:
    per = 100
    for i in range(0, len(posts), per):
        chunk = posts[i : i + per]
        n = i // per + 1
        path = MIGRATION_DATA / f"posts_page_{n}_raw.json"
        with open(path, "w", encoding="utf-8") as f:
            json.dump(chunk, f, indent=2)


def save_media_chunked(media_items: list) -> None:
    per = 100
    for i in range(0, len(media_items), per):
        chunk = media_items[i : i + per]
        n = i // per + 1
        path = MIGRATION_DATA / f"media_page_{n}_raw.json"
        with open(path, "w", encoding="utf-8") as f:
            json.dump(chunk, f, indent=2)


def copy_outputs_to_df_migrate() -> None:
    DF_MIGRATE_DATA.mkdir(parents=True, exist_ok=True)
    for name in (
        "articles.json",
        "in_brief.json",
        "issues.json",
        "topics.json",
        "cancer_types.json",
        "media.json",
        "redirects.json",
    ):
        src = MIGRATION_DATA / name
        if src.is_file():
            shutil.copy2(src, DF_MIGRATE_DATA / name)


def run_process_wp_data() -> None:
    script = MIGRATION_DATA / "process_wp_data.py"
    subprocess.run(
        [sys.executable, str(script)],
        cwd=str(MIGRATION_DATA),
        check=True,
    )


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--sitemap-only",
        action="store_true",
        help="Only fetch wp-sitemap and write wp_sitemap_state.json (no REST/process).",
    )
    parser.add_argument(
        "--skip-process",
        action="store_true",
        help="Fetch REST raw files and sitemap state but do not run process_wp_data.py.",
    )
    args = parser.parse_args()
    base = get_base()

    print(f"WordPress base: {base}", flush=True)

    if _http_via_jina_enabled():
        print("HTTP: using WP_HTTP_VIA_JINA (r.jina.ai) — slower but bypasses captcha.", file=sys.stderr, flush=True)
    posts_cache: list | None = None
    print("Fetching post sitemap(s) / slug list...", file=sys.stderr)
    try:
        urls, slugs = collect_post_urls_from_sitemap(base)
    except SitemapDiscoveryError:
        print(
            "Sitemap discovery failed; using published posts from the REST API for wp_sitemap_state.json.",
            file=sys.stderr,
        )
        posts_cache = wp_collect_paginated(base, "/wp-json/wp/v2/posts", {"status": "publish"})
        urls, slugs = post_urls_and_slugs_from_rest_payload(posts_cache)
    state = {
        "base": base,
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "post_urls": sorted(urls),
        "post_slugs": sorted(slugs),
        "post_url_count": len(urls),
        "post_slug_count": len(slugs),
    }
    sm_path = DF_MIGRATE_DATA / "wp_sitemap_state.json"
    DF_MIGRATE_DATA.mkdir(parents=True, exist_ok=True)
    with open(sm_path, "w", encoding="utf-8") as f:
        json.dump(state, f, indent=2)
    print(f"Wrote {sm_path} ({len(slugs)} unique slugs, {len(urls)} URLs)")

    if args.sitemap_only:
        print("Sitemap-only mode; done.")
        return

    print("Removing old posts_page_*/media_page_* raw JSON...")
    clear_old_raw_files()

    print("REST: categories...")
    categories = wp_collect_paginated(base, "/wp-json/wp/v2/categories")
    with open(MIGRATION_DATA / "categories.json", "w", encoding="utf-8") as f:
        json.dump(categories, f, indent=2)

    print("REST: tags...")
    tags = wp_collect_paginated(base, "/wp-json/wp/v2/tags")
    with open(MIGRATION_DATA / "tags.json", "w", encoding="utf-8") as f:
        json.dump(tags, f, indent=2)

    print("REST: pages...")
    pages = wp_collect_paginated(base, "/wp-json/wp/v2/pages", {"status": "publish"})
    with open(MIGRATION_DATA / "pages_raw.json", "w", encoding="utf-8") as f:
        json.dump(pages, f, indent=2)

    print("REST: posts (publish)...")
    if posts_cache is not None:
        posts = posts_cache
        print(f"  {len(posts)} posts (reused from slug-list fetch)")
        save_posts_chunked(posts)
    else:
        posts = wp_collect_paginated(base, "/wp-json/wp/v2/posts", {"status": "publish"})
        print(f"  {len(posts)} posts")
        save_posts_chunked(posts)

    print("REST: media...")
    media_items = wp_collect_paginated(base, "/wp-json/wp/v2/media", {"per_page": 100})
    print(f"  {len(media_items)} media items")
    save_media_chunked(media_items)

    if args.skip_process:
        print("--skip-process: not running process_wp_data.py. Copy files manually when ready.")
        return

    print("Running process_wp_data.py...")
    run_process_wp_data()

    print("Copying JSON to df_migrate/data...")
    copy_outputs_to_df_migrate()

    print("\nDone. Next steps (from ddev / Drupal root):")
    print("  ddev drush migrate:import df_articles --update")
    print("  ddev drush migrate:import df_in_brief --update")
    print("  ddev drush df-migrate:prune-wordpress-orphans --execute   # remove Drupal posts gone from WP")
    print("  ddev drush df-migrate:sync-wordpress-paths")


if __name__ == "__main__":
    try:
        main()
    except RuntimeError as e:
        print(str(e), file=sys.stderr)
        sys.exit(1)
    except urllib.error.HTTPError as e:
        print(f"HTTP error: {e}", file=sys.stderr)
        sys.exit(1)
    except urllib.error.URLError as e:
        print(f"URL error: {e}", file=sys.stderr)
        sys.exit(1)

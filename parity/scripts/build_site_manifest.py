#!/usr/bin/env python3
"""
Crawl danafarberimpact.org and write parity/manifest/site-manifest.json.

Fetch order: direct HTTP first; on WAF block (403/401/202 sg-captcha), retry HTML/XML
via https://r.jina.ai/https://... (same as WP_HTTP_VIA_JINA=1 in migration fetch).
Set WP_HTTP_VIA_JINA=1 to force Jina for all fetches.
"""

from __future__ import annotations

import json
import os
import re
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from collections import deque
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlparse

SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent.parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from classify_url import (  # noqa: E402
    ALL_TEMPLATES,
    TEMPLATE_DOCUMENT,
    TEMPLATE_MEDIA,
    bump_counts,
    classify_path,
    classify_url,
    empty_template_counts,
    load_wp_rest_hints,
)

DEFAULT_BASE = "https://danafarberimpact.org"
USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)
REQUEST_DELAY = 0.2
MAX_DEPTH = 3
MAX_PAGES = 2000
SCHEMA_VERSION = "1.0"

SITEMAP_INDEX_PATHS = ("/sitemap_index.xml", "/wp-sitemap.xml", "/sitemap.xml")

KNOWN_EMBED_HOSTS = (
    "youtube.com",
    "youtube-nocookie.com",
    "youtu.be",
    "vimeo.com",
    "player.vimeo.com",
    "facebook.com",
    "twitter.com",
    "x.com",
    "platform.twitter.com",
    "soundcloud.com",
    "wistia.com",
    "fast.wistia.net",
    "jwplayer.com",
    "brightcove.net",
    "spotify.com",
    "embed.spotify.com",
    "issuu.com",
    "scribd.com",
)

MANIFEST_PATH = REPO_ROOT / "parity" / "manifest" / "site-manifest.json"
SUMMARY_PATH = REPO_ROOT / "parity" / "manifest" / "summary.json"

_SSL = ssl.create_default_context()


def get_base() -> str:
    return os.environ.get("WP_BASE_URL", DEFAULT_BASE).rstrip("/")


def _http_via_jina_enabled() -> bool:
    return os.environ.get("WP_HTTP_VIA_JINA", "").strip().lower() in (
        "1",
        "true",
        "yes",
        "y",
        "on",
    )


def _jina_proxy_url(target_url: str) -> str:
    return "https://r.jina.ai/" + target_url


def _decode_jina_reader_body(raw: bytes) -> bytes:
    if not raw or not raw.strip():
        raise RuntimeError("Jina reader returned empty body")
    text = raw.decode("utf-8", errors="replace")
    t = text.strip()
    if t.startswith(("[", "{", "<")):
        return t.encode("utf-8")
    marker = "Markdown Content:"
    i = text.find(marker)
    if i < 0:
        return t.encode("utf-8")
    text = text[i + len(marker) :].lstrip()
    if text.startswith("\n"):
        text = text[1:]
    if text.startswith("```"):
        lines = text.split("\n")
        if len(lines) >= 2 and lines[-1].strip() == "```":
            text = "\n".join(lines[1:-1])
    out = text.strip()
    if not out:
        raise RuntimeError("Jina reader markdown section was empty")
    return out.encode("utf-8")


def _response_is_blocked(status: int, headers: dict[str, str], body: bytes) -> bool:
    if status in (403, 401):
        return True
    h = {k.lower(): v for k, v in headers.items()}
    if status == 202 and "challenge" in (h.get("sg-captcha") or "").lower():
        return True
    probe = body[:1600].lower()
    if b"sgcaptcha" in probe or b"sg-captcha" in probe or b"well-known/sgcaptcha" in probe:
        return True
    return False


def _urllib_fetch(url: str, timeout: int, accept: str) -> tuple[int, dict[str, str], bytes]:
    parsed = urlparse(url)
    referer = f"{parsed.scheme}://{parsed.netloc}/"
    headers = {
        "User-Agent": USER_AGENT,
        "Accept": accept,
        "Accept-Language": "en-US,en;q=0.9",
        "Referer": referer,
    }
    cookie = os.environ.get("WP_HTTP_COOKIE", "").strip()
    if cookie:
        headers["Cookie"] = cookie
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=timeout, context=_SSL) as r:
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
    except Exception as e:
        return 0, {}, str(e).encode("utf-8", errors="replace")


class SiteFetcher:
    def __init__(self) -> None:
        self.blockers: list[str] = []
        self._last_request = 0.0
        self._jina_disabled = False
        self.html_fetch_enabled = True
        self._consecutive_html_failures = 0

    def _polite_wait(self) -> None:
        elapsed = time.monotonic() - self._last_request
        if elapsed < REQUEST_DELAY:
            time.sleep(REQUEST_DELAY - elapsed)
        self._last_request = time.monotonic()

    def fetch(
        self,
        url: str,
        *,
        accept: str = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        allow_jina_fallback: bool = True,
        timeout: int = 45,
    ) -> bytes | None:
        self._polite_wait()

        if _http_via_jina_enabled() and allow_jina_fallback:
            return self._fetch_via_jina(url, accept, timeout)

        status, hdrs, body = _urllib_fetch(url, timeout, accept)
        if status == 0:
            self.blockers.append(
                f"Request error for {url}: {body.decode('utf-8', errors='replace')[:160]}"
            )
            return None
        if not _response_is_blocked(status, hdrs, body):
            if status >= 400:
                self.blockers.append(f"HTTP {status} for {url}")
                return None
            return body

        if (
            allow_jina_fallback
            and not self._jina_disabled
            and self._looks_like_html_or_xml(accept, url)
        ):
            jina_body = self._fetch_via_jina(url, accept, timeout)
            if jina_body is not None:
                return jina_body

        preview = body[:200].decode("utf-8", errors="replace").replace("\n", " ")
        self.blockers.append(
            f"Blocked ({status}) for {url}: {preview[:120]}"
        )
        return None

    def fetch_json(self, url: str) -> bytes | None:
        body = self.fetch(
            url,
            accept="application/json, */*;q=0.8",
            allow_jina_fallback=_http_via_jina_enabled(),
        )
        if body and body.strip()[:1] in (b"{", b"["):
            return body
        return None

    def _looks_like_html_or_xml(self, accept: str, url: str) -> bool:
        lower = (accept + " " + url).lower()
        if "json" in lower and url.endswith(".json"):
            return False
        return any(x in lower for x in ("html", "xml", ".xml", "xhtml"))

    def _fetch_via_jina(self, url: str, accept: str, timeout: int) -> bytes | None:
        if self._jina_disabled:
            return None
        proxied = _jina_proxy_url(url)
        self._polite_wait()
        try:
            status, _hdrs, body = _urllib_fetch(proxied, min(max(timeout, 60), 120), accept)
            if status != 200:
                preview = body[:160].decode("utf-8", errors="replace")
                self.blockers.append(f"Jina HTTP {status} for {url}: {preview[:100]}")
                if status in (401, 403):
                    self._jina_disabled = True
                return None
            decoded = _decode_jina_reader_body(body)
            return decoded
        except Exception as e:
            self.blockers.append(f"Jina failed for {url}: {e}")
            return None


def normalize_url(url: str, base: str) -> str | None:
    url = (url or "").strip()
    if not url or url.startswith(("#", "mailto:", "tel:", "javascript:")):
        return None
    absolute = urljoin(base + "/", url)
    parsed = urlparse(absolute)
    if parsed.scheme not in ("http", "https"):
        return None
    base_host = urlparse(base).netloc.lower().replace("www.", "")
    host = parsed.netloc.lower().replace("www.", "")
    if host != base_host:
        return None
    path = parsed.path or "/"
    if path != "/" and path.endswith("/"):
        path = path.rstrip("/")
    clean = urllib.parse.urlunparse(
        (parsed.scheme, parsed.netloc, path, "", parsed.query, "")
    )
    return clean


def normalize_path_from_url(url: str) -> str:
    parsed = urlparse(url)
    path = parsed.path or "/"
    if path != "/" and path.endswith("/"):
        path = path.rstrip("/")
    return path or "/"


def iter_sitemap_locs(xml_bytes: bytes):
    try:
        root = ET.fromstring(xml_bytes)
    except ET.ParseError:
        return
    for elem in root.iter():
        if elem.tag.endswith("loc") and elem.text:
            yield elem.text.strip()


def discover_sitemap_urls(fetcher: SiteFetcher, base: str) -> set[str]:
    found: set[str] = set()
    to_fetch_sitemaps: deque[str] = deque()

    for path in SITEMAP_INDEX_PATHS:
        to_fetch_sitemaps.append(f"{base}{path}")

    seen_sitemaps: set[str] = set()
    while to_fetch_sitemaps:
        sm_url = to_fetch_sitemaps.popleft()
        if sm_url in seen_sitemaps:
            continue
        seen_sitemaps.add(sm_url)
        xml = fetcher.fetch(sm_url, accept="application/xml,text/xml,*/*")
        if not xml:
            continue
        locs = list(iter_sitemap_locs(xml))
        if not locs:
            continue
        if any(loc.lower().endswith(".xml") for loc in locs):
            for loc in locs:
                if loc.lower().endswith(".xml"):
                    norm = normalize_url(loc, base)
                    if norm:
                        to_fetch_sitemaps.append(norm)
                else:
                    norm = normalize_url(loc, base)
                    if norm:
                        found.add(norm)
        else:
            for loc in locs:
                norm = normalize_url(loc, base)
                if norm:
                    found.add(norm)
    return found


class PageParser(HTMLParser):
    def __init__(self, page_url: str, base: str) -> None:
        super().__init__(convert_charrefs=True)
        self.page_url = page_url
        self.base = base
        self.title = ""
        self.in_title = False
        self.meta: dict[str, str] = {}
        self.links_out: list[str] = []
        self.media: list[str] = []
        self.embeds: list[dict[str, str]] = []
        self._nav_depth = 0

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attr = {k.lower(): (v or "") for k, v in attrs}
        if tag == "title":
            self.in_title = True
        if tag == "meta":
            name = (attr.get("name") or attr.get("property") or "").lower()
            content = attr.get("content", "").strip()
            if name and content:
                self.meta[name] = content
        if tag in ("a", "link") and "href" in attr:
            self._record_href(attr["href"])
        if tag in ("img", "source", "video", "audio") and "src" in attr:
            self._record_media(attr["src"])
        if tag == "a" and "href" in attr:
            self._record_media(attr["href"])
        if tag == "iframe" and "src" in attr:
            self._record_embed("iframe", attr["src"])
        if tag == "script" and "src" in attr:
            self._record_embed("script", attr["src"])
        if tag == "nav":
            self._nav_depth += 1

    def handle_endtag(self, tag: str) -> None:
        if tag == "title":
            self.in_title = False
        if tag == "nav":
            self._nav_depth = max(0, self._nav_depth - 1)

    def handle_data(self, data: str) -> None:
        if self.in_title:
            self.title += data

    def _record_href(self, href: str) -> None:
        norm = normalize_url(href, self.base)
        if norm:
            self.links_out.append(norm)

    def _record_media(self, src: str) -> None:
        norm = normalize_url(src, self.base) or urljoin(self.page_url, src)
        if not norm:
            return
        lower = norm.lower()
        if "/wp-content/uploads/" in lower or re.search(
            r"\.(jpe?g|png|gif|webp|svg|pdf|mp4|mp3|doc|docx)(?:\?|$)", lower
        ):
            if norm not in self.media:
                self.media.append(norm)

    def _record_embed(self, kind: str, src: str) -> None:
        full = urljoin(self.page_url, src)
        host = urlparse(full).netloc.lower()
        if not any(h in host for h in KNOWN_EMBED_HOSTS):
            return
        entry = {"type": kind, "src": full}
        if entry not in self.embeds:
            self.embeds.append(entry)


def extract_homepage_menus(html: bytes, base: str) -> dict[str, list[dict[str, str]]]:
    text = html.decode("utf-8", errors="replace")
    menus: dict[str, list[dict[str, str]]] = {"primary": []}
    nav_chunks = re.findall(r"<nav[^>]*>(.*?)</nav>", text, re.I | re.S)
    seen: set[str] = set()
    for chunk in nav_chunks[:6]:
        for href, label in re.findall(
            r'<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>', chunk, re.I | re.S
        ):
            label_clean = re.sub(r"<[^>]+>", "", label).strip()
            if not label_clean:
                continue
            norm = normalize_url(href, base)
            if not norm or norm in seen:
                continue
            seen.add(norm)
            menus["primary"].append(
                {
                    "label": label_clean,
                    "url": norm,
                    "path": normalize_path_from_url(norm),
                }
            )
    return menus


def is_crawlable_html(url: str) -> bool:
    lower = url.lower()
    if DOCUMENT_EXT_RE_match(lower):
        return False
    static_ext = (
        ".jpg",
        ".jpeg",
        ".png",
        ".gif",
        ".webp",
        ".svg",
        ".mp4",
        ".mp3",
        ".zip",
        ".css",
        ".js",
        ".woff",
        ".woff2",
        ".ttf",
        ".eot",
        ".map",
        ".xml",
    )
    if lower.endswith(static_ext):
        return False
    if "/wp-content/plugins/" in lower or "/wp-content/themes/" in lower:
        return False
    if "/wp-content/uploads/" in lower:
        return False
    if "/wp-json/" in lower or "/feed" in lower:
        return False
    if "wp-admin" in lower or "wp-login" in lower:
        return False
    return True


def DOCUMENT_EXT_RE_match(lower: str) -> bool:
    return bool(re.search(r"\.(pdf|doc|docx|xls|xlsx|ppt|pptx)(?:\?|$)", lower))


def build_manifest() -> dict:
    base = get_base()
    fetcher = SiteFetcher()
    wp_hints = load_wp_rest_hints(fetcher.fetch_json, base)

    sitemap_urls = discover_sitemap_urls(fetcher, base)
    home = base.rstrip("/") or base

    queue: deque[tuple[str, int, bool]] = deque()
    queue.append((home, 0, True))

    visited: set[str] = set()
    records: dict[str, dict] = {}
    menus: dict[str, list[dict[str, str]]] = {}
    fetched_html_count = 0

    def canonical(url: str) -> str:
        parsed = urlparse(url)
        path = parsed.path or "/"
        if path != "/" and path.endswith("/"):
            path = path.rstrip("/")
        host = parsed.netloc.lower()
        return urllib.parse.urlunparse((parsed.scheme, host, path, "", "", ""))

    def record_url(
        url: str,
        *,
        title: str = "",
        meta: dict[str, str] | None = None,
        links_out: list[str] | None = None,
        media: list[str] | None = None,
        embeds: list[dict[str, str]] | None = None,
    ) -> None:
        path = normalize_path_from_url(url)
        template = classify_path(path, query=urlparse(url).query, wp_hints=wp_hints)
        records[url] = {
            "path": path,
            "url": url,
            "template": template,
            "title": title,
            "meta": meta or {},
            "links_out": sorted(set(links_out or [])),
            "media": media or [],
            "embeds": embeds or [],
        }

    for sm_url in sorted(sitemap_urls):
        if len(records) >= MAX_PAGES:
            break
        record_url(canonical(sm_url))

    while queue and len(records) < MAX_PAGES:
        url, depth, fetch_html = queue.popleft()
        url = canonical(url)
        if url in visited:
            continue
        visited.add(url)

        path = normalize_path_from_url(url)
        title = records.get(url, {}).get("title", "")
        meta = dict(records.get(url, {}).get("meta") or {})
        links_out: list[str] = list(records.get(url, {}).get("links_out") or [])
        media: list[str] = list(records.get(url, {}).get("media") or [])
        embeds: list[dict] = list(records.get(url, {}).get("embeds") or [])

        should_fetch = (
            fetch_html
            and is_crawlable_html(url)
            and fetched_html_count < MAX_PAGES
            and fetcher.html_fetch_enabled
        )
        if should_fetch:
            body = None
            attempts = 3 if path in ("/", "") else 1
            for attempt in range(attempts):
                body = fetcher.fetch(url)
                if body:
                    break
                if attempt + 1 < attempts:
                    time.sleep(2.0)
            if body:
                fetched_html_count += 1
                fetcher._consecutive_html_failures = 0
                parser = PageParser(url, base)
                try:
                    parser.feed(body.decode("utf-8", errors="replace"))
                except Exception:
                    pass
                title = parser.title.strip()
                meta = parser.meta
                links_out = parser.links_out
                media = parser.media
                embeds = parser.embeds
                if path in ("/", "") and not menus.get("primary"):
                    menus = extract_homepage_menus(body, base)

                if depth < MAX_DEPTH:
                    for link in links_out:
                        if not is_crawlable_html(link):
                            continue
                        lk = canonical(link)
                        if lk not in visited and len(records) + len(queue) < MAX_PAGES:
                            record_url(lk)
                            queue.append((lk, depth + 1, True))
            else:
                fetcher._consecutive_html_failures += 1
                if fetcher._consecutive_html_failures >= 5:
                    fetcher.html_fetch_enabled = False
                    fetcher.blockers.append(
                        "Disabled further HTML fetches after repeated WAF blocks "
                        "(URLs still recorded from sitemap/homepage links)."
                    )

        for extra in links_out + media:
            ek = canonical(extra) if normalize_url(extra, base) else extra
            if ek in records:
                continue
            extra_path = normalize_path_from_url(ek)
            extra_tpl = classify_url(ek, wp_hints=wp_hints)
            if extra_tpl in (TEMPLATE_MEDIA, TEMPLATE_DOCUMENT):
                records[ek] = {
                    "path": extra_path,
                    "url": ek,
                    "template": extra_tpl,
                    "title": "",
                    "meta": {},
                    "links_out": [],
                    "media": [ek] if extra_tpl == TEMPLATE_MEDIA else [],
                    "embeds": [],
                }

        record_url(
            url,
            title=title,
            meta=meta,
            links_out=links_out,
            media=media,
            embeds=embeds,
        )

    counts = empty_template_counts()
    urls_list = []
    for url in sorted(records.keys()):
        rec = records[url]
        bump_counts(counts, rec["template"])
        urls_list.append(rec)

    generated_at = datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace(
        "+00:00", "Z"
    )

    unique_blockers = list(dict.fromkeys(fetcher.blockers))

    return {
        "schema_version": SCHEMA_VERSION,
        "generated_at": generated_at,
        "base_url": base,
        "counts_by_template": counts,
        "menus": menus,
        "crawl_blockers": unique_blockers,
        "urls": urls_list,
    }


def write_outputs(manifest: dict) -> None:
    MANIFEST_PATH.parent.mkdir(parents=True, exist_ok=True)
    with MANIFEST_PATH.open("w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2, ensure_ascii=False)
        f.write("\n")

    summary = {
        "schema_version": manifest["schema_version"],
        "generated_at": manifest["generated_at"],
        "base_url": manifest["base_url"],
        "total_urls": len(manifest["urls"]),
        "counts_by_template": manifest["counts_by_template"],
        "crawl_blocker_count": len(manifest.get("crawl_blockers") or []),
    }
    with SUMMARY_PATH.open("w", encoding="utf-8") as f:
        json.dump(summary, f, indent=2, ensure_ascii=False)
        f.write("\n")


def main() -> int:
    manifest = build_manifest()
    write_outputs(manifest)
    total = len(manifest["urls"])
    print(f"Wrote {MANIFEST_PATH} ({total} URLs)")
    print(f"Wrote {SUMMARY_PATH}")
    for tpl in ALL_TEMPLATES:
        n = manifest["counts_by_template"].get(tpl, 0)
        if n:
            print(f"  {tpl}: {n}")
    blockers = manifest.get("crawl_blockers") or []
    if blockers:
        print(f"Crawl blockers ({len(blockers)}):", file=sys.stderr)
        for b in blockers[:15]:
            print(f"  - {b}", file=sys.stderr)
        if len(blockers) > 15:
            print(f"  … and {len(blockers) - 15} more", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

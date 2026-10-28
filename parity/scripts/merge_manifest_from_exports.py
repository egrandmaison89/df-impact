#!/usr/bin/env python3
"""
Merge committed WordPress export JSON (df_migrate data) into site-manifest.json.

Use when live crawl is blocked by SiteGround captcha. Export paths are the
editorial URL contract for migrated content; live crawl adds landing/archives.
"""

from __future__ import annotations

import json
import sys
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent.parent
sys.path.insert(0, str(SCRIPT_DIR))

from classify_url import bump_counts, classify_path, empty_template_counts  # noqa: E402

MANIFEST_PATH = REPO_ROOT / "parity" / "manifest" / "site-manifest.json"
SUMMARY_PATH = REPO_ROOT / "parity" / "manifest" / "summary.json"
DF_DATA = REPO_ROOT / "drupal" / "web" / "modules" / "custom" / "df_migrate" / "data"
BASE = "https://danafarberimpact.org"


def path_from_wp_url(url: str) -> str:
    p = urlparse(url)
    path = p.path or "/"
    if not path.startswith("/"):
        path = "/" + path
    return path.rstrip("/") or "/"


def load_manifest() -> dict:
    if MANIFEST_PATH.is_file():
        return json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    return {
        "schema_version": "1.0",
        "generated_at": None,
        "base_url": BASE,
        "sources": [],
        "urls": [],
        "menus": {},
        "counts_by_template": empty_template_counts(),
    }


def index_urls(manifest: dict) -> dict[str, dict]:
    out: dict[str, dict] = {}
    for entry in manifest.get("urls") or []:
        path = entry.get("path") or path_from_wp_url(entry.get("url", ""))
        out[path] = entry
    return out


def add_entry(
    by_path: dict[str, dict],
    *,
    path: str,
    template: str,
    title: str = "",
    source: str,
    wp_url: str | None = None,
    drupal_alias: str | None = None,
) -> None:
    path = path.rstrip("/") or "/"
    url = BASE + (path if path.startswith("/") else "/" + path)
    existing = by_path.get(path)
    if existing:
        existing.setdefault("sources", [])
        if source not in existing["sources"]:
            existing["sources"].append(source)
        if title and not existing.get("title"):
            existing["title"] = title
        if drupal_alias:
            existing["drupal_alias"] = drupal_alias
        return
    by_path[path] = {
        "path": path,
        "url": wp_url or url,
        "template": template,
        "title": title,
        "sources": [source],
        **({"drupal_alias": drupal_alias} if drupal_alias else {}),
    }


def main() -> None:
    manifest = load_manifest()
    by_path = index_urls(manifest)

    articles = json.loads((DF_DATA / "articles.json").read_text(encoding="utf-8"))
    in_brief = json.loads((DF_DATA / "in_brief.json").read_text(encoding="utf-8"))
    issues = json.loads((DF_DATA / "issues.json").read_text(encoding="utf-8"))
    redirects = json.loads((DF_DATA / "redirects.json").read_text(encoding="utf-8"))

    for row in articles:
        alias = row.get("drupal_alias") or f"/stories/{row.get('slug', '')}"
        add_entry(
            by_path,
            path=alias,
            template="article",
            title=row.get("title") or "",
            source="articles.json",
            wp_url=row.get("wp_url"),
            drupal_alias=alias,
        )

    for row in in_brief:
        alias = row.get("drupal_alias") or f"/in-brief/{row.get('slug', '')}"
        add_entry(
            by_path,
            path=alias,
            template="in_brief",
            title=row.get("title") or "",
            source="in_brief.json",
            wp_url=row.get("wp_url"),
            drupal_alias=alias,
        )

    for row in issues:
        alias = row.get("path_alias") or f"/issues/{row.get('slug', '')}"
        alias = alias.replace(" ", "-").lower()
        if not alias.startswith("/"):
            alias = "/" + alias
        add_entry(
            by_path,
            path=alias,
            template="issue",
            title=row.get("name") or "",
            source="issues.json",
        )

    static_paths = [
        ("/homepage/", "homepage", "Homepage"),
        ("/home", "homepage", "Home (Drupal front)"),
        ("/about", "landing_page", "About"),
        ("/search", "search", "Search"),
        ("/topics", "archive", "Topics archive"),
        ("/in-brief", "archive", "In Brief archive"),
        ("/stories", "archive", "Stories archive"),
        ("/issues", "archive", "Issues archive"),
    ]
    for path, template, title in static_paths:
        add_entry(by_path, path=path, template=template, title=title, source="static_core")

    for row in redirects:
        src = row.get("source") or row.get("from") or ""
        if isinstance(src, str) and src.startswith("/"):
            tpl = classify_path(src)
            add_entry(by_path, path=src.rstrip("/") or "/", template=tpl, source="redirects.json")

    counts = empty_template_counts()
    urls = sorted(by_path.values(), key=lambda u: u["path"])
    for u in urls:
        bump_counts(counts, u.get("template") or "other")

    manifest["generated_at"] = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    manifest["merged_from_exports_at"] = manifest["generated_at"]
    manifest["sources"] = sorted(
        {
            *(manifest.get("sources") or []),
            "live_crawl",
            "df_migrate_export",
        }
    )
    manifest["urls"] = urls
    manifest["counts_by_template"] = counts
    manifest["total_urls"] = len(urls)
    manifest["export_counts"] = {
        "articles.json": len(articles),
        "in_brief.json": len(in_brief),
        "issues.json": len(issues),
        "redirects.json": len(redirects),
    }

    MANIFEST_PATH.parent.mkdir(parents=True, exist_ok=True)
    MANIFEST_PATH.write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    SUMMARY_PATH.write_text(
        json.dumps(
            {
                "schema_version": manifest["schema_version"],
                "generated_at": manifest["generated_at"],
                "base_url": manifest["base_url"],
                "total_urls": len(urls),
                "counts_by_template": counts,
                "export_counts": manifest["export_counts"],
            },
            indent=2,
        ),
        encoding="utf-8",
    )
    print(f"Wrote {len(urls)} URLs to {MANIFEST_PATH}")
    print(json.dumps(counts, indent=2))


if __name__ == "__main__":
    main()

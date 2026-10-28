#!/usr/bin/env python3
"""
Regenerate migration JSON from the gitignored WXR export.

Writes categories.json, tags.json, posts_page_1_raw.json (all posts), then runs
the same transformation logic as process_wp_data.py.
"""

from __future__ import annotations

import json
import shutil
import subprocess
import sys
from pathlib import Path

MIGRATION_DATA = Path(__file__).resolve().parent
WXR = MIGRATION_DATA / "private" / "wp-export.xml"
DF_DATA = MIGRATION_DATA.parent / "web" / "modules" / "custom" / "df_migrate" / "data"

from wxr_to_rest import load_attachments, load_issue_pages, load_posts, load_taxonomies  # noqa: E402


def main() -> None:
    wxr = Path(sys.argv[1]) if len(sys.argv) > 1 else WXR
    if not wxr.is_file():
        sys.exit(f"Missing WXR: {wxr} (see docs/WORDPRESS_WXR_EXPORT.md)")

    attachments = load_attachments(wxr)
    categories, tags = load_taxonomies(wxr)
    posts = load_posts(wxr, attachments)
    issue_pages = load_issue_pages(wxr)

    (MIGRATION_DATA / "categories.json").write_text(json.dumps(categories, indent=2), encoding="utf-8")
    (MIGRATION_DATA / "tags.json").write_text(json.dumps(tags, indent=2), encoding="utf-8")

    # Stash old chunked post files so process_wp_data only reads WXR posts.
    backup = MIGRATION_DATA / "_wxr_backup_posts"
    backup.mkdir(exist_ok=True)
    for old in MIGRATION_DATA.glob("posts_page_*_raw.json"):
        shutil.move(str(old), str(backup / old.name))

    (MIGRATION_DATA / "posts_page_1_raw.json").write_text(json.dumps(posts, indent=2), encoding="utf-8")
    (MIGRATION_DATA / "pages_raw.json").write_text("[]", encoding="utf-8")

    # Minimal pages export (issue landing metadata for reconcile notes).
    (MIGRATION_DATA / "wxr_issue_pages.json").write_text(json.dumps(issue_pages, indent=2), encoding="utf-8")

    print(f"WXR posts: {len(posts)} | categories: {len(categories)} | tags: {len(tags)} | issue pages: {len(issue_pages)}")

    tag_counts: dict[int, int] = {}
    for post in posts:
        for tid in post.get("tags", []):
            tag_counts[tid] = tag_counts.get(tid, 0) + 1
    for tag in tags:
        tag["count"] = tag_counts.get(tag["id"], 0)
    (MIGRATION_DATA / "tags.json").write_text(json.dumps(tags, indent=2), encoding="utf-8")

    subprocess.check_call([sys.executable, str(MIGRATION_DATA / "process_wp_data.py")], cwd=MIGRATION_DATA)

    # Merge issue path aliases from WXR pages (newest issues may not have tag counts yet).
    issues_path = MIGRATION_DATA / "issues.json"
    issues = json.loads(issues_path.read_text(encoding="utf-8"))
    by_key = {(i["season"], i["year"]): i for i in issues}
    for page in issue_pages:
        season = page["season"].replace("late fall", "late fall")
        key = (season, page["year"])
        if key not in by_key:
            name = page["title"] or f"{page['season'].title()} {page['year']}"
            issues.append(
                {
                    "wp_tag_id": 0,
                    "name": name,
                    "slug": page["slug"].replace("-issue", ""),
                    "season": season,
                    "year": page["year"],
                    "path_alias": page["path"],
                    "article_count": 0,
                }
            )
        else:
            by_key[key]["path_alias"] = page["path"]

    issues_path.write_text(json.dumps(issues, indent=2), encoding="utf-8")

    def _iso_dates(rows: list[dict]) -> None:
        for row in rows:
            for key in ("created", "changed"):
                val = row.get(key, "")
                if isinstance(val, str) and " " in val and "T" not in val:
                    row[key] = val.replace(" ", "T", 1)

    for fname in ("articles.json", "in_brief.json"):
        path = MIGRATION_DATA / fname
        if path.is_file():
            data = json.loads(path.read_text(encoding="utf-8"))
            _iso_dates(data)
            path.write_text(json.dumps(data, indent=2), encoding="utf-8")

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
            shutil.copy2(src, DF_DATA / name)

    print("Copied migration JSON to df_migrate/data/")


if __name__ == "__main__":
    main()

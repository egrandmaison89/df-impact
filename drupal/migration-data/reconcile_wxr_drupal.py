#!/usr/bin/env python3
"""Compare WXR published posts/pages to Drupal path aliases (requires local Drupal DB via drush)."""

from __future__ import annotations

import json
import subprocess
from pathlib import Path

MIGRATION_DATA = Path(__file__).resolve().parent
INVENTORY = MIGRATION_DATA / "wxr_content_inventory.json"
OUT = MIGRATION_DATA / "wxr_drupal_reconcile.json"


def drush_sql(query: str) -> str:
    import os

    os.environ.setdefault("DDEV_DB_PORT", "22633")
    cmd = ["./vendor/bin/drush", "-r", "web", "sqlq", query]
    return subprocess.check_output(cmd, cwd=MIGRATION_DATA.parent, text=True, env=os.environ)


def load_drupal_aliases() -> dict[str, str]:
    raw = drush_sql(
        "SELECT alias, path FROM path_alias WHERE alias NOT LIKE '/admin%' ORDER BY alias"
    )
    aliases: dict[str, str] = {}
    for line in raw.splitlines():
        parts = line.split("\t")
        if len(parts) >= 2:
            aliases[parts[0].rstrip("/") or "/"] = parts[1]
    return aliases


def main() -> None:
    if not INVENTORY.is_file():
        raise SystemExit(f"Run parse_wxr_export.py first ({INVENTORY} missing)")
    items = json.loads(INVENTORY.read_text(encoding="utf-8"))
    published = [i for i in items if i.get("status") == "publish" and i.get("post_type") in ("post", "page")]
    aliases = load_drupal_aliases()

    missing = []
    extra_note = []
    matched = 0
    for row in published:
        path = row.get("path", "/").rstrip("/") or "/"
        if path in aliases or (path + "/") in aliases:
            matched += 1
            continue
        missing.append({"path": path, "title": row.get("title"), "wp_id": row.get("wp_id"), "type": row.get("post_type")})

    report = {
        "published_posts_pages": len(published),
        "matched_paths": matched,
        "missing_in_drupal": missing,
        "missing_count": len(missing),
        "drupal_alias_count": len(aliases),
    }
    OUT.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps({k: report[k] for k in report if k != "missing_in_drupal"}, indent=2))
    if missing[:5]:
        print("sample missing:", missing[:5])


if __name__ == "__main__":
    main()

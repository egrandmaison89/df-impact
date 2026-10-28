#!/usr/bin/env python3
"""
Parse WordPress WXR export (private/, gitignored) into migration JSON + design reference.

Default input: private/wp-export.xml (see docs/WORDPRESS_WXR_EXPORT.md).
"""

from __future__ import annotations

import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse
import xml.etree.ElementTree as ET

MIGRATION_DATA = Path(__file__).resolve().parent
DEFAULT_WXR = MIGRATION_DATA / "private" / "wp-export.xml"
OUT_DIR = MIGRATION_DATA
DF_DATA = MIGRATION_DATA.parent / "web" / "modules" / "custom" / "df_migrate" / "data"

NS = {
    "content": "http://purl.org/rss/1.0/modules/content/",
    "wp": "http://wordpress.org/export/1.2/",
    "dc": "http://purl.org/dc/elements/1.1/",
    "excerpt": "http://wordpress.org/export/1.2/excerpt/",
}


def text(el, tag: str) -> str:
    if el is None:
        return ""
    node = el.find(tag, NS) if ":" in tag else el.find(tag)
    if node is None:
        node = el.find(f"{{{NS['wp']}}}{tag.split(':')[-1]}") if tag.startswith("wp:") else None
    return (node.text or "").strip() if node is not None else ""


def post_meta(item) -> dict[str, str]:
    out: dict[str, str] = {}
    for pm in item.findall("wp:postmeta", NS):
        k = pm.find("wp:meta_key", NS)
        v = pm.find("wp:meta_value", NS)
        if k is not None and k.text:
            out[k.text] = v.text if v is not None and v.text else ""
    return out


def permalink_path(link: str) -> str:
    if not link:
        return "/"
    p = urlparse(link).path.rstrip("/") or "/"
    return p if p.startswith("/") else f"/{p}"


def parse_wxr(wxr_path: Path) -> dict:
    media: list[dict] = []
    content_items: list[dict] = []
    chrome: dict = {
        "wp_global_styles": [],
        "custom_css": [],
        "wp_template_part": [],
        "wp_template": [],
        "wp_navigation": [],
        "nav_menu_item": [],
        "ultp_templates": [],
        "ultp_builder": [],
        "wp_block": [],
    }

    for _event, item in ET.iterparse(wxr_path, events=("end",)):
        if not item.tag.endswith("item"):
            continue

        ptype = text(item, "wp:post_type")
        status = text(item, "wp:status")
        title_el = item.find("title")
        title = title_el.text if title_el is not None and title_el.text else ""
        link_el = item.find("link")
        link = link_el.text if link_el is not None and link_el.text else ""
        post_id = text(item, "wp:post_id")
        slug = text(item, "wp:post_name")
        meta = post_meta(item)
        content_el = item.find("content:encoded", NS)
        body = content_el.text if content_el is not None and content_el.text else ""

        if ptype == "attachment":
            attached = meta.get("_wp_attached_file", "")
            mime = text(item, "wp:post_mime_type")
            src = link or ""
            if not src and attached:
                src = f"https://danafarberimpact.org/wp-content/uploads/{attached.lstrip('/')}"
            filename = attached.split("/")[-1] if attached else (urlparse(src).path.split("/")[-1] if src else "")
            media.append(
                {
                    "wp_id": int(post_id) if post_id.isdigit() else post_id,
                    "source_url": src,
                    "wp_upload_path": attached.replace("\\", "/"),
                    "title": title,
                    "alt_text": meta.get("_wp_attachment_image_alt", ""),
                    "caption": "",
                    "mime_type": mime or "image/jpeg",
                    "filename": filename,
                }
            )
        elif ptype in ("post", "page"):
            content_items.append(
                {
                    "wp_id": int(post_id) if post_id.isdigit() else post_id,
                    "post_type": ptype,
                    "status": status,
                    "title": title,
                    "slug": slug,
                    "link": link,
                    "path": permalink_path(link),
                }
            )
        elif ptype in chrome:
            chrome[ptype].append(
                {
                    "wp_id": post_id,
                    "title": title,
                    "slug": slug,
                    "status": status,
                    "link": link,
                    "content": body,
                    "meta": {k: v for k, v in meta.items() if not k.startswith("_") or k in ("_wp_page_template",)},
                }
            )

        item.clear()

    return {"media": media, "content_items": content_items, "chrome": chrome}


def extract_design_tokens(chrome: dict) -> dict:
    """Best-effort token extraction from WP global styles, custom CSS, and template HTML."""
    tokens: dict = {
        "colors": set(),
        "fontFamilies": set(),
        "fontSizes": set(),
        "cssSnippets": [],
    }

    hex_pat = re.compile(r"#(?:[0-9a-fA-F]{3}){1,2}\b")
    rem_pat = re.compile(r"\b\d+(?:\.\d+)?rem\b")
    font_pat = re.compile(r"font-family\s*:\s*([^;}{]+)", re.I)

    def scan(text_blob: str, label: str) -> None:
        if not text_blob:
            return
        for h in hex_pat.findall(text_blob):
            tokens["colors"].add(h.lower())
        for r in rem_pat.findall(text_blob):
            tokens["fontSizes"].add(r)
        for m in font_pat.findall(text_blob):
            tokens["fontFamilies"].add(m.strip().strip('"').split(",")[0].strip())
        if len(text_blob) < 12000:
            tokens["cssSnippets"].append({"label": label, "length": len(text_blob)})

    for row in chrome.get("custom_css", []):
        scan(row.get("content", ""), f"custom_css:{row.get('slug')}")
    for row in chrome.get("wp_global_styles", []):
        scan(row.get("content", ""), f"global_styles:{row.get('title')}")
    for row in chrome.get("wp_template_part", []):
        scan(row.get("content", ""), f"template_part:{row.get('slug')}")
    for row in chrome.get("ultp_templates", []) + chrome.get("ultp_builder", []):
        scan(row.get("content", ""), f"ultp:{row.get('slug')}")

    return {
        "colors": sorted(tokens["colors"]),
        "fontFamilies": sorted(tokens["fontFamilies"]),
        "fontSizes": sorted(tokens["fontSizes"]),
        "cssSnippetSources": tokens["cssSnippets"],
    }


def main() -> None:
    wxr = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_WXR
    if not wxr.is_file():
        sys.exit(f"Missing WXR: {wxr}")

    parsed = parse_wxr(wxr)
    media = parsed["media"]
    content = parsed["content_items"]
    chrome = parsed["chrome"]
    tokens = extract_design_tokens(chrome)

    stamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    summary = {
        "generated_at": stamp,
        "wxr_path": str(wxr.name),
        "attachment_count": len(media),
        "published_posts": sum(1 for c in content if c["post_type"] == "post" and c["status"] == "publish"),
        "published_pages": sum(1 for c in content if c["post_type"] == "page" and c["status"] == "publish"),
        "chrome_counts": {k: len(v) for k, v in chrome.items()},
    }

    (OUT_DIR / "media.json").write_text(json.dumps(media, indent=2), encoding="utf-8")
    (OUT_DIR / "wxr_content_inventory.json").write_text(json.dumps(content, indent=2), encoding="utf-8")
    (OUT_DIR / "wp_chrome_reference.json").write_text(json.dumps(chrome, indent=2), encoding="utf-8")
    (OUT_DIR / "wp_design_tokens.json").write_text(json.dumps(tokens, indent=2), encoding="utf-8")
    (OUT_DIR / "wxr_parse_summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")

    DF_DATA.mkdir(parents=True, exist_ok=True)
    (DF_DATA / "media.json").write_text(json.dumps(media, indent=2), encoding="utf-8")

    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()

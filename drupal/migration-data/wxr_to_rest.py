"""Convert WXR XML items into WordPress REST-shaped dicts for process_wp_data."""

from __future__ import annotations

import re
import xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import urlparse

WP = "{http://wordpress.org/export/1.2/}"
NS = {
    "wp": "http://wordpress.org/export/1.2/",
    "content": "http://purl.org/rss/1.0/modules/content/",
    "excerpt": "http://wordpress.org/export/1.2/excerpt/",
}


def _text(el, local: str) -> str:
    node = el.find(f"wp:{local}", NS)
    return (node.text or "").strip() if node is not None else ""


def _post_meta(item) -> dict[str, str]:
    out: dict[str, str] = {}
    for pm in item.findall("wp:postmeta", NS):
        k = pm.find("wp:meta_key", NS)
        v = pm.find("wp:meta_value", NS)
        if k is not None and k.text:
            out[k.text] = v.text if v is not None and v.text else ""
    return out


def load_taxonomies(wxr_path: Path) -> tuple[list[dict], list[dict]]:
    root = ET.parse(wxr_path).getroot()
    channel = root.find("channel")
    categories: list[dict] = []
    tags: list[dict] = []
    for child in channel:
        if child.tag == WP + "category":
            tid = int(child.find(f"{WP}term_id").text)
            slug = child.find(f"{WP}category_nicename").text
            name = child.find(f"{WP}cat_name").text
            parent = child.find(f"{WP}category_parent").text
            categories.append(
                {
                    "id": tid,
                    "name": name,
                    "slug": slug,
                    "parent": 0 if not parent or parent == "None" else 0,
                    "count": 0,
                }
            )
        elif child.tag == WP + "tag":
            tid = int(child.find(f"{WP}term_id").text)
            slug = child.find(f"{WP}tag_slug").text
            name = child.find(f"{WP}tag_name").text
            tags.append({"id": tid, "name": name, "slug": slug, "count": 0})

    return categories, tags


def load_attachments(wxr_path: Path) -> dict[int, dict]:
    by_id: dict[int, dict] = {}
    for _event, item in ET.iterparse(wxr_path, events=("end",)):
        if not item.tag.endswith("item"):
            continue
        if _text(item, "post_type") != "attachment":
            item.clear()
            continue
        pid = int(_text(item, "post_id"))
        link = (item.find("link").text or "").strip()
        meta = _post_meta(item)
        rel = meta.get("_wp_attached_file", "")
        if not link and rel:
            link = f"https://danafarberimpact.org/wp-content/uploads/{rel.lstrip('/')}"
        by_id[pid] = {"id": pid, "source_url": link, "relative": rel}
        item.clear()
    return by_id


def load_posts(wxr_path: Path, attachments: dict[int, dict]) -> list[dict]:
    cats_by_slug: dict[str, int] = {}
    tags_by_slug: dict[str, int] = {}
    cats, tags = load_taxonomies(wxr_path)
    for c in cats:
        cats_by_slug[c["slug"]] = c["id"]
    for t in tags:
        tags_by_slug[t["slug"]] = t["id"]

    posts: list[dict] = []
    for _event, item in ET.iterparse(wxr_path, events=("end",)):
        if not item.tag.endswith("item"):
            continue
        if _text(item, "post_type") != "post":
            item.clear()
            continue
        status = _text(item, "status")
        if status not in ("publish", "draft"):
            item.clear()
            continue

        pid = int(_text(item, "post_id"))
        title = item.find("title").text if item.find("title") is not None else ""
        link = (item.find("link").text or "").strip()
        slug = _text(item, "post_name")
        meta = _post_meta(item)
        content_el = item.find("content:encoded", NS)
        excerpt_el = item.find("excerpt:encoded", NS)
        body = content_el.text if content_el is not None and content_el.text else ""
        excerpt = excerpt_el.text if excerpt_el is not None and excerpt_el.text else ""

        cat_ids: list[int] = []
        tag_ids: list[int] = []
        for c in item.findall("category"):
            domain = c.get("domain", "")
            nicename = c.get("nicename", "")
            if domain == "category" and nicename in cats_by_slug:
                cat_ids.append(cats_by_slug[nicename])
            elif domain in ("post_tag", "tag") and nicename in tags_by_slug:
                tag_ids.append(tags_by_slug[nicename])

        thumb = int(meta.get("_thumbnail_id") or 0)
        featured_url = attachments.get(thumb, {}).get("source_url", "")
        yoast_desc = meta.get("_yoast_wpseo_metadesc", "")

        posts.append(
            {
                "id": pid,
                "slug": slug,
                "link": link,
                "status": status,
                "date": _text(item, "post_date").replace(" ", "T", 1),
                "modified": _text(item, "post_modified").replace(" ", "T", 1),
                "title": {"rendered": title or ""},
                "content": {"rendered": body},
                "excerpt": {"rendered": excerpt},
                "categories": cat_ids,
                "tags": tag_ids,
                "featured_media": thumb,
                "yoast_head_json": {"description": yoast_desc},
                "_embedded": {"wp:featuredmedia": [{"source_url": featured_url, "id": thumb}]},
            }
        )
        item.clear()

    return posts


def load_issue_pages(wxr_path: Path) -> list[dict]:
    """Published WP pages whose slug ends with -issue (issue landing URLs)."""
    out: list[dict] = []
    pat = re.compile(r"^(spring|summer|fall|winter|late-fall)-(\d{4})-issue$", re.I)
    for _event, item in ET.iterparse(wxr_path, events=("end",)):
        if not item.tag.endswith("item"):
            continue
        if _text(item, "post_type") != "page" or _text(item, "status") != "publish":
            item.clear()
            continue
        slug = _text(item, "post_name")
        m = pat.match(slug)
        if not m:
            item.clear()
            continue
        link = (item.find("link").text or "").strip()
        path = urlparse(link).path.rstrip("/") or f"/{slug}"
        season = m.group(1).lower().replace("-", " ")
        if season == "late fall":
            season = "late fall"
        out.append(
            {
                "slug": slug,
                "path": path if path.startswith("/") else f"/{path}",
                "season": season,
                "year": int(m.group(2)),
                "title": item.find("title").text if item.find("title") is not None else "",
            }
        )
        item.clear()
    return out

#!/usr/bin/env python3
"""Extract behavior-related snippets from WXR for DYNAMIC_BEHAVIOR_INVENTORY.md maintenance."""

from __future__ import annotations

import re
import sys
from pathlib import Path
import xml.etree.ElementTree as ET

WXR = Path(__file__).resolve().parent / "private" / "wp-export.xml"
NS = {"wp": "http://wordpress.org/export/1.2/", "content": "http://purl.org/rss/1.0/modules/content/"}


def main() -> None:
    if not WXR.is_file():
        print("No WXR at", WXR, file=sys.stderr)
        sys.exit(1)
    css = ""
    home_markup = ""
    for _ev, item in ET.iterparse(WXR, events=("end",)):
        if not item.tag.endswith("item"):
            continue
        pt = item.find("wp:post_type", NS)
        if pt is None:
            item.clear()
            continue
        if pt.text == "custom_css":
            slug = item.find("wp:post_name", NS)
            if slug is not None and slug.text == "blockmagchild":
                c = item.find("content:encoded", NS)
                css = (c.text or "") if c is not None else ""
        if pt.text == "page":
            slug = item.find("wp:post_name", NS)
            if slug is not None and slug.text in ("homepage", "stories-of-support"):
                c = item.find("content:encoded", NS)
                body = (c.text or "") if c is not None else ""
                if "homepage-banner" in body or "Impact_Ambient" in body:
                    home_markup = body
        item.clear()

    print("=== Hero video markup (excerpt) ===")
    if home_markup:
        m = re.search(r"<video[^>]+>", home_markup)
        print(m.group(0) if m else home_markup[:400])
    print("\n=== CSS keywords (blockmagchild) ===")
    for kw in ("transition", "sticky", "hover", "animation", "homepage-banner", "scrolled", "mega-menu"):
        lines = [ln.strip() for ln in css.splitlines() if kw in ln.lower()][:5]
        if lines:
            print(f"\n-- {kw} --")
            for ln in lines:
                print(ln[:140])


if __name__ == "__main__":
    main()

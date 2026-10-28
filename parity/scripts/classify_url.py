#!/usr/bin/env python3
"""URL → Drupal template heuristics for danafarberimpact.org (WordPress source)."""

from __future__ import annotations

import json
import re
from dataclasses import dataclass, field
from typing import Callable
from urllib.parse import parse_qs, urlparse

TEMPLATE_HOMEPAGE = "homepage"
TEMPLATE_ISSUE = "issue"
TEMPLATE_ARTICLE = "article"
TEMPLATE_IN_BRIEF = "in_brief"
TEMPLATE_LANDING = "landing_page"
TEMPLATE_ARCHIVE = "archive"
TEMPLATE_TOPIC = "topic"
TEMPLATE_SEARCH = "search"
TEMPLATE_MEDIA = "media"
TEMPLATE_DOCUMENT = "document"
TEMPLATE_OTHER = "other"

ALL_TEMPLATES: tuple[str, ...] = (
    TEMPLATE_HOMEPAGE,
    TEMPLATE_ISSUE,
    TEMPLATE_ARTICLE,
    TEMPLATE_IN_BRIEF,
    TEMPLATE_LANDING,
    TEMPLATE_ARCHIVE,
    TEMPLATE_TOPIC,
    TEMPLATE_SEARCH,
    TEMPLATE_MEDIA,
    TEMPLATE_DOCUMENT,
    TEMPLATE_OTHER,
)

DOCUMENT_EXT_RE = re.compile(
    r"\.(pdf|doc|docx|xls|xlsx|ppt|pptx|csv|zip|rar|7z|txt|rtf)(?:\?|#|$)",
    re.I,
)
MEDIA_EXT_RE = re.compile(
    r"\.(jpe?g|png|gif|webp|svg|avif|ico|bmp|tiff?|mp4|webm|mov|m4v|mp3|wav|ogg|m4a)(?:\?|#|$)",
    re.I,
)

ISSUE_SEASON_ISSUE_RE = re.compile(
    r"^(?:/)?(spring|summer|fall|winter|late-fall)-\d{4}-issue/?$",
    re.I,
)
ISSUE_UNDER_ISSUES_RE = re.compile(r"/issues/", re.I)
STORIES_RE = re.compile(r"/stories/", re.I)
IN_BRIEF_RE = re.compile(r"/in-brief/", re.I)
HOMEPAGE_SEGMENT_RE = re.compile(r"/homepage(?:/|$)", re.I)
ARCHIVE_RE = re.compile(
    r"/(?:category|tag|topics?|author|archive|page/\d+)(?:/|$)|"
    r"^/\d{4}/(?:\d{2}/)?(?:page/\d+)?/?$",
    re.I,
)
TOPIC_RE = re.compile(r"/topics?/", re.I)
DATE_POST_RE = re.compile(r"^/\d{4}/\d{2}/[^/]+/?$")
FEED_RE = re.compile(r"/(?:feed|rss|atom)(?:/|$|\?)", re.I)
WP_UPLOADS_RE = re.compile(r"/wp-content/uploads/", re.I)


@dataclass
class WpRestHints:
    """Path (no query) → WordPress REST type slug."""

    by_path: dict[str, str] = field(default_factory=dict)
    in_brief_paths: set[str] = field(default_factory=set)

    def hint_for_path(self, path: str) -> str | None:
        key = _normalize_path_key(path)
        return self.by_path.get(key)

    def is_in_brief(self, path: str) -> bool:
        return _normalize_path_key(path) in self.in_brief_paths


def _normalize_path_key(path: str) -> str:
    p = path.split("?")[0].split("#")[0].strip()
    if not p.startswith("/"):
        p = "/" + p
    if len(p) > 1 and p.endswith("/"):
        p = p.rstrip("/")
    return p or "/"


def classify_path(
    path: str,
    *,
    query: str = "",
    wp_hints: WpRestHints | None = None,
) -> str:
    """Classify a site path into a manifest template type."""
    path_key = _normalize_path_key(path)
    lower = path_key.lower()

    if query:
        qs = parse_qs(query.lstrip("?"))
        if qs.get("s") or qs.get("search"):
            return TEMPLATE_SEARCH

    if path_key in ("/", ""):
        return TEMPLATE_HOMEPAGE

    if "/search" in lower or lower.endswith("/search"):
        return TEMPLATE_SEARCH

    if DOCUMENT_EXT_RE.search(lower):
        return TEMPLATE_DOCUMENT

    if MEDIA_EXT_RE.search(lower) or (
        WP_UPLOADS_RE.search(lower) and not lower.endswith("/")
    ):
        return TEMPLATE_MEDIA

    if wp_hints:
        if wp_hints.is_in_brief(path_key):
            return TEMPLATE_IN_BRIEF
        wp_type = wp_hints.hint_for_path(path_key)
        if wp_type == "attachment":
            if DOCUMENT_EXT_RE.search(lower):
                return TEMPLATE_DOCUMENT
            return TEMPLATE_MEDIA
        if wp_type == "post":
            return TEMPLATE_ARTICLE
        if wp_type == "page":
            if ISSUE_SEASON_ISSUE_RE.match(path_key.lstrip("/")) or "issue" in lower:
                return TEMPLATE_ISSUE
            return TEMPLATE_LANDING

    if IN_BRIEF_RE.search(path_key):
        return TEMPLATE_IN_BRIEF
    if STORIES_RE.search(path_key):
        return TEMPLATE_ARTICLE
    if ISSUE_UNDER_ISSUES_RE.search(path_key) or ISSUE_SEASON_ISSUE_RE.match(
        path_key.lstrip("/")
    ):
        return TEMPLATE_ISSUE
    if HOMEPAGE_SEGMENT_RE.search(path_key):
        return TEMPLATE_LANDING
    if TOPIC_RE.search(path_key):
        return TEMPLATE_TOPIC
    if ARCHIVE_RE.search(path_key) or FEED_RE.search(path_key):
        return TEMPLATE_ARCHIVE
    if DATE_POST_RE.match(path_key):
        return TEMPLATE_ARTICLE

    # Common static landing slugs (about, contact, etc.)
    if re.match(r"^/(about|contact|privacy|terms|donate|subscribe)(?:/|$)", lower):
        return TEMPLATE_LANDING

    return TEMPLATE_OTHER


def classify_url(url: str, *, wp_hints: WpRestHints | None = None) -> str:
    parsed = urlparse(url)
    return classify_path(parsed.path or "/", query=parsed.query, wp_hints=wp_hints)


def empty_template_counts() -> dict[str, int]:
    return {t: 0 for t in ALL_TEMPLATES}


def bump_counts(counts: dict[str, int], template: str) -> None:
    key = template if template in counts else TEMPLATE_OTHER
    counts[key] = counts.get(key, 0) + 1


def _link_to_path(link: str, base_netloc: str) -> str | None:
    parsed = urlparse(link)
    if parsed.scheme and parsed.scheme not in ("http", "https"):
        return None
    if parsed.netloc and parsed.netloc.lower().replace("www.", "") != base_netloc.lower().replace(
        "www.", ""
    ):
        return None
    path = parsed.path or "/"
    return _normalize_path_key(path)


def load_wp_rest_hints(
    fetch_json: Callable[[str], bytes | None],
    base_url: str,
) -> WpRestHints:
    """
    Paginate WordPress REST collections when reachable.
    fetch_json returns body bytes or None on failure.
    """
    hints = WpRestHints()
    base = base_url.rstrip("/")
    endpoints = (
        ("pages", "page"),
        ("posts", "post"),
        ("media", "attachment"),
    )
    in_brief_tag_names: set[str] = set()

    probe = fetch_json(f"{base}/wp-json/wp/v2/types")
    if probe is None:
        return hints
    try:
        json.loads(probe.decode("utf-8"))
    except (json.JSONDecodeError, UnicodeDecodeError):
        return hints

    tags_raw = fetch_json(f"{base}/wp-json/wp/v2/tags?per_page=100")
    if tags_raw is None:
        return hints
    if tags_raw:
        try:
            tags = json.loads(tags_raw.decode("utf-8"))
            for tag in tags:
                name = (tag.get("name") or "").strip()
                if name.lower().startswith("in brief"):
                    slug = tag.get("slug") or ""
                    if slug:
                        in_brief_tag_names.add(slug)
        except (json.JSONDecodeError, UnicodeDecodeError):
            pass

    for rest_name, wp_type in endpoints:
        page = 1
        while page <= 30:
            url = f"{base}/wp-json/wp/v2/{rest_name}?per_page=100&page={page}"
            raw = fetch_json(url)
            if not raw:
                break
            try:
                items = json.loads(raw.decode("utf-8"))
            except (json.JSONDecodeError, UnicodeDecodeError):
                break
            if not isinstance(items, list) or not items:
                break
            for item in items:
                link = item.get("link") or ""
                path = _link_to_path(link, urlparse(base).netloc)
                if not path:
                    continue
                hints.by_path[path] = wp_type
                if rest_name == "posts" and in_brief_tag_names:
                    # WP embeds tag IDs; slug list used when classifying posts via tags endpoint only.
                    pass
            if len(items) < 100:
                break
            page += 1

    # Mark in-brief posts via tag names on each post (second pass, capped).
    page = 1
    while page <= 10:
        url = f"{base}/wp-json/wp/v2/posts?per_page=100&page={page}&_embed=wp:term"
        raw = fetch_json(url)
        if not raw:
            break
        try:
            posts = json.loads(raw.decode("utf-8"))
        except (json.JSONDecodeError, UnicodeDecodeError):
            break
        if not isinstance(posts, list) or not posts:
            break
        for post in posts:
            link = post.get("link") or ""
            path = _link_to_path(link, urlparse(base).netloc)
            if not path:
                continue
            embedded = post.get("_embedded") or {}
            terms = embedded.get("wp:term") or []
            for group in terms:
                if not isinstance(group, list):
                    continue
                for term in group:
                    if term.get("taxonomy") != "post_tag":
                        continue
                    name = (term.get("name") or "").lower()
                    if name.startswith("in brief"):
                        hints.in_brief_paths.add(path)
        if len(posts) < 100:
            break
        page += 1

    return hints

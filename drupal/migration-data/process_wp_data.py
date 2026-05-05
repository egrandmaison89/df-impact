#!/usr/bin/env python3
"""
Process raw WordPress REST API data into clean migration source files.
Produces:
  - topics.json       (Drupal taxonomy terms - Topics vocab)
  - cancer_types.json (Drupal taxonomy terms - Cancer Types vocab)
  - issues.json       (Issue nodes with season/year)
  - articles.json     (Article nodes)
  - in_brief.json     (In Brief nodes)
  - media.json        (Media entities with source URLs)
  - redirects.json    (URL redirect mapping: old WP slug -> new Drupal path)
"""

import json
import re
import os
import html as html_lib

# ─── CATEGORY → TOPIC/CANCER TYPE MAPPING ────────────────────────────────────
# Determine which WP categories map to Topics vs Cancer Types vocabularies

# Cancer type categories (map to Cancer Types vocabulary in Drupal)
CANCER_TYPE_CATS = {
    90: 'Breast Cancer',
    91: 'Lung Cancer',
    92: 'Prostate Cancer',
    19: 'Pediatrics',  # Pediatric cancers
}

# Administrative/curation categories (not imported as taxonomy terms)
SKIP_CATS = {1, 4}  # Uncategorized, Digital Exclusives (handled as placement field)

# Issue tag patterns (season + year combos)
ISSUE_TAG_PATTERN = re.compile(
    r'^(Spring|Summer|Fall|Winter|Late Fall)\s+(\d{4})$', re.IGNORECASE
)

# In Brief tag patterns
IN_BRIEF_TAG_PATTERN = re.compile(
    r'^In Brief\s+(Spring|Summer|Fall|Winter|Late Fall)\s+(\d{4})$', re.IGNORECASE
)

# Homepage placement tags
HOMEPAGE_FEATURED_TAG_IDS = {8}    # "Homepage Featured"
HOMEPAGE_HIGHLIGHTS_TAG_IDS = {37} # "Homepage Recent Highlights"
HOMEPAGE_DE_TAG_IDS = {36}         # "Homepage DE"
DIGITAL_EXCLUSIVES_TAG_IDS = {5}   # "Digital Exclusives" tag

# ─── LOAD RAW DATA ───────────────────────────────────────────────────────────

with open('categories.json') as f:
    categories = json.load(f)

with open('tags.json') as f:
    tags = json.load(f)

# Load all post pages
posts = []
for page in range(1, 8):
    fname = f'posts_page_{page}_raw.json'
    if os.path.exists(fname):
        with open(fname) as f:
            posts.extend(json.load(f))

print(f"Loaded {len(posts)} posts")

# Load pages
with open('pages_raw.json') as f:
    wp_pages = json.load(f)

print(f"Loaded {len(wp_pages)} pages")

# Load all media
media_items = []
for page in range(1, 10):
    fname = f'media_page_{page}_raw.json'
    if os.path.exists(fname):
        with open(fname) as f:
            media_items.extend(json.load(f))

print(f"Loaded {len(media_items)} media items")

# ─── BUILD TAG & CATEGORY LOOKUPS ────────────────────────────────────────────

tags_by_id = {t['id']: t for t in tags}
cats_by_id = {c['id']: c for c in categories}

# Identify issue tags
issue_tags = {}  # tag_id -> {'season': 'Spring', 'year': 2026, 'name': 'Spring 2026'}
in_brief_tags = {}  # tag_id -> {'season': 'Spring', 'year': 2026, 'name': 'Spring 2026'}

for tag in tags:
    m = ISSUE_TAG_PATTERN.match(tag['name'])
    if m:
        issue_tags[tag['id']] = {
            'name': tag['name'],
            'slug': tag['slug'],
            'season': m.group(1).lower(),
            'year': int(m.group(2)),
            'count': tag['count']
        }
    m2 = IN_BRIEF_TAG_PATTERN.match(tag['name'])
    if m2:
        in_brief_tags[tag['id']] = {
            'name': tag['name'],
            'slug': tag['slug'],
            'issue_season': m2.group(1).lower(),
            'issue_year': int(m2.group(2)),
            'count': tag['count']
        }

print(f"Issue tags: {len(issue_tags)}")
print(f"In Brief tags: {len(in_brief_tags)}")
for tid, info in sorted(issue_tags.items(), key=lambda x: (x[1]['year'], x[1]['season'])):
    print(f"  [{tid}] {info['name']} ({info['count']} posts)")

# ─── TOPICS (Drupal taxonomy) ────────────────────────────────────────────────
# Map WP categories to Drupal Topics vocabulary with hierarchy

TOPIC_PARENT_MAP = {
    # Parent: Research
    10: {'parent': 'Research', 'name': 'Cancer Research'},
    14: {'parent': 'Research', 'name': 'Discovery Science'},
    13: {'parent': 'Research', 'name': 'Drug Development'},
    18: {'parent': 'Research', 'name': 'Immunotherapy'},
    62: {'parent': 'Research', 'name': 'AI and Machine Learning'},
    61: {'parent': 'Research', 'name': 'Epigenetics'},
    17: {'parent': 'Research', 'name': 'Prevention and Early Detection'},
    69: {'parent': 'Research', 'name': 'Basic Science'},
    63: {'parent': 'Research', 'name': 'Rare and Resistant Cancers'},

    # Parent: Patient Care
    11: {'parent': 'Patient Care', 'name': 'Total Patient Care'},
    15: {'parent': 'Patient Care', 'name': 'Access and Equity'},
    60: {'parent': 'Patient Care', 'name': 'Exceptional Expertise'},
    59: {'parent': 'Patient Care', 'name': 'Essential Opportunities'},

    # Parent: Philanthropy
    9:  {'parent': 'Philanthropy', 'name': 'Grassroots Support'},
    16: {'parent': 'Philanthropy', 'name': 'Planned Giving'},
    12: {'parent': 'Philanthropy', 'name': 'Recognition'},
    55: {'parent': 'Philanthropy', 'name': 'The Dana-Farber Campaign'},

    # Top-level / other
    40: {'parent': None, 'name': 'From Melany Duval'},
}

topics_output = []
# Add parent terms
for parent_name in ['Research', 'Patient Care', 'Philanthropy']:
    topics_output.append({
        'wp_id': None,
        'name': parent_name,
        'slug': parent_name.lower().replace(' ', '-'),
        'parent_name': None,
        'is_parent': True,
    })

# Add child terms
for wp_cat_id, info in TOPIC_PARENT_MAP.items():
    cat = cats_by_id.get(wp_cat_id)
    if cat:
        topics_output.append({
            'wp_id': wp_cat_id,
            'name': info['name'],
            'slug': cat['slug'],
            'parent_name': info['parent'],
            'is_parent': False,
            'wp_count': cat['count'],
        })

with open('topics.json', 'w') as f:
    json.dump(topics_output, f, indent=2)
print(f"\nTopics: {len(topics_output)} terms")

# ─── CANCER TYPES (Drupal taxonomy) ──────────────────────────────────────────

cancer_types_output = []
for wp_cat_id, name in CANCER_TYPE_CATS.items():
    cat = cats_by_id.get(wp_cat_id)
    if cat:
        cancer_types_output.append({
            'wp_id': wp_cat_id,
            'name': name,
            'slug': cat['slug'],
            'wp_count': cat['count'],
        })

# Also check if there are cancer-type WP tags we missed
# Add common cancer types not in WP cats
extra_cancer_types = [
    {'wp_id': None, 'name': 'Colorectal Cancer', 'slug': 'colorectal-cancer', 'wp_count': 0},
    {'wp_id': None, 'name': 'Leukemia', 'slug': 'leukemia', 'wp_count': 0},
    {'wp_id': None, 'name': 'Lymphoma', 'slug': 'lymphoma', 'wp_count': 0},
    {'wp_id': None, 'name': 'Ovarian Cancer', 'slug': 'ovarian-cancer', 'wp_count': 0},
    {'wp_id': None, 'name': 'Multiple Myeloma', 'slug': 'multiple-myeloma', 'wp_count': 0},
    {'wp_id': None, 'name': 'Skin Cancer', 'slug': 'skin-cancer', 'wp_count': 0},
]
cancer_types_output.extend(extra_cancer_types)

with open('cancer_types.json', 'w') as f:
    json.dump(cancer_types_output, f, indent=2)
print(f"Cancer types: {len(cancer_types_output)} terms")

# ─── ISSUES (Drupal nodes) ───────────────────────────────────────────────────

issues_output = []
for tag_id, info in sorted(issue_tags.items(), key=lambda x: (x[1]['year'], x[1]['season'])):
    if info['count'] == 0:
        continue  # Skip empty issues

    season = info['season']
    year = info['year']

    # Generate Drupal path alias
    path_alias = f"/issues/{season}-{year}"

    issues_output.append({
        'wp_tag_id': tag_id,
        'name': info['name'],
        'slug': info['slug'],
        'season': season,
        'year': year,
        'path_alias': path_alias,
        'article_count': info['count'],
    })

with open('issues.json', 'w') as f:
    json.dump(issues_output, f, indent=2)
print(f"Issues: {len(issues_output)}")

# ─── MEDIA (Drupal media entities) ───────────────────────────────────────────

media_output = []
media_by_id = {}
for item in media_items:
    if not item.get('source_url'):
        continue

    media_data = {
        'wp_id': item['id'],
        'source_url': item['source_url'],
        'title': item.get('title', {}).get('rendered', ''),
        'alt_text': item.get('alt_text', ''),
        'caption': item.get('caption', {}).get('rendered', ''),
        'mime_type': item.get('mime_type', ''),
        'width': item.get('media_details', {}).get('width', 0),
        'height': item.get('media_details', {}).get('height', 0),
        'filename': os.path.basename(item['source_url']),
    }
    media_output.append(media_data)
    media_by_id[item['id']] = media_data

with open('media.json', 'w') as f:
    json.dump(media_output, f, indent=2)
print(f"Media: {len(media_output)}")

# ─── HELPER: Extract byline and photo credit from content ────────────────────

def extract_byline(content):
    """Extract 'By [Name]' from article body HTML."""
    if not content:
        return ''
    # Common patterns:
    # <p>By John Smith</p>  or  By Jane Doe  or  Written by [name]
    patterns = [
        r'<p[^>]*>\s*By\s+([A-Z][a-zA-Z\s,\.]+?)\s*</p>',
        r'<em>\s*By\s+([A-Z][a-zA-Z\s,\.]+?)\s*</em>',
        r'<strong>\s*By\s+([A-Z][a-zA-Z\s,\.]+?)\s*</strong>',
    ]
    for pattern in patterns:
        m = re.search(pattern, content)
        if m:
            return f"By {m.group(1).strip()}"
    return ''

def extract_photo_credit(content):
    """Extract 'Photography by [Name]' or 'Photo by [Name]' from body."""
    if not content:
        return ''
    patterns = [
        r'<p[^>]*>\s*Photograph[y|s]?\s+by\s+([A-Z][a-zA-Z\s,\.]+?)\s*</p>',
        r'<p[^>]*>\s*Photos?\s+by\s+([A-Z][a-zA-Z\s,\.]+?)\s*</p>',
        r'<p[^>]*>\s*Images?\s+by\s+([A-Z][a-zA-Z\s,\.]+?)\s*</p>',
        r'Photography by\s+([A-Z][a-zA-Z\s,\.]+?)(?:<|\s*$)',
        r'Photos? by\s+([A-Z][a-zA-Z\s,\.]+?)(?:<|\s*$)',
    ]
    for pattern in patterns:
        m = re.search(pattern, content, re.IGNORECASE)
        if m:
            return f"Photography by {m.group(1).strip()}"
    return ''

def normalize_wp_lazy_images(content):
    """Fix WordPress lazy-load img markup for hosts that do not run lazy-load JS.

    WP used src=transparent GIF + data-src=real URL. Promote data-src to src and
    strip lazy-load attributes so HTML is sane before/without Drupal migration.
    """
    if not content:
        return content

    def fix_img_tag(match):
        tag = match.group(0)
        ds = re.search(
            r'data-src\s*=\s*["\']([^"\']+)["\']',
            tag,
            re.IGNORECASE,
        )
        if not ds:
            return tag
        real_url = html_lib.unescape(ds.group(1).strip())
        if 'danafarberimpact.org' not in real_url:
            return tag
        tag = re.sub(
            r'\ssrc\s*=\s*["\'][^"\']*["\']',
            f' src="{real_url}"',
            tag,
            count=1,
            flags=re.IGNORECASE,
        )
        tag = re.sub(r'\sdata-src\s*=\s*["\'][^"\']*["\']', '', tag, flags=re.IGNORECASE)
        tag = re.sub(r'\sdata-srcset\s*=\s*["\'][^"\']*["\']', '', tag, flags=re.IGNORECASE)
        tag = re.sub(r'\sdata-sizes\s*=\s*["\'][^"\']*["\']', '', tag, flags=re.IGNORECASE)
        cls_m = re.search(r'\sclass\s*=\s*["\']([^"\']*)["\']', tag, re.IGNORECASE)
        if cls_m:
            new_cls = ' '.join(c for c in cls_m.group(1).split() if c != 'lazyload')
            if new_cls:
                tag = re.sub(
                    r'\sclass\s*=\s*["\'][^"\']*["\']',
                    f' class="{new_cls}"',
                    tag,
                    count=1,
                    flags=re.IGNORECASE,
                )
            else:
                tag = re.sub(
                    r'\sclass\s*=\s*["\'][^"\']*["\']',
                    '',
                    tag,
                    count=1,
                    flags=re.IGNORECASE,
                )
        return tag

    return re.sub(r'<img\b[^>]+>', fix_img_tag, content, flags=re.IGNORECASE)


def clean_body(content, byline, photo_credit):
    """Remove extracted byline/photo credit paragraphs from body HTML."""
    if not content:
        return content
    # Remove byline paragraphs
    content = re.sub(r'<p[^>]*>\s*By\s+[A-Z][a-zA-Z\s,\.]+?\s*</p>', '', content)
    content = re.sub(r'<p[^>]*>\s*Photograph[y|s]?\s+by\s+[A-Z][a-zA-Z\s,\.]+?\s*</p>', '', content, flags=re.IGNORECASE)
    content = re.sub(r'<p[^>]*>\s*Photos?\s+by\s+[A-Z][a-zA-Z\s,\.]+?\s*</p>', '', content, flags=re.IGNORECASE)
    # Clean up leading/trailing whitespace
    content = content.strip()
    content = normalize_wp_lazy_images(content)
    return content

def get_excerpt(post):
    """Get excerpt, falling back to truncated content."""
    excerpt = post.get('excerpt', {}).get('rendered', '').strip()
    # Strip HTML from excerpt
    excerpt = re.sub(r'<[^>]+>', '', excerpt).strip()
    excerpt = re.sub(r'\s+', ' ', excerpt)
    # Remove WP "Continue reading" links
    excerpt = re.sub(r'Continue reading.*$', '', excerpt).strip()
    return excerpt[:500] if excerpt else ''

def get_featured_image_url(post):
    """Get featured image URL from embedded data."""
    try:
        embedded = post.get('_embedded', {})
        featured = embedded.get('wp:featuredmedia', [{}])[0]
        return featured.get('source_url', '')
    except (IndexError, KeyError, TypeError):
        return ''

def get_featured_image_id(post):
    """Get featured image WP media ID."""
    return post.get('featured_media', 0)

def get_yoast_meta(post):
    """Extract Yoast SEO meta description."""
    try:
        yoast = post.get('yoast_head_json', {})
        if isinstance(yoast, dict):
            return yoast.get('description', '')
    except (AttributeError, KeyError):
        pass
    return ''

# ─── ARTICLES (Drupal nodes) ─────────────────────────────────────────────────

# Build issue tag -> issue name lookup
issue_tag_id_to_name = {tid: info['name'] for tid, info in issue_tags.items()}
in_brief_tag_id_set = set(in_brief_tags.keys())

# Build set of all cat IDs that are topics
topic_cat_ids = set(TOPIC_PARENT_MAP.keys())
cancer_type_cat_ids = set(CANCER_TYPE_CATS.keys())

articles_output = []
in_brief_output = []

# Determine which posts are "In Brief" based on tags
for post in posts:
    tag_ids = post.get('tags', [])
    cat_ids = post.get('categories', [])

    # Check if this is an "In Brief" post
    in_brief_tag_matches = [tid for tid in tag_ids if tid in in_brief_tag_id_set]
    is_in_brief = len(in_brief_tag_matches) > 0

    # Get issue association
    issue_tag_matches = [tid for tid in tag_ids if tid in issue_tag_id_to_name]
    issue_name = issue_tag_id_to_name[issue_tag_matches[0]] if issue_tag_matches else ''

    # Get topic categories
    topic_cats = [cats_by_id[cid]['name'] for cid in cat_ids
                  if cid in topic_cat_ids and cid in cats_by_id]

    # Get cancer type categories
    cancer_type_cats = [CANCER_TYPE_CATS[cid] for cid in cat_ids
                        if cid in cancer_type_cat_ids]

    # Homepage placement (Featured / Recent Highlights only; Digital Exclusives → Channels).
    placement = 'none'
    if any(tid in HOMEPAGE_FEATURED_TAG_IDS for tid in tag_ids):
        placement = 'featured'
    elif any(tid in HOMEPAGE_HIGHLIGHTS_TAG_IDS for tid in tag_ids):
        placement = 'recent_highlights'

    # Is digital exclusive (WordPress category)?
    is_digital_exclusive = (4 in cat_ids)  # WP cat "Digital Exclusives"
    content_type_value = 'digital_exclusive' if is_digital_exclusive else 'print'

    # Drupal Channels taxonomy (mirror WP category + legacy DE homepage tags).
    channels = []
    if is_digital_exclusive or any(tid in HOMEPAGE_DE_TAG_IDS for tid in tag_ids) \
            or any(tid in DIGITAL_EXCLUSIVES_TAG_IDS for tid in tag_ids):
        channels = ['Digital Exclusives']

    # Get content
    title = post.get('title', {}).get('rendered', '').strip()
    content = post.get('content', {}).get('rendered', '')

    byline = extract_byline(content)
    photo_credit = extract_photo_credit(content)
    clean_content = clean_body(content, byline, photo_credit)
    excerpt = get_excerpt(post)

    # Get WP metadata
    wp_slug = post.get('slug', '')
    wp_url = post.get('link', '')
    wp_status = post.get('status', 'publish')
    wp_date = post.get('date', '')
    wp_modified = post.get('modified', '')

    # Featured image
    featured_image_url = get_featured_image_url(post)
    featured_image_id = get_featured_image_id(post)

    # SEO
    meta_description = get_yoast_meta(post)

    # Drupal path alias (canonical article URLs use /stories/ per Pathauto pattern)
    drupal_alias = f"/stories/{wp_slug}" if not is_in_brief else f"/in-brief/{wp_slug}"

    base_data = {
        'wp_id': post['id'],
        'title': title,
        'slug': wp_slug,
        'wp_url': wp_url,
        'drupal_alias': drupal_alias,
        'status': 'published' if wp_status == 'publish' else 'draft',
        'created': wp_date,
        'changed': wp_modified,
        'byline': byline,
        'photo_credit': photo_credit,
        'body': clean_content,
        'excerpt': excerpt,
        'featured_image_url': featured_image_url,
        'featured_image_wp_id': featured_image_id,
        'issue_name': issue_name,
        'topics': topic_cats,
        'cancer_types': cancer_type_cats,
        'meta_description': meta_description,
    }

    if is_in_brief:
        # For In Brief: use the linked tag to find the issue
        in_brief_issue = ''
        for tid in in_brief_tag_matches:
            ib = in_brief_tags[tid]
            in_brief_issue = f"{ib['issue_season'].capitalize()} {ib['issue_year']}"
            break

        # Try to get external link from content (In Brief items often have source links)
        ext_link = ''
        link_match = re.search(r'href=["\']([^"\']+)["\'][^>]*>(?:Read More|Learn More|Source|Full Story)',
                               content, re.IGNORECASE)
        if link_match:
            ext_link = link_match.group(1)

        in_brief_data = {**base_data,
                         'issue_name': in_brief_issue or issue_name,
                         'link': ext_link}
        in_brief_output.append(in_brief_data)
    else:
        article_data = {**base_data,
                        'homepage_placement': placement,
                        'channels': channels,
                        'content_type': content_type_value,
                        'is_digital_exclusive': is_digital_exclusive}
        articles_output.append(article_data)

with open('articles.json', 'w') as f:
    json.dump(articles_output, f, indent=2)

with open('in_brief.json', 'w') as f:
    json.dump(in_brief_output, f, indent=2)

print(f"\nArticles: {len(articles_output)}")
print(f"In Brief: {len(in_brief_output)}")

# ─── REDIRECTS ───────────────────────────────────────────────────────────────

redirects_output = []

# WP uses /?p=ID or /slug/ URL structure -> Drupal uses /stories/slug or /in-brief/slug
in_brief_slugs = {item['slug'] for item in in_brief_output}

for post in posts:
    wp_slug = post.get('slug', '')
    wp_url_path = '/' + wp_slug + '/'
    wp_url = post.get('link', '')

    if wp_slug in in_brief_slugs:
        drupal_path = f"/in-brief/{wp_slug}"
    else:
        drupal_path = f"/stories/{wp_slug}"

    redirects_output.append({
        'wp_id': post['id'],
        'source': wp_url_path,
        'redirect_to': drupal_path,
        'status_code': 301,
    })

with open('redirects.json', 'w') as f:
    json.dump(redirects_output, f, indent=2)

print(f"Redirects: {len(redirects_output)}")

# ─── SUMMARY ─────────────────────────────────────────────────────────────────

print("\n" + "="*50)
print("MIGRATION DATA SUMMARY")
print("="*50)
print(f"Topics:           {len(topics_output)} terms")
print(f"Cancer Types:     {len(cancer_types_output)} terms")
print(f"Issues:           {len(issues_output)}")
print(f"Articles:         {len(articles_output)}")
print(f"In Brief items:   {len(in_brief_output)}")
print(f"Media:            {len(media_output)}")
print(f"Redirects:        {len(redirects_output)}")
print()

# Sample: First article
if articles_output:
    a = articles_output[0]
    print("Sample article:")
    print(f"  Title: {a['title'][:60]}")
    print(f"  Slug: {a['slug'][:50]}")
    print(f"  Issue: {a['issue_name']}")
    print(f"  Topics: {a['topics']}")
    print(f"  Byline: {a['byline']}")
    print(f"  Photo credit: {a['photo_credit']}")
    print(f"  Image URL: {a['featured_image_url'][:60] if a['featured_image_url'] else 'none'}")
    print(f"  Placement: {a['homepage_placement']}")

print("\nDone! Output files ready in migration-data/")

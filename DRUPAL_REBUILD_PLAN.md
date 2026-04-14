# Dana-Farber Impact Magazine — Drupal Rebuild Plan

**Site:** https://danafarberimpact.org/
**Current Platform:** WordPress (Jetpack, Yoast SEO, Ultimate Post Grid, Max Mega Menu)
**Target Platform:** Drupal 10/11
**Date:** 2026-03-31

---

## TABLE OF CONTENTS

1. [Phase 1: Deep Analysis of Existing Website](#phase-1-deep-analysis-of-existing-website)
2. [Phase 2: Drupal Architecture Design](#phase-2-drupal-architecture-design)
3. [Phase 3: Implementation Plan](#phase-3-implementation-plan)
4. [Phase 4: Testing & Validation Strategy](#phase-4-testing--validation-strategy)
5. [Phase 5: Persistent Project Memory System](#phase-5-persistent-project-memory-system)

---

# PHASE 1: DEEP ANALYSIS OF EXISTING WEBSITE

## 1.1 Primary Purpose

Dana-Farber Impact Magazine is the **official philanthropic storytelling publication** of Dana-Farber Cancer Institute and The Jimmy Fund. Its purpose is to:

- Showcase how donor generosity funds cancer research and patient care
- Recognize individual, corporate, and foundation donors
- Highlight research breakthroughs enabled by philanthropy
- Drive ongoing fundraising by demonstrating impact
- Serve as a quarterly digital magazine with web-exclusive content between editions

## 1.2 User Journeys

### Reader (Primary)
1. Arrives via email newsletter, social media, or direct URL
2. Lands on homepage → sees latest featured stories and recent highlights
3. Clicks into an article → reads the full story
4. Discovers related content via "More stories" section
5. May browse the current quarterly issue or explore past issues

### Donor / Prospective Donor
1. Receives print or digital copy of Impact magazine
2. Visits the website to read additional stories or share with others
3. May click the "Donate" button in the sticky header → redirected to Jimmy Fund donation page
4. Browses donor recognition stories for social proof and inspiration

### Editor / Content Creator
1. Logs into WordPress admin
2. Creates a new post using the standard WordPress editor
3. Assigns categories (Cancer Research, Grassroots Support, etc.) and tags (season + year)
4. Uploads featured image
5. Associates the article with a quarterly issue via tags (e.g., "Spring 2026")
6. Publishes or schedules the article

### Institutional Stakeholder
1. Uses the site to share impact stories with board members, leadership, or partners
2. References specific articles or issues for fundraising presentations
3. Uses "About Impact" page for publication credibility

## 1.3 Navigation Structure

### Primary Header Navigation
- **Logo** (Dana-Farber Impact Magazine) — links to homepage
- **Issues** — dropdown listing recent quarterly issues (Spring 2026, Winter 2025, etc.) + "See All Issues"
- **Search** — integrated search functionality
- **Language Selector** — Google Translate widget
- **Donate Button** — persistent CTA linking to Jimmy Fund donation page with UTM tracking

### Footer
- Dana-Farber Cancer Institute logo
- Jimmy Fund logo
- Category archive links (Cancer Research, Total Patient Care, etc.)
- Copyright notice
- Contact information

### Breadcrumbs
- Schema: Home → Stories Archive → [Article Title]
- On issue pages: Home → [Season Year]

## 1.4 Content Presentation Patterns

### Homepage
- **Hero Banner:** Full-width collage image (2560×981px) with blue-toned staff/patient imagery
- **Featured Articles Grid:** Large featured card + supporting smaller cards (3-column desktop → 1-column mobile)
- **Recent Highlights:** 3-column card grid with gray (#f6f6f6) background cards
- **Secondary Content Grid:** 4-column layout with consistent card styling
- **Visual Divider:** Blue-orange-cyan gradient bar separating sections

### Quarterly Issue Page
- **Issue Banner:** Full-width seasonal cover image (1750×677px)
- **Article Grid:** 2-column layout of article cards with featured images, titles, and excerpts
- **Pagination:** Numbered page buttons with prev/next arrows
- **Dark Background:** #0f0e17 with white text for issue pages

### Article Page
- **Featured Image:** Large hero image (1450×1040px) with caption
- **Headline:** Libre Baskerville, 40px, bold
- **Metadata:** Issue reference (e.g., "Spring 2026"), byline ("By [Author Name]"), photo credit
- **Body:** 18px Montserrat, 1.75 line-height, drop-cap on opening paragraph
- **Pull Quotes:** Orange left border (3px solid #ffa300)
- **Categories:** Displayed as clickable tags below content
- **Related Content:** "More stories" section with 3 thumbnail cards
- **Social Sharing:** 5 platform sharing buttons

### In Brief Page
- **List Layout:** Image-text pairs with horizontal separator lines
- **Compact Format:** Smaller font sizes, shorter descriptions
- **Image:** 30% width on desktop, full-width on mobile
- **Pagination:** Load-more or numbered pagination

### Browse Issues Page
- **Grid of Cover Images:** 3-4 issues per row on desktop
- **Season/Year Labels:** Centered below each cover
- **Chronological Order:** Most recent first
- **"See Archives" Link:** Points to Dana-Farber newsroom for older issues

## 1.5 Content Structure Analysis

### Content Types (Inferred)

| Content Type | WordPress Implementation | Volume | Notes |
|---|---|---|---|
| **Article** | Post | 600+ | Primary content type |
| **Issue** | Page | 13 (seasonal) | Quarterly editions — Spring/Summer/Fall/Winter |
| **About Page** | Page | 1 | Static informational |
| **Browse Issues** | Page | 1 | Archive/index page |
| **In Brief** | Page (with post listing) | 1 | Compact news roundup |
| **Homepage** | Page | 1 | Custom layout via Ultimate Post Grid |

### Article Fields (from WP REST API)

| Field | Type | Example |
|---|---|---|
| Title | Text (long) | "A Legacy of Leadership and Hope..." |
| Body | Rich text | Full article HTML with drop caps, pull quotes |
| Featured Image | Image | 1450×1040px, with alt text and caption |
| Excerpt | Text | Auto-generated or manual summary |
| Author/Byline | Text | "By Amber Sinicrope" |
| Photo Credit | Text (in body) | "Photography by Bryce Vickmark" |
| Categories | Taxonomy | Cancer Research, Breast Cancer, etc. |
| Tags | Taxonomy | Spring 2026, 2026, Homepage Featured, etc. |
| Publication Date | Date | 2026-03-19 |
| Sticky | Boolean | Featured post flag |

### Categories (16 identified)

| Category | Slug | Post Count | Purpose |
|---|---|---|---|
| Cancer Research | cancer-research | 437 | Broadest research category |
| Grassroots Support | grassroots-support | 140 | Community fundraising events |
| Pediatrics | pediatrics | 84 | Pediatric cancer stories |
| Immunotherapy | immunotherapy | 68 | Treatment-specific |
| Access and Equity | access-and-equity | 51 | DEI and access stories |
| Discovery Science | discovery-science | 49 | Basic research |
| Exceptional Expertise | exceptional-expertise | 48 | Faculty/staff recognition |
| Basic Science | basic-science | 46 | Lab research |
| Digital Exclusives | digital-exclusives | 44 | Web-only content |
| Drug Development | drug-development | 37 | Therapeutic development |
| Essential Opportunities | essential-opportunities | 25 | Strategic priorities |
| AI and Machine Learning | ai-and-machine-learning | 14 | Technology in cancer care |
| Epigenetics | epigenetics | 12 | Research subspecialty |
| From Melany Duval | from-melany-duval | 9 | Leadership messages |
| Breast Cancer | breast-cancer | 5 | Cancer type |
| Lung Cancer | lung-cancer | 2 | Cancer type |

### Tags (Dual-Purpose System)

Tags serve two functions in the current WordPress implementation:

**1. Issue Association (Season + Year):**
- Spring 2026 (ID: 88), Winter 2025, Fall 2025, Summer 2025...
- Year tags: 2022 (15), 2023 (92), 2024 (122), 2025 (122), 2026 (24)

**2. Homepage Placement Control:**
- `Homepage Featured` (9 posts) — hero/featured section
- `Homepage Recent Highlights` (13 posts) — secondary grid
- `Homepage DE` (3 posts) — Digital Exclusives on homepage

**3. Content Type Tagging:**
- `Digital Exclusives` (47 posts)
- `Campaign Updates` (9 posts)

### Media Usage Patterns
- **Hero/Banner Images:** 2560×981px (homepage), 1750×677px (issue pages)
- **Featured Article Images:** 1450×1040px
- **Thumbnail/Card Images:** 350×200px (related content)
- **In Brief Images:** Variable, constrained to 30% column width
- **Image Optimization:** Jetpack CDN (i0.wp.com) with SSL, responsive srcset
- **Lazy Loading:** GIF placeholder pattern via Jetpack

## 1.6 Editorial Workflow Observations

### Current WordPress Workflow
1. Editor creates a standard WordPress Post
2. Content is written in the block editor with manual formatting (drop caps, pull quotes)
3. Categories assigned from a flat list (no hierarchy)
4. Issue association done via tags — error-prone since tags are free-text
5. Homepage placement controlled via special tags (`Homepage Featured`, etc.)
6. Featured image uploaded and captioned separately
7. Byline and photo credit embedded directly in post body — no structured fields

### Pain Points Identified

1. **No formal Issue/Edition content type** — Issues are standalone Pages with embedded post grid blocks; articles are linked to issues only via tags. This is fragile and relies on tag naming consistency.

2. **Byline/credit not structured** — Author name and photographer are typed into the post body rather than stored in dedicated fields. This prevents author archive pages, filtering by photographer, or consistent formatting.

3. **Homepage curation is tag-based** — Using tags like "Homepage Featured" to control homepage layout is brittle and not intuitive for non-technical editors.

4. **No content preview workflow** — WordPress default editing lacks structured content preview matching the frontend design.

5. **Flat category structure** — All 16 categories are top-level with no hierarchy. Cancer types (Breast Cancer, Lung Cancer) sit alongside broad themes (Cancer Research, Access and Equity). This will scale poorly.

6. **Duplicate taxonomy usage** — "Digital Exclusives" exists as both a category AND a tag, creating confusion.

7. **No editorial calendar view** — No visible mechanism for planning content across issues or managing the quarterly publishing cycle.

## 1.7 Technical Observations

### SEO Structure
- **URL Pattern:** `/[year]/[month]/[slug]/` — date-based, potentially poor for evergreen content
- **Yoast SEO:** Full integration with schema markup, meta descriptions, OG tags
- **Breadcrumbs:** Schema-enabled (Home → Stories Archive → Article)
- **Canonical URLs:** Properly set via Yoast
- **Sitemap:** Auto-generated XML sitemap with 600+ URLs

### Plugins/Tools Detected
- **Yoast SEO** — SEO management
- **Jetpack** — CDN, related posts, sharing, lazy loading, simple payments
- **Ultimate Post Grid (ULTP)** — Custom post grids and layouts
- **Max Mega Menu** — Navigation
- **GTranslate** — Machine translation
- **Stackable** — Block library

### Reusable Components
1. **Article Card** — Image + Title + Excerpt + Category tag (3 variants: large featured, medium, compact)
2. **Issue Cover Card** — Cover image + Season/Year label
3. **Pull Quote Block** — Orange left border, italic text
4. **Hero Banner** — Full-width image with optional overlay text
5. **In Brief Item** — Side-by-side image/text with separator
6. **Gradient Divider** — Blue-orange-cyan decorative bar
7. **"More Stories" Block** — 3-column related articles
8. **Pagination** — Numbered buttons with prev/next

### Performance Considerations
- Heavy reliance on Jetpack CDN for image optimization
- Multiple plugin JavaScript/CSS bundles
- No evidence of aggressive caching strategy
- Large hero images without next-gen format optimization (WebP/AVIF)

---

# PHASE 2: DRUPAL ARCHITECTURE DESIGN

## 2.1 Content Model

### Content Type: Article (machine name: `article`)

The primary content type representing all magazine stories.

| Field | Machine Name | Type | Required | Notes |
|---|---|---|---|---|
| Title | `title` | Text | Yes | Max 255 characters |
| Body | `body` | Text (formatted, long) | Yes | CKEditor 5 with custom styles |
| Featured Image | `field_featured_image` | Entity Reference (Media) | Yes | Image media entity |
| Subtitle | `field_subtitle` | Text (plain) | No | Optional deck/subhead |
| Byline | `field_byline` | Text (plain) | Yes | Author name (e.g., "By Amber Sinicrope") |
| Photo Credit | `field_photo_credit` | Text (plain) | No | "Photography by [Name]" |
| Excerpt | `field_excerpt` | Text (plain, long) | No | Manual summary; auto-trimmed from body if empty |
| Issue | `field_issue` | Entity Reference (Node: Issue) | No | Links article to quarterly issue. Empty = Digital Exclusive |
| Topics | `field_topics` | Entity Reference (Taxonomy: Topics) | Yes | Multi-value; replaces WP categories |
| Cancer Types | `field_cancer_types` | Entity Reference (Taxonomy: Cancer Types) | No | Multi-value; structured cancer type tags |
| Content Type Tag | `field_content_type` | Entity Reference (Taxonomy: Content Type) | No | "Digital Exclusive", "Campaign Update", "Leadership Message", "In Brief" |
| Homepage Placement | `field_homepage_placement` | List (text) | No | Values: "Featured", "Recent Highlights", "None". Replaces WP tag-based approach |
| Pull Quotes | `field_pull_quotes` | Paragraphs | No | Structured pull quote components |
| Related Articles | `field_related_articles` | Entity Reference (Node: Article) | No | Manual override; falls back to auto-related by topic |
| Sticky/Featured | `sticky` | Boolean | No | Core sticky flag |
| Meta Description | `field_meta_description` | Text (plain) | No | SEO meta; Metatag module fallback to excerpt |

### Content Type: Issue (machine name: `issue`)

Represents a quarterly edition of the magazine.

| Field | Machine Name | Type | Required | Notes |
|---|---|---|---|---|
| Title | `title` | Text | Yes | e.g., "Spring 2026" |
| Season | `field_season` | List (text) | Yes | Values: Spring, Summer, Fall, Winter |
| Year | `field_year` | Integer | Yes | e.g., 2026 |
| Cover Image | `field_cover_image` | Entity Reference (Media) | Yes | Magazine cover (1500×1941px source) |
| Banner Image | `field_banner_image` | Entity Reference (Media) | Yes | Issue page hero (1750×677px) |
| Description | `field_description` | Text (formatted, long) | No | Optional editorial introduction |
| Message from Leadership | `field_leadership_message` | Entity Reference (Node: Article) | No | Links to Melany Duval's letter |

**Key Design Decision:** Articles reference Issues via `field_issue`, not the reverse. This means the Issue page dynamically lists all articles that reference it — no manual curation of article order is needed (sorted by weight/date). This is the inverse of the fragile WP tag-based approach.

### Content Type: Page (machine name: `page`)

Standard Drupal page for static content (About, Contact).

| Field | Machine Name | Type | Required | Notes |
|---|---|---|---|---|
| Title | `title` | Text | Yes | |
| Body | `body` | Text (formatted, long) | Yes | |
| Banner Image | `field_banner_image` | Entity Reference (Media) | No | Optional hero |
| Content Sections | `field_content_sections` | Paragraphs | No | Flexible layout components |

### Content Type: In Brief Item (machine name: `in_brief`)

Short-form content items for the "In Brief" section.

| Field | Machine Name | Type | Required | Notes |
|---|---|---|---|---|
| Title | `title` | Text | Yes | |
| Summary | `field_summary` | Text (formatted, long) | Yes | Compact write-up |
| Image | `field_image` | Entity Reference (Media) | No | Supporting image |
| Issue | `field_issue` | Entity Reference (Node: Issue) | Yes | Which edition this belongs to |
| Link | `field_link` | Link | No | Optional link to full article or external resource |

## 2.2 Taxonomy Vocabularies

### Topics (machine name: `topics`)

Replaces the flat WP category system with a **two-level hierarchy**.

**Top Level → Children:**

- **Research**
  - Basic Science
  - Discovery Science
  - Drug Development
  - Immunotherapy
  - Epigenetics
  - AI and Machine Learning
- **Patient Care**
  - Access and Equity
  - Exceptional Expertise
- **Philanthropy**
  - Grassroots Support
  - Essential Opportunities
  - Campaign Updates

### Cancer Types (machine name: `cancer_types`)

Flat vocabulary for cancer-specific tagging.

- Breast Cancer
- Lung Cancer
- Pediatric Cancers
- Blood Cancers
- Gastrointestinal Cancers
- Brain Tumors
- Ovarian Cancer
- Prostate Cancer
- Colorectal Cancer
- Multiple Myeloma
- *(Extensible — editors can add new terms)*

### Content Type (machine name: `content_type`)

Classifies the format/purpose of an article.

- Digital Exclusive
- Campaign Update
- Leadership Message
- Feature Story
- Donor Profile
- Event Recap

## 2.3 Paragraph Types (Component System)

Using the **Paragraphs** module to build structured, reusable content components.

### Pull Quote (`pull_quote`)
| Field | Type | Notes |
|---|---|---|
| Quote Text | Text (formatted) | The quoted text |
| Attribution | Text (plain) | Speaker name and title |

### Image with Caption (`image_caption`)
| Field | Type | Notes |
|---|---|---|
| Image | Entity Reference (Media) | The image |
| Caption | Text (plain) | Caption text |
| Display Mode | List (text) | "Full width", "Half width left", "Half width right" |

### Text Block (`text_block`)
| Field | Type | Notes |
|---|---|---|
| Body | Text (formatted, long) | Rich text content |

### Call to Action (`cta`)
| Field | Type | Notes |
|---|---|---|
| Heading | Text (plain) | CTA title |
| Body | Text (plain, long) | Supporting text |
| Button Text | Text (plain) | Button label |
| Button URL | Link | Button destination |

### Stats/Highlight (`stat_highlight`)
| Field | Type | Notes |
|---|---|---|
| Number | Text (plain) | e.g., "$72M" |
| Label | Text (plain) | e.g., "raised in 2024" |
| Description | Text (plain, long) | Optional context |

### Video Embed (`video_embed`)
| Field | Type | Notes |
|---|---|---|
| Video URL | Link | YouTube/Vimeo URL |
| Caption | Text (plain) | Optional |

## 2.4 Media Types

### Image (core Media type)
- **Image Styles:**
  - `hero_banner` — 2560×981, scale and crop
  - `issue_banner` — 1750×677, scale and crop
  - `issue_cover` — 750×970, scale and crop
  - `article_featured` — 1450×1040, scale and crop
  - `card_large` — 625×400, scale and crop
  - `card_medium` — 400×267, scale and crop
  - `card_small` — 350×200, scale and crop
  - `in_brief` — 300×200, scale and crop
  - `thumbnail` — 150×150, scale and crop
- **Responsive Image Styles:** Configured for breakpoints at 320px, 768px, 1024px, 1440px, 1920px
- **Format:** WebP conversion via ImageAPI Optimize + WebP module
- **Required fields:** Alt text (mandatory), Credit/Source (optional)

### Video (Remote)
- Supports YouTube and Vimeo via `media_entity_video` or oEmbed
- Thumbnail auto-generated from provider

## 2.5 URL Patterns (Pathauto)

| Content Type | URL Pattern | Example |
|---|---|---|
| Article | `/stories/[node:title]` | `/stories/legacy-of-leadership-and-hope` |
| Issue | `/issues/[node:field_season]-[node:field_year]` | `/issues/spring-2026` |
| In Brief Item | `/in-brief/[node:title]` | `/in-brief/pmc-raises-record-funds` |
| Page | `/[node:title]` | `/about-impact` |
| Topic (taxonomy) | `/topics/[term:name]` | `/topics/immunotherapy` |
| Cancer Type (taxonomy) | `/cancer-types/[term:name]` | `/cancer-types/breast-cancer` |

**URL Redirect Strategy:** 301 redirects from all old WordPress URLs (`/2026/03/slug/`) to new Drupal patterns (`/stories/slug`). Handled by the Redirect module + CSV import of old→new mappings.

## 2.6 Menu Structure

### Main Navigation
```
Impact Magazine (logo/home)
├── Current Issue → /issues/spring-2026 (dynamically updated)
├── Browse Issues → /issues
├── In Brief → /in-brief
├── About → /about-impact
├── Topics (dropdown)
│   ├── Cancer Research → /topics/research
│   ├── Patient Care → /topics/patient-care
│   ├── Philanthropy → /topics/philanthropy
│   └── All Topics → /topics
├── Search (icon)
└── Donate (button) → external Jimmy Fund URL
```

### Footer Navigation
```
├── Dana-Farber Cancer Institute (logo + link)
├── The Jimmy Fund (logo + link)
├── Contact Information
├── Privacy Policy
└── © Copyright
```

## 2.7 Views (Dynamic Content Listings)

### Homepage Views

**1. Featured Articles (`view_homepage_featured`)**
- Filter: `field_homepage_placement` = "Featured"
- Sort: Sticky desc, then Date desc
- Display: 1 large card + 2 medium cards
- Limit: 3

**2. Recent Highlights (`view_homepage_highlights`)**
- Filter: `field_homepage_placement` = "Recent Highlights"
- Sort: Date desc
- Display: 3-column card grid
- Limit: 6

**3. Latest Digital Exclusives (`view_homepage_digital_exclusives`)**
- Filter: `field_content_type` = "Digital Exclusive"
- Sort: Date desc
- Display: 4-column card grid
- Limit: 4

### Issue Page View (`view_issue_articles`)
- Contextual filter: Issue node ID (from URL)
- Filter: `field_issue` = current issue
- Sort: Weight (if added), then Date desc
- Display: 2-column article cards with pagination (10 per page)

### Browse Issues View (`view_browse_issues`)
- Content type: Issue
- Sort: `field_year` desc, then `field_season` (custom sort: Spring=1, Summer=2, Fall=3, Winter=4)
- Display: Grid of cover image cards with season/year labels

### In Brief View (`view_in_brief`)
- Content type: In Brief Item
- Sort: Date desc
- Display: List layout with image/text pairs and separators
- Pagination: 10 per page

### Topic Archive View (`view_topic_archive`)
- Contextual filter: Topic taxonomy term
- Sort: Date desc
- Display: 3-column card grid with pagination

### Related Articles View (`view_related_articles`)
- Contextual filter: Current article's topics
- Exclude: Current node
- Sort: Date desc
- Limit: 3
- Fallback: If `field_related_articles` has values, use those instead

## 2.8 Editorial Experience Design

### Article Creation Flow (for non-technical editors)

**Step 1:** Editor clicks "Add Article" from admin toolbar
**Step 2:** Presented with a clean form:
- Title (large, prominent)
- Subtitle (optional, with helper text: "A secondary headline or deck")
- Byline (with helper text: "e.g., By Jane Smith")
- Photo Credit (with helper text: "e.g., Photography by John Doe")
- Body (CKEditor 5 with limited toolbar: Bold, Italic, Link, Block Quote, Heading 2/3, Ordered/Unordered List, Image, Table)
- Featured Image (drag-and-drop media library widget)

**Step 3:** Sidebar metadata:
- Issue dropdown (select from existing issues, or leave empty for Digital Exclusive)
- Topics (autocomplete multi-select)
- Cancer Types (optional autocomplete)
- Content Type (dropdown)
- Homepage Placement (dropdown: None / Featured / Recent Highlights)
- Excerpt (textarea with character counter, helper text: "Leave blank to auto-generate from body")
- Meta Description (for SEO)

**Step 4:** Preview button shows the article exactly as it will appear on the frontend
**Step 5:** Save as Draft or Publish

### Key UX Improvements over WordPress

1. **Structured byline/credit fields** — No more typing "By [Name]" into the body
2. **Issue selector dropdown** — No more guessing tag names for issue association
3. **Homepage placement dropdown** — Clear "Featured" / "Recent Highlights" / "None" instead of cryptic tags
4. **Inline media library** — Drag-and-drop with automatic image style application
5. **Content preview** — Exact frontend rendering before publish
6. **Field descriptions** — Every field has helper text explaining what it's for
7. **Required field indicators** — Visual cues for mandatory fields
8. **Editorial dashboard** — Custom admin view showing articles by issue, status, and date

### Content Moderation Workflow

Using Drupal core's Content Moderation module:

```
Draft → In Review → Published → Archived
```

- **Draft:** Editor creates/edits content
- **In Review:** Editor marks content ready for review (optional; can skip for small teams)
- **Published:** Content is live on the site
- **Archived:** Content is unpublished but preserved (e.g., for past issue cleanup)

Permissions:
- **Editor role:** Can create drafts, submit for review, publish articles and In Brief items
- **Issue Manager role:** Can create/edit Issues, manage taxonomy terms, control homepage placement
- **Administrator:** Full access

### Editorial Dashboard

Custom admin view at `/admin/content/editorial-dashboard`:
- Filterable by Issue, Status, Topic, Content Type
- Columns: Title, Issue, Status, Author, Last Modified, Homepage Placement
- Bulk operations: Publish, Archive, Change Issue, Change Homepage Placement

---

# PHASE 3: IMPLEMENTATION PLAN

## 3.1 Environment Setup

### Local Development
- **Tool:** DDEV (Docker-based Drupal development environment)
- **PHP:** 8.2+
- **Database:** MariaDB 10.6
- **Drupal:** 10.4.x or 11.x (evaluate LTS timeline)
- **Composer:** Dependency management
- **Drush:** CLI tool for Drupal administration

### Environments
| Environment | Purpose | URL Pattern |
|---|---|---|
| Local | Developer workstations | `https://df-impact.ddev.site` |
| Dev | Integration testing | `https://dev.danafarberimpact.org` |
| Staging | UAT and editorial preview | `https://staging.danafarberimpact.org` |
| Production | Live site | `https://danafarberimpact.org` |

### Hosting Recommendation
- **Platform:** Pantheon or Acquia (managed Drupal hosting)
- **CDN:** Cloudflare or Fastly (replaces Jetpack CDN)
- **CI/CD:** GitHub Actions or GitLab CI for automated deployment

## 3.2 Required Modules

### Core Modules (Enabled)
- `node` — Content types
- `taxonomy` — Vocabularies
- `media` / `media_library` — Media management
- `views` — Content listings
- `content_moderation` — Editorial workflow
- `path` / `path_alias` — URL aliases
- `menu_ui` — Menu management
- `block` — Block placement
- `responsive_image` — Responsive images
- `ckeditor5` — Rich text editor
- `search` — Search functionality
- `jsonapi` — Decoupled access (optional)

### Contributed Modules

| Module | Purpose | Replaces (WP) |
|---|---|---|
| `paragraphs` | Structured content components | Ultimate Post Grid |
| `pathauto` | Automated URL aliases | WordPress permalink settings |
| `redirect` | URL redirects (301s from old URLs) | — |
| `metatag` | SEO meta tags | Yoast SEO |
| `simple_sitemap` | XML sitemap | Yoast SEO sitemap |
| `google_analytics` | Analytics tracking | — |
| `admin_toolbar` | Improved admin navigation | — |
| `admin_toolbar_tools` | Extra admin toolbar links | — |
| `token` | Token system for Pathauto patterns | — |
| `field_group` | Organize form fields into tabs/groups | — |
| `entity_reference_revisions` | Paragraphs dependency | — |
| `focal_point` | Image cropping with focal point | — |
| `imageapi_optimize` | Image optimization | Jetpack CDN |
| `webp` | WebP image conversion | — |
| `social_media_links` | Social sharing block | Jetpack sharing |
| `search_api` + `search_api_solr` OR `search_api_db` | Advanced search | WordPress search |
| `views_infinite_scroll` | Load-more pagination | ULTP pagination |
| `scheduler` | Scheduled publishing | — |
| `linkit` | Internal content linking in CKEditor | — |
| `editoria11y` | Accessibility checker for editors | — |
| `gin` | Modern admin theme | — |
| `coffee` | Quick admin navigation | — |
| `config_split` | Environment-specific config | — |
| `stage_file_proxy` | Dev environment media proxying | — |

## 3.3 Implementation Phases

### Phase A: Foundation (Week 1-2)

1. **Set up DDEV local environment**
   - Initialize Drupal project via Composer
   - Configure DDEV for local development
   - Install Drupal with standard profile

2. **Install and configure contributed modules**
   - Install all modules listed in §3.2 via Composer
   - Enable and configure base modules

3. **Create taxonomy vocabularies**
   - Topics (with parent-child hierarchy)
   - Cancer Types (flat)
   - Content Type (flat)
   - Populate with initial terms from WP category/tag analysis

4. **Create content types with all fields**
   - Article (per §2.1 specification)
   - Issue (per §2.1 specification)
   - In Brief Item (per §2.1 specification)
   - Page (per §2.1 specification)

5. **Create Paragraph types**
   - Pull Quote, Image with Caption, Text Block, CTA, Stat Highlight, Video Embed

6. **Configure media types and image styles**
   - Image styles per §2.4
   - Responsive image styles with breakpoints
   - WebP conversion pipeline

7. **Set up content moderation workflow**
   - Draft → In Review → Published → Archived
   - Configure role permissions

### Phase B: Site Building (Week 3-4)

8. **Configure Pathauto URL patterns**
   - Per §2.5 specification
   - Test with sample content

9. **Build Views**
   - Homepage Featured Articles
   - Homepage Recent Highlights
   - Homepage Digital Exclusives
   - Issue Articles listing
   - Browse Issues grid
   - In Brief listing
   - Topic Archive
   - Related Articles
   - Editorial Dashboard (admin)

10. **Configure menus**
    - Main Navigation per §2.6
    - Footer Navigation
    - Dynamic "Current Issue" menu item (via custom token or menu block)

11. **Configure SEO**
    - Metatag defaults for each content type
    - Simple Sitemap configuration
    - Canonical URL settings
    - Schema.org markup (Article, Organization)

12. **Configure CKEditor 5**
    - Custom toolbar for Article body field
    - Custom styles (drop cap, pull quote formatting)
    - Linkit integration for internal linking

13. **Configure editorial form UX**
    - Field groups (tabs: Content, Media, Metadata, SEO)
    - Field descriptions/helper text
    - Conditional field visibility
    - Form display ordering

### Phase C: Theming (Week 5-7)

14. **Create custom theme**
    - Base theme: Olivero (core) or custom starter
    - Component-based architecture (BEM CSS methodology)
    - Design tokens: colors (#00629b, #ffa300, #f6f6f6, #0f0e17), typography (Libre Baskerville, Montserrat)

15. **Build page templates**
    - Homepage layout
    - Article page (full article with featured image, metadata, related content)
    - Issue page (banner + article grid)
    - Browse Issues page (cover grid)
    - In Brief page (list layout)
    - Topic archive page
    - Search results page

16. **Build reusable components**
    - Article card (3 variants: large, medium, compact)
    - Issue cover card
    - Pull quote block
    - Hero banner
    - In Brief list item
    - Gradient divider
    - Pagination
    - Social sharing buttons
    - Donate button (persistent header CTA)

17. **Responsive design**
    - Mobile-first approach
    - Breakpoints: 320px, 768px, 1024px, 1440px, 1920px
    - Test on iOS Safari, Chrome, Firefox, Edge

18. **Accessibility**
    - WCAG 2.1 AA compliance
    - Keyboard navigation
    - Screen reader testing
    - Color contrast verification
    - Focus states for all interactive elements

### Phase D: Migration (Week 8-10)

19. **WordPress data export**
    - Export all posts, pages, media, categories, tags via WP REST API or WP-CLI
    - Generate CSV/JSON migration source files
    - Map old fields to new Drupal fields

20. **Write Drupal migration plugins**
    - Using Migrate API + `migrate_plus` + `migrate_source_csv` (or `migrate_source_json`)
    - Migration order:
      1. Taxonomy terms (Topics, Cancer Types, Content Type)
      2. Media entities (images)
      3. Issues (from WP pages)
      4. Articles (from WP posts, with entity references to Issues and Taxonomies)
      5. In Brief items (filter from WP posts by tag/category)
      6. Pages (About, etc.)
      7. URL redirects (old → new)

21. **Field mapping**

    | WP Field | Drupal Field | Transformation |
    |---|---|---|
    | `post_title` | `title` | Direct |
    | `post_content` | `body` | HTML cleanup, extract byline/credit |
    | `post_excerpt` | `field_excerpt` | Direct |
    | `_thumbnail_id` | `field_featured_image` | Media entity reference |
    | Categories | `field_topics` | Map to hierarchical terms |
    | Tags (season) | `field_issue` | Map to Issue node reference |
    | Tags (Homepage Featured) | `field_homepage_placement` | Map to list value |
    | Tags (Digital Exclusives) | `field_content_type` | Map to taxonomy term |
    | `post_date` | `created` | Direct |
    | Yoast meta | `field_meta_description` | Extract from yoast_head_json |
    | Body "By [Name]" | `field_byline` | Regex extraction from body |
    | Body "Photography by [Name]" | `field_photo_credit` | Regex extraction from body |

22. **Test migration**
    - Run migration on dev environment
    - Validate content count matches
    - Spot-check 20+ articles for field accuracy
    - Verify image migration and style generation
    - Verify taxonomy term assignment
    - Verify Issue associations

23. **URL redirect import**
    - Generate redirect CSV: `/2026/03/old-slug/` → `/stories/new-slug`
    - Import via Redirect module
    - Test all 600+ redirects

### Phase E: Integration & Polish (Week 11-12)

24. **Search configuration**
    - Index Article, Issue, In Brief content types
    - Configure faceted search by Topic, Cancer Type, Issue
    - Search results page with article card display

25. **Google Analytics / Tag Manager integration**
    - Configure tracking code
    - Set up event tracking for Donate clicks, article reads, issue browsing

26. **Translation / Language**
    - Evaluate GTranslate equivalent or Drupal Translation module
    - If machine translation: configure Google Cloud Translation API or similar

27. **Performance optimization**
    - Enable Drupal page caching
    - Configure CDN (Cloudflare/Fastly)
    - Optimize CSS/JS aggregation
    - Lazy loading for images (core Drupal supports this)
    - Target: <3s LCP on mobile

28. **Security hardening**
    - Security review module
    - Content Security Policy headers
    - Rate limiting on forms
    - Automated security updates via Composer

29. **Backup and disaster recovery**
    - Automated daily backups (database + files)
    - Tested restore procedure

### Phase F: Launch (Week 13-14)

30. **User Acceptance Testing (UAT)**
    - Editorial team creates test content
    - Stakeholder review of all page types
    - Cross-browser/device testing

31. **Content freeze and final migration**
    - Freeze WordPress content updates
    - Run final migration
    - Verify redirect coverage

32. **DNS cutover**
    - Point `danafarberimpact.org` to Drupal hosting
    - SSL certificate provisioning
    - Monitor for errors

33. **Post-launch monitoring**
    - 72-hour intensive monitoring
    - Check Google Search Console for crawl errors
    - Monitor 404s and add missing redirects
    - Performance benchmarking

---

# PHASE 4: TESTING & VALIDATION STRATEGY

## 4.1 Content Creation Flow Testing

### Acceptance Criteria
- [ ] Editor can create a new Article with all required fields in under 5 minutes
- [ ] Editor can associate an article with an Issue via dropdown (not free-text tags)
- [ ] Editor can set Homepage Placement via dropdown
- [ ] Editor can upload and crop a featured image with focal point
- [ ] Editor can preview the article as it will appear on the frontend
- [ ] Editor can save a draft without publishing
- [ ] Editor can move an article through Draft → In Review → Published workflow
- [ ] Editor can create a new Issue with cover and banner images
- [ ] Editor can create an In Brief item and associate it with an Issue

### Test Script
1. Log in as editor role
2. Navigate to Add Article
3. Fill in all fields with test content
4. Upload a featured image
5. Select an Issue, Topics, and Homepage Placement
6. Preview the article
7. Save as Draft
8. Change status to Published
9. Verify article appears on Issue page, Homepage (if placement set), and Topic archive

## 4.2 Editorial Usability Testing

### Test Participants
- 2-3 members of Dana-Farber editorial team (Tarice Gray, Hannah White)
- 1 non-technical content contributor (guest writer)

### Tasks to Evaluate
1. Create a new article from scratch
2. Edit an existing article's featured image
3. Change which articles appear on the homepage
4. Create a new quarterly issue
5. Find and edit an article from 6 months ago
6. Add a new Topic term

### Success Metrics
- Task completion rate: >95%
- Average task time: <3 minutes for basic operations
- System Usability Scale (SUS) score: >75
- Zero instances of editor needing developer help for routine tasks

## 4.3 Responsive Design Testing

### Devices/Viewports
| Device | Viewport | Browser |
|---|---|---|
| iPhone 14 | 390×844 | Safari |
| iPhone SE | 375×667 | Safari |
| iPad Air | 820×1180 | Safari |
| Samsung Galaxy S24 | 360×780 | Chrome |
| Desktop | 1920×1080 | Chrome, Firefox, Edge |
| Desktop | 1440×900 | Chrome |
| Desktop | 2560×1440 | Chrome |

### Checklist per Viewport
- [ ] Homepage layout renders correctly (grids collapse appropriately)
- [ ] Article page is readable (text doesn't overflow, images scale)
- [ ] Navigation is accessible (mobile hamburger menu works)
- [ ] Issue page grid adapts to screen width
- [ ] In Brief items stack on mobile
- [ ] Donate button remains accessible
- [ ] Search is functional
- [ ] Images load appropriate responsive variant
- [ ] No horizontal scroll on any page
- [ ] Touch targets are ≥44px on mobile

## 4.4 Performance Testing

### Targets
| Metric | Target | Tool |
|---|---|---|
| Largest Contentful Paint (LCP) | <2.5s | Lighthouse |
| First Input Delay (FID) | <100ms | Lighthouse |
| Cumulative Layout Shift (CLS) | <0.1 | Lighthouse |
| Time to First Byte (TTFB) | <600ms | WebPageTest |
| Total page weight | <2MB | Browser DevTools |
| Lighthouse Performance Score | >90 | Lighthouse |

### Test Pages
- Homepage (heaviest — multiple image grids)
- Article page (featured image + body content)
- Issue page (multiple article cards)
- Browse Issues page (cover image grid)

## 4.5 SEO Validation

### Checklist
- [ ] All pages have unique `<title>` tags
- [ ] All pages have meta descriptions
- [ ] Open Graph tags render correctly (test with Facebook Debugger)
- [ ] Twitter Card tags render correctly (test with Twitter Card Validator)
- [ ] XML sitemap includes all content types and is accessible at `/sitemap.xml`
- [ ] `robots.txt` is properly configured
- [ ] Canonical URLs are set on all pages
- [ ] Schema.org Article markup on article pages
- [ ] Schema.org Organization markup on About page
- [ ] Breadcrumb markup matches visual breadcrumbs
- [ ] All 600+ old WordPress URLs return 301 redirects
- [ ] No 404 errors for indexed pages (verify via Google Search Console)
- [ ] H1 tags are present and unique on every page
- [ ] Image alt text is present on all images
- [ ] Internal links use descriptive anchor text

## 4.6 Automated Testing

### PHPUnit (Drupal core testing)
- **Kernel tests** for migration plugins — verify field mapping accuracy
- **Functional tests** for content creation forms — verify required fields, validation
- **Unit tests** for any custom modules (e.g., homepage placement logic)

### Visual Regression Testing
- **Tool:** BackstopJS or Percy
- **Pages:** Homepage, Article, Issue, Browse Issues, In Brief, About
- **Breakpoints:** 375px, 768px, 1024px, 1440px
- **Trigger:** On every deployment to staging

### Accessibility Testing
- **Automated:** axe-core via CI pipeline on every deployment
- **Manual:** Screen reader walkthrough (VoiceOver on Mac, NVDA on Windows)
- **Standard:** WCAG 2.1 AA

## 4.7 Manual QA Checklists

### Pre-Launch Checklist
- [ ] All content migrated and verified (count match, spot checks)
- [ ] All URL redirects working (sample 50+ old URLs)
- [ ] Homepage displays correct featured/highlighted content
- [ ] All quarterly issues accessible and displaying correct articles
- [ ] Search returns relevant results
- [ ] Donate button works and tracks correctly
- [ ] Forms (if any) submit successfully
- [ ] Email addresses/phone numbers on About page are correct
- [ ] Footer logos link to correct destinations
- [ ] SSL certificate valid and forced
- [ ] No mixed content warnings
- [ ] Favicons/app icons configured
- [ ] 404 page is styled and helpful
- [ ] Social sharing produces correct previews

---

# PHASE 5: PERSISTENT PROJECT MEMORY SYSTEM

## 5.1 Architecture Decisions Log

### Format
```
### ADR-[NNN]: [Decision Title]
**Date:** YYYY-MM-DD
**Status:** Accepted | Superseded | Deprecated
**Decision:** [What was decided]
**Context:** [Why this decision was needed]
**Alternatives Considered:**
1. [Alternative A] — rejected because [reason]
2. [Alternative B] — rejected because [reason]
**Consequences:** [What this means going forward]
```

### Initial Decisions

#### ADR-001: Use Entity Reference to Issue Instead of Tags
**Date:** 2026-03-31
**Status:** Accepted
**Decision:** Articles reference Issues via an Entity Reference field (`field_issue`) rather than taxonomy tags.
**Context:** The WordPress site uses free-text tags (e.g., "Spring 2026") to associate articles with issues. This is error-prone — typos, inconsistent naming, and no validation. Editors must remember exact tag names.
**Alternatives Considered:**
1. Taxonomy vocabulary "Issues" — rejected because Issues have structured data (cover image, banner, season/year) that taxonomies can't store well.
2. Tag-based approach (replicate WP) — rejected because it perpetuates the fragile WP pattern.
**Consequences:** Every article has a clear, validated link to its Issue. Issue pages dynamically list their articles via Views. Editors select from a dropdown, eliminating typos.

#### ADR-002: Structured Byline and Photo Credit Fields
**Date:** 2026-03-31
**Status:** Accepted
**Decision:** Byline and Photo Credit are dedicated text fields on the Article content type, not embedded in the body.
**Context:** The WordPress site embeds "By [Name]" and "Photography by [Name]" directly in the post body. This prevents filtering by author, creates inconsistent formatting, and makes bulk updates impossible.
**Alternatives Considered:**
1. Use Drupal's core Author field — rejected because the byline name doesn't always correspond to a Drupal user account.
2. Keep in body (replicate WP) — rejected because it perpetuates formatting inconsistency.
**Consequences:** Bylines render consistently in templates. Future capability to create author archive pages. Migration must extract bylines from body text via regex.

#### ADR-003: Paragraphs for Flexible Content, Not Layout Builder
**Date:** 2026-03-31
**Status:** Accepted
**Decision:** Use the Paragraphs module for structured content components within articles and pages. Do not use Layout Builder.
**Context:** Layout Builder gives editors full layout control but adds complexity that is unnecessary for this primarily editorial site. Paragraphs provide structured, predictable components that maintain design consistency.
**Alternatives Considered:**
1. Layout Builder — rejected because it adds editorial complexity beyond what's needed. Editors don't need arbitrary layout control; they need consistent article presentation.
2. CKEditor-only (no components) — rejected because pull quotes, CTAs, and stat highlights benefit from structured data entry rather than manual HTML formatting.
**Consequences:** Editors add pre-defined components (pull quotes, images, CTAs) to pages via a simple "Add" button. Design consistency is enforced. Template logic is predictable.

#### ADR-004: Hierarchical Topics Taxonomy
**Date:** 2026-03-31
**Status:** Accepted
**Decision:** Replace WordPress's flat 16-category system with a two-level hierarchical Topics vocabulary (Research → Basic Science, Drug Development, etc.).
**Context:** The WP site has 16 flat categories mixing broad themes (Cancer Research at 437 posts) with narrow topics (Lung Cancer at 2 posts). This flat structure makes it difficult to browse content thematically.
**Alternatives Considered:**
1. Flat taxonomy (replicate WP) — rejected because it doesn't support thematic grouping.
2. Three-level hierarchy — rejected as over-engineering for current content volume.
**Consequences:** Topic archive pages can show parent-level overviews with drill-down. Navigation can present topics in logical groups. Migration must map flat WP categories to hierarchical terms.

#### ADR-005: Separate Cancer Types Vocabulary
**Date:** 2026-03-31
**Status:** Accepted
**Decision:** Cancer types are a separate vocabulary from Topics.
**Context:** In WordPress, cancer types (Breast Cancer, Lung Cancer) are mixed into the same category list as editorial themes (Grassroots Support, Access and Equity). These are fundamentally different classification axes.
**Alternatives Considered:**
1. Sub-terms under a "Cancer Types" parent in Topics — rejected because an article can be about both "Breast Cancer" (type) and "Immunotherapy" (topic), and these shouldn't be in the same hierarchy.
**Consequences:** Articles can be independently tagged with both a Topic (what the story is about) and Cancer Type(s) (which cancer). This enables faceted filtering on search and archive pages.

#### ADR-006: Homepage Placement via List Field, Not Tags
**Date:** 2026-03-31
**Status:** Accepted
**Decision:** Use a List (text) field with defined values ("Featured", "Recent Highlights", "None") instead of tags for homepage content curation.
**Context:** WordPress uses tags like "Homepage Featured" and "Homepage Recent Highlights" to control what appears on the homepage. This is unintuitive — editors must know the exact tag names and understand the tag-to-layout mapping.
**Alternatives Considered:**
1. Tag-based approach (replicate WP) — rejected for usability reasons.
2. Separate "Homepage Curation" content type or entity — rejected as over-engineering.
**Consequences:** Editors see a clear dropdown when editing an article. Views filter on this field. Only one value is allowed, preventing an article from appearing in multiple homepage sections simultaneously.

## 5.2 Progress Tracker

### Format
```
| Task | Status | Completed | Notes |
|---|---|---|---|
| [Task description] | Not Started / In Progress / Complete / Blocked | YYYY-MM-DD | [Any notes] |
```

### Current State
| Task | Status | Completed | Notes |
|---|---|---|---|
| Phase 1: Site Analysis | Complete | 2026-03-31 | Documented in this file |
| Phase 2: Architecture Design | Complete | 2026-03-31 | Documented in this file |
| Phase 3: Implementation Plan | Complete | 2026-03-31 | Documented in this file |
| Phase 4: Testing Strategy | Complete | 2026-03-31 | Documented in this file |
| Phase 5: Project Memory | Complete | 2026-03-31 | This section |
| Environment Setup (DDEV) | Complete | 2026-03-31 | DDEV at http://df-impact.ddev.site |
| Module Installation | Complete | 2026-03-31 | 22 contrib modules installed |
| Taxonomy Creation | Complete | 2026-03-31 | 3 vocabularies, 30 terms |
| Content Type Creation | Complete | 2026-03-31 | 4 types, 27 custom fields total |
| Paragraph Type Creation | Complete | 2026-03-31 | 6 paragraph types |
| Media Configuration | Complete | 2026-03-31 | 8 custom image styles |
| Content Moderation Setup | Complete | 2026-03-31 | 4 states, 2 roles |
| Pathauto Configuration | Complete | 2026-03-31 | 3 URL patterns |
| Views Building | Complete | 2026-03-31 | 9 custom views (+ core) |
| Menu Configuration | Complete | 2026-03-31 | Main nav + Footer |
| SEO Configuration | Complete | 2026-03-31 | Metatag defaults + Sitemap |
| CKEditor 5 Configuration | Complete | 2026-03-31 | Full HTML + Basic HTML toolbars |
| Form Display / Field Groups | Complete | 2026-03-31 | 3 field groups on Article |
| Theme Development | Complete | 2026-03-31 | df_impact theme: 4 CSS files, 17 Twig templates, JS, .theme file |
| Migration Development | Complete | 2026-04-01 | Python pipeline + df_migrate; see `standups/STANDUP.md` Phase D (local) |
| Migration Testing | Complete | 2026-04-01 | Counts verified; redirects + moderation_state fixes applied |
| Search Configuration | Complete | 2026-04-01 | Search API DB, `/search` Views, 673 items indexed |
| Performance Optimization | Complete | 2026-04-01 | Aggregate CSS/JS, page cache 900s — see `standups/STANDUP.md` (local) |
| UAT | In progress | — | Editorial QA per STANDUP |
| Launch | Not Started | — | GTM, HSTS, DNS cutover pending |
| Site snippets (header/footer) | Complete | 2026-04-08 | Custom module `df_site_snippets` — Configuration → Site snippets |

## 5.3 Mistakes & Learnings Log

### Format
```
### ML-[NNN]: [Short Description]
**Date:** YYYY-MM-DD
**What Went Wrong:** [Description]
**Root Cause:** [Why it happened]
**How It Was Fixed:** [Resolution]
**Prevention:** [How to prevent recurrence]
```

### ML-001: Content moderation ignored `status` during migration
**Date:** 2026-04-01
**What Went Wrong:** Imported nodes appeared unpublished or wrong workflow state despite `status: 1` in migration process.
**Root Cause:** Content Moderation controls visibility; `status` alone is not sufficient when moderation is enabled.
**How It Was Fixed:** Added `moderation_state` to migration process maps (e.g. `published` for published WP posts).
**Prevention:** Every future node migration must set both `status` and `moderation_state`; document in migration checklist.

### ML-002: Search API DB backend required undocumented `database` key
**Date:** 2026-04-01
**What Went Wrong:** Search API database backend failed or behaved incorrectly until backend config was corrected.
**Root Cause:** Module expected `'database' => 'default:default'` in `backend_config`; not obvious from UI-only setup.
**How It Was Fixed:** Set correct `backend_config` for `search_api_db` (confirmed via source inspection).
**Prevention:** Export and version `search_api.server.*` config; document required keys in AGENT_HANDOFF / server README.

### ML-003: Search API tracker item ID format assumptions
**Date:** 2026-04-01
**What Went Wrong:** Pagination or reindexing scripts assumed wrong ID format for indexed items.
**Root Cause:** Datasource item IDs use formats like `NID:langcode`, not `langcode/NID`.
**How It Was Fixed:** Confirmed `getItemIds()` behavior and aligned scripts/drush usage.
**Prevention:** Read Search API datasource plugin docs before writing bulk index tooling.

### ML-004: DDEV nginx custom directives overwritten
**Date:** 2026-04-01
**What Went Wrong:** Security headers (or other nginx tweaks) disappeared after DDEV restart.
**Root Cause:** Edits to auto-generated `nginx-site.conf` with `#ddev-generated` are replaced on regenerate.
**How It Was Fixed:** Placed custom config in `.ddev/nginx/*.conf` included via `include /mnt/ddev_config/nginx/*.conf`.
**Prevention:** Never edit generated DDEV nginx blocks in place; use include hook pattern only.

### ML-005: Plan tracker drift vs engineered reality
**Date:** 2026-04-08
**What Went Wrong:** This document’s §5.2 still showed migration and search as “Not Started” while STANDUP and production data reflected completion.
**Root Cause:** Single plan file not updated after Phase D/E; teams used STANDUP for status.
**How It Was Fixed:** Reconciled §5.2 with STANDUP; Appendix A paths corrected; STANDUP and this file tracked in Git.
**Prevention:** After each phase, update §5.2 or explicitly deprecate it in favor of a dated engineering log.

### ML-006: WordPress excerpt used for both subtitle and excerpt fields
**Date:** 2026-04-08
**What Went Wrong:** Migrated articles could show redundant “subtitle” text that matched the excerpt/card summary, unlike the legacy site’s editorial pattern.
**Root Cause:** `migrate_plus.migration.df_articles` mapped WP `excerpt` to both `field_subtitle` and `field_excerpt`.
**How It Was Fixed:** Removed `field_subtitle` from article migration mapping; subtitle remains editor-entered only. Re-run migration or bulk-clear subtitles on existing nodes if needed.
**Prevention:** Map each Drupal field to at most one WP source unless explicitly validated for duplication.

## 5.4 Content Model Documentation

Fully documented in Phase 2 sections:
- §2.1 — Content Types and Fields
- §2.2 — Taxonomy Vocabularies
- §2.3 — Paragraph Types
- §2.4 — Media Types and Image Styles
- §2.5 — URL Patterns
- §2.7 — Views

## 5.5 Editorial Guide (for non-technical users)

### Creating a New Article

1. **Log in** to the Drupal admin at `danafarberimpact.org/user/login`
2. Click **"Content" → "Add Content" → "Article"** in the admin toolbar
3. Fill in the form:
   - **Title:** The article headline (keep under 100 characters for best display)
   - **Subtitle:** Optional secondary headline
   - **Byline:** The author's name, e.g., "By Jane Smith"
   - **Photo Credit:** The photographer, e.g., "Photography by John Doe"
   - **Body:** Write or paste the article text. Use the toolbar to:
     - Add **bold** or *italic* text
     - Insert **block quotes** for pull quotes
     - Add **headings** (H2, H3 only — H1 is the title)
     - Insert **links** (highlight text → click link icon → search for content or paste URL)
     - Insert **images** inline (click image icon → select from media library or upload)
   - **Featured Image:** Click "Select media" → upload a new image or choose from the library. Minimum size: 1450×1040px. You can adjust the **focal point** by clicking on the most important part of the image.
4. In the **sidebar**, fill in:
   - **Issue:** Select the quarterly issue this article belongs to. Leave empty for Digital Exclusives.
   - **Topics:** Start typing to search. Select one or more topics (e.g., "Immunotherapy", "Grassroots Support").
   - **Cancer Types:** Optional. Select if the article is about a specific cancer type.
   - **Content Type:** Select the article format (Feature Story, Donor Profile, Event Recap, etc.)
   - **Homepage Placement:** Choose "Featured" to put this in the hero section, "Recent Highlights" for the secondary grid, or leave as "None."
   - **Excerpt:** Write a 1-2 sentence summary. If left blank, one will be auto-generated.
5. Click **"Preview"** to see exactly how the article will look on the site.
6. Click **"Save as Draft"** to save without publishing, or **"Publish"** to make it live immediately.

### Creating a New Quarterly Issue

1. Go to **"Content" → "Add Content" → "Issue"**
2. Fill in:
   - **Title:** e.g., "Summer 2026"
   - **Season:** Select from dropdown
   - **Year:** Enter the year
   - **Cover Image:** Upload the magazine cover image (1500×1941px)
   - **Banner Image:** Upload the issue page banner (1750×677px)
   - **Description:** Optional introduction text
   - **Leadership Message:** Link to the Melany Duval letter article (if applicable)
3. Click **"Publish"**
4. The issue page will automatically display all articles that have this issue selected in their Issue field.

### Managing the Homepage

The homepage is populated automatically based on article settings:
- To **feature an article** in the hero section: Edit the article → set "Homepage Placement" to "Featured"
- To add an article to **Recent Highlights**: Edit the article → set "Homepage Placement" to "Recent Highlights"
- To **remove an article** from the homepage: Edit the article → set "Homepage Placement" to "None"
- **Digital Exclusives** section auto-populates from the most recent articles tagged with the "Digital Exclusive" content type.

### Finding and Editing Content

- Go to **"Content"** in the admin toolbar to see all content
- Use **filters** to narrow by content type, status, issue, or topic
- Click any title to edit
- Use the **Editorial Dashboard** at "Content → Editorial Dashboard" for a publishing-focused view

### Administrative experience (stakeholders)

For options on simplifying the back end for non-technical editors versus power users, see **[docs/ADMIN_UX_OPTIONS.md](docs/ADMIN_UX_OPTIONS.md)**.

### Trusted header/footer snippets (technical editors)

Site-wide HTML snippets (for example inline CSS or JavaScript approved by the web team) are managed at **Configuration → System → Site snippets** after the **`df_site_snippets`** module is enabled. See **§5.6** and module README for security and CSP notes.

## 5.6 Site snippets (technical editors)

The **`df_site_snippets`** module stores optional **header** and **footer** HTML in configuration (`df_site_snippets.settings`). Trusted users with the **Administer site snippets** permission can paste markup that is rendered in `<head>` and before `</body>` without a code deploy.

**Security:** Treat this permission like code deployment access. Snippets run in every visitor’s browser; only highly trusted roles should edit them. Third-party script domains may require **Content-Security-Policy** updates in **Seckit** (or equivalent) if not already allow-listed — see `standups/STANDUP.md` Phase E security section (local).

**Cache:** Pages are tagged with `config:df_site_snippets.settings`; saving the form invalidates affected caches.

---

## APPENDIX A: Configuration Management Strategy

Active configuration exports for this project live under **`drupal/web/sites/default/files/sync/`** (the directory set as the sync folder in `settings.php` for local/DDEV — adjust if your `settings.local.php` overrides `$settings['config_sync_directory']`).

```
drupal/web/sites/default/files/sync/
│   ├── core.entity_form_display.node.article.default.yml
│   ├── core.entity_view_display.node.article.default.yml
│   ├── field.field.node.article.field_byline.yml
│   ├── field.field.node.article.field_issue.yml
│   ├── node.type.article.yml
│   ├── taxonomy.vocabulary.topics.yml
│   ├── views.view.homepage_featured.yml
│   └── ... (full site export)
```

Some documentation may refer to a top-level `config/sync/` layout; for this repo, treat **`files/sync`** as canonical unless the team standardizes a different `config_sync_directory`.

**Workflow:**
1. Make configuration changes on local environment
2. Export config: `drush config:export` (from the Drupal docroot or via DDEV: `ddev drush cex`)
3. Commit config changes to Git
4. Deploy to dev/staging: `drush config:import`
5. Test
6. Deploy to production: `drush config:import`

**Environment splits** (via `config_split`):
- `config/dev/` — Dev-only modules (devel, stage_file_proxy)
- `config/prod/` — Production-only settings (caching, CDN)

## APPENDIX B: Migration Approach Detail

### Data Source Options

**Option 1: WordPress REST API (Recommended)**
- Fetch all posts/pages/media/taxonomies via paginated API calls
- Script to dump to JSON files as migration source
- Advantages: Structured data, includes Yoast SEO metadata, media URLs
- Command: `curl "https://danafarberimpact.org/wp-json/wp/v2/posts?per_page=100&page=1&_embed"`

**Option 2: WordPress Database Export**
- Direct SQL dump of `wp_posts`, `wp_postmeta`, `wp_terms`, etc.
- Requires database access
- More complex but handles edge cases (private drafts, revisions)

### Migration Plugins Structure
```
modules/custom/df_migrate/
├── df_migrate.info.yml
├── config/install/
│   ├── migrate_plus.migration.df_taxonomy_topics.yml
│   ├── migrate_plus.migration.df_taxonomy_cancer_types.yml
│   ├── migrate_plus.migration.df_media_images.yml
│   ├── migrate_plus.migration.df_issues.yml
│   ├── migrate_plus.migration.df_articles.yml
│   ├── migrate_plus.migration.df_in_brief.yml
│   ├── migrate_plus.migration.df_pages.yml
│   └── migrate_plus.migration.df_redirects.yml
├── src/Plugin/migrate/process/
│   ├── ExtractByline.php        # Regex to pull "By [Name]" from body
│   ├── ExtractPhotoCredit.php   # Regex to pull "Photography by [Name]"
│   ├── MapWpCategoryToTopic.php # Map flat categories to hierarchical terms
│   ├── MapWpTagToIssue.php      # Map season tags to Issue node references
│   └── CleanArticleBody.php     # Remove byline/credit from body, fix HTML
```

### Migration Execution Order
1. `drush migrate:import df_taxonomy_topics`
2. `drush migrate:import df_taxonomy_cancer_types`
3. `drush migrate:import df_media_images`
4. `drush migrate:import df_issues`
5. `drush migrate:import df_articles`
6. `drush migrate:import df_in_brief`
7. `drush migrate:import df_pages`
8. `drush migrate:import df_redirects`

### Rollback
Each migration can be rolled back independently:
`drush migrate:rollback df_articles`

---

## APPENDIX C: Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Byline extraction regex misses edge cases | High | Medium | Manual review of extracted bylines; QA checklist for 50+ articles |
| Old URL redirects incomplete (SEO impact) | Medium | High | Crawl WordPress site to capture ALL indexed URLs; monitor Search Console post-launch |
| Editors find Drupal admin unfamiliar | Medium | Medium | Training sessions; Gin admin theme for modern UX; written editorial guide |
| Image migration loses quality/crops | Low | Medium | Migrate original uploads, regenerate image styles in Drupal |
| Performance regression vs. WordPress | Low | High | CDN configuration; Drupal caching; benchmark before launch |
| Content created in WP during migration window | Medium | Medium | Enforce content freeze; short cutover window; final delta migration |
| Module compatibility with Drupal version | Low | High | Test all modules on target Drupal version before committing to architecture |

---

*This document serves as the single source of truth for the Dana-Farber Impact Magazine Drupal rebuild. It should be updated as decisions are made and implementation progresses.*

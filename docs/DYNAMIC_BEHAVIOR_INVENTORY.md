# Dynamic behavior inventory (WordPress → Drupal)

Sources: WXR export (`migration-data/private/wp-export.xml`), `custom_css` post **blockmagchild**, theme templates, live HTML on `danafarberimpact.org`, Drupal `df_impact` theme (`js/main.js`, CSS).

Regenerate CSS excerpts: `python3 drupal/migration-data/mine_wxr_behavior.py`

---

## 1. Homepage hero video

| Attribute | WordPress (WXR / live) | Drupal (Phase 3+ behavior PR) |
| --- | --- | --- |
| **Container** | `.wp-block-cover.alignfull.homepage-banner` | `.hero.hero--front .hero__cover.homepage-banner` |
| **Video selector** | `.homepage-banner video.wp-block-cover__video-background` | `.hero__cover-video` |
| **Source** | `https://danafarberimpact.org/wp-content/uploads/2026/09/Impact_Ambient.mp4` (attachment meta: H.264, 2560×980, ~36s, ~103MB) | Issue **`field_hero_video`** (Media: Video file); editors swap per issue |
| **Poster** | Cover block (no explicit poster in markup; first frame / overlay) | Issue **`field_hero_poster`** (Media: Image); required for `prefers-reduced-motion` |
| **Attributes** | `autoplay`, `muted`, `loop`, `playsinline`; no controls | Same via `<video>` attributes in Twig |
| **Object fit** | `data-object-fit="cover"` / CSS cover | `object-fit: cover; width/height 100%` |
| **Min height** | `min-height: 490px` (cover block inline) | `.hero__cover { min-height: 490px }` |
| **Overlay** | `.wp-block-cover__background` dim 0; white overlay `#FFF` | `.hero__cover-overlay` |
| **Title** | `.homepage-banner .wp-block-cover__inner-container h1` (white, centered) | `.hero__banner-title` — default copy matches WP |
| **Breakpoints** | Full-bleed; CSS in blockmagchild adjusts H1 size at tablet/mobile | Same min-height; title scales in `theme.css` |
| **Reduced motion** | Not explicit in export; best practice: poster + pause | `@media (prefers-reduced-motion: reduce)` hides video, shows poster image |

**JS triggers:** None (native HTML5 autoplay). **Plugin:** core Cover block only.

---

## 2. Sticky / shrinking header

| | WordPress | Drupal |
| --- | --- | --- |
| **Selectors** | `.desktop-header .scrolled`, `.impact-header` | `.header` |
| **Trigger** | Scroll (theme/child JS — class `scrolled` on desktop header) | `Drupal.behaviors.dfImpactHeader` — adds `.header--scrolled` when `scrollY > 50` |
| **Sticky** | `position: sticky` (global styles / blockmagchild) | `position: sticky; top: 0; z-index: 100` (`layout.css`) |
| **Scrolled effect** | Logo / padding shrink (`.scrolled` rules in WP CSS) | `.header--scrolled` — smaller logo max-width, box-shadow |
| **Transition** | `transition: max-width 400ms` (logo, WXR CSS) | `--transition-normal` on header shadow; logo `transition: max-width var(--transition-normal)` |

---

## 3. Navigation hover / focus

| Element | WP selector | WP computed intent | Drupal selector | Drupal CSS |
| --- | --- | --- | --- | --- |
| Primary links | `#mega-menu-max_mega_menu_1 a.mega-menu-link`, `.impact-header a` | Color shift on hover | `.header__nav-link` | `color: #00629b` → hover `#68a0c1`, `transition: color var(--transition-fast)` |
| Issues dropdown | Mega Menu flyout `#mega-menu-… ul.mega-sub-menu` | Hover opens flyout | `.header__nav-item--issues:hover .header__dropdown` | `display: block`; links `#000` → hover bg `#f6f6f6`, color primary |
| Donate CTA | `.homepage-banner-button a` (hover invert) | BG/text swap | `.header__donate` | Primary fill → hover white bg, primary text; `transition: background, color` |
| Search icon | Header search SVG link | Primary color | `.header__search-link` | Same as nav links |
| Footer links | `.copyright a:hover` | Underline / color | `.footer__link:hover` | Primary color |

**Plugin:** Max Mega Menu (`#mega-menu-wrap-max_mega_menu_1`) on WP; Drupal uses custom dropdown (no Mega Menu JS).

---

## 4. Mobile drawer

| | WordPress | Drupal |
| --- | --- | --- |
| **Toggle** | Theme hamburger (block markup in template part) | `.header__menu-toggle` (3 bars) |
| **Panel** | Off-canvas / mobile menu (theme CSS) | `#header-drawer.header__drawer` |
| **Open class** | Theme-specific | `.header__drawer--open` |
| **Animation** | WXR: transform/opacity (theme) | CSS transform slide (`layout.css` @media max-width 1024px) |
| **A11y** | — | `aria-expanded` on toggle; Escape closes (`main.js`) |
| **Duration** | ~300ms (typical; verify on live) | `transition: transform 0.3s ease` |

---

## 5. Scroll reveals / parallax / animations

| Feature | WP | Drupal |
| --- | --- | --- |
| PostX (Ultp) blocks | Scroll animations on grids | Not ported; static Views |
| Lazy load images | WP lazyload / Jetpack (`lazyload`, `data-src`) | Migrated bodies normalized; native `loading="lazy"` on theme images |
| Parallax | Not identified in WXR home template | None |
| Cookie/consent | Cookiebot / OneTrust (blocked in parity tests) | Same third-party if enabled |

---

## 6. Embeds & search

| | WordPress | Drupal |
| --- | --- | --- |
| YouTube/Vimeo | `.wp-block-embed` — max-width 800px (global styles) | `.media-oembed` / paragraph video embed |
| Search | WP search + SearchStax plugin UI | `df_searchstax` module — `/search` route, filters in JS (`df_searchstax.js`) |
| **Search interaction** | Facets, instant results (plugin) | SearchStax autocomplete + issue/category filters — **behavior parity TBD** |

---

## 7. Carousels / sliders / accordions

| | WordPress | Drupal |
| --- | --- | --- |
| Issue/article carousels | PostX pagination `.ultp-pagination-wrap a:hover` | Views pager — no carousel |
| In Brief | Static lists | Grouped by issue (`in-brief-listing-by-issue.html.twig`, `in-brief-page.js`) |
| Accordions | Rare in export | None on homepage |

---

## 8. Plugin-driven scripts (inventory)

| Plugin / area | Purpose | Parity note |
| --- | --- | --- |
| Max Mega Menu | Primary nav flyouts | Replaced by Drupal custom dropdown |
| PostX (Ultp) | Layout grids, pagination hover | CSS tokens mined; JS animations not replicated |
| Yoast SEO | Meta (static parity) | `field_meta_description` |
| Jetpack / lazy-load | Images | Cleaned in migration |
| GTranslate | Header supplement | `gtranslate-init.js` wrapper |
| SiteGround Optimizer / captcha | Bot challenge | Bypass via frozen baseline on Mac |

---

## 9. Behavior harness coverage

Playwright: `parity/tests/behavior-parity.spec.ts`

| Check | Method |
| --- | --- |
| Hero video attrs + playback | `videoState()` — `currentTime` advance after 600ms |
| Hover nav colors / transitions | `computedStyle` vs `:hover` |
| Sticky header at scroll | Screenshot @ scrollY=120; header computed styles |
| Mobile drawer | Toggle → screenshot open state |
| Reduced motion | `prefers-reduced-motion: reduce` emulated on Drupal |

**Owner Mac command:** see [ACCEPTANCE_CRITERIA.md](./ACCEPTANCE_CRITERIA.md#dynamic-behavior).

---

## Match status summary

| Behavior | Match | Notes |
| --- | --- | --- |
| Hero video (autoplay loop) | **Partial → implemented** | Drupal issue fields + front template; needs editor upload of MP4/poster |
| Header sticky + scroll class | **Partial** | Same threshold (50px); WP uses `.scrolled`, Drupal `.header--scrolled` |
| Nav hover colors | **Close** | Mega Menu vs simple dropdown — structure differs |
| Issues dropdown | **Partial** | Hover CSS match; no Mega Menu animation |
| Mobile drawer | **Partial** | Open/close works; fine-tune animation vs WP |
| PostX scroll FX | **No** | Not in scope for static parity |
| Search UX | **No** | Functional SearchStax; interaction parity open |
| Lazy embeds | **Partial** | Article bodies; not hero |

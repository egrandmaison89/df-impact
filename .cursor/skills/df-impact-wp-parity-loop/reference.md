# DF Impact WP Parity — Reference

## Theme file map

Primary surface: `drupal/web/themes/custom/df_impact/`

| Area | Files |
|------|--------|
| Global page shells | `templates/layout/page.html.twig`, `page--front.html.twig`, `html.html.twig` |
| Header / nav | `templates/includes/site-header.html.twig`, `templates/navigation/menu--main.html.twig` |
| Footer | `templates/includes/site-footer.html.twig` |
| Pager | `templates/navigation/pager.html.twig` |
| Article | `templates/node/node--article--full.html.twig`, `node--article--teaser.html.twig` |
| Topic/category archives | `templates/views/views-view--topic-archive.html.twig`, related `views-view-fields--topic-archive*`, `views-view-unformatted--topic-archive*` |
| Homepage sections | `page--front.html.twig` + `templates/views/views-view-*homepage*` |
| Preprocess / vars | `df_impact.theme` (`is_front`, `is_about_impact`, donate/search URLs) |
| Header scroll / menus | `js/main.js` |
| CSS | `css/base.css`, `layout.css`, `components.css`, `theme.css` |
| Libraries | `df_impact.libraries.yml` |

### Known intentional chrome split

In `site-header.html.twig`, when `is_front or is_about_impact`, nav is a hard-coded **Issues** link only; otherwise `page.nav_main` renders the full menu. WP homepage behaves similarly. Parity work must preserve this split unless WP itself changed.

## Host mapping

| Drupal (DDEV) | WordPress |
|---------------|-----------|
| `http://df-impact.ddev.site/` | `https://danafarberimpact.org/` |
| `http://df-impact.ddev.site/category/<slug>` | `https://danafarberimpact.org/category/<slug>/` |
| Paths generally mirror WP aliases; normalize trailing slash when pairing. |

## Viewport presets

| Name | Width × Height |
|------|----------------|
| desktop | 1440 × 900 |
| tablet | 768 × 1024 |
| mobile | 390 × 844 |

## gaps.md template

```markdown
# Parity gaps — <page title / path>

- Drupal: <DRUPAL_BASE_URL>
- WordPress: <WP_BASE_URL>
- Scope: <full|chrome|content|page>
- Date: <ISO date>
- Viewports: <list>

## Summary

| Sev | Count | Notes |
|-----|-------|-------|
| P0  |  |  |
| P1  |  |  |
| P2  |  |  |
| Blocked |  |  |

## Chrome (header / footer / nav)

### G-<id> — <short title>
- Severity: P0|P1|P2
- Type: theme|content|blocked
- WP: <observed>
- Drupal: <observed>
- Evidence: <screenshot filenames / DOM notes>
- Likely files: <paths>
- Status: open|fixed|wontfix|blocked

## Page content

### G-<id> — ...

## Pagination / interaction / motion

### G-<id> — ...

## Out of scope / content mismatches

- ...
```

## plan.md template

```markdown
# Parity plan — <path>

## Approach
<1–3 sentences>

## Ordered work
1. [P0] ... — files: ...
2. [P1] ... — files: ...

## Risks
- Global CSS affecting home/article: ...

## QA routes after changes
- <target>
- http://df-impact.ddev.site/
- <other if chrome/global>
```

## QA checklist

```
QA:
- [ ] Target page desktop matches WP for P0/P1 gaps
- [ ] Target page mobile matches WP for P0/P1 gaps
- [ ] Homepage header: reduced nav (Issues) matches WP
- [ ] Interior header: full nav order/labels/dropdowns match WP
- [ ] Footer columns, links, spacing match on home + interior
- [ ] Sticky/scroll header behavior acceptable vs WP
- [ ] Mobile menu open/close works; no clipped Donate/Search
- [ ] Pagination (if any): numbers, current, next/prev
- [ ] Search + Donate affordances still work
- [ ] Spot-check article or home body not regressing after global CSS
- [ ] No obvious theme JS errors while interacting
```

## Comparison heuristics

When screenshot-diffing:

1. Align sections top-to-bottom; note first divergent band.
2. Measure rough spacing (card gap, title→meta, container width).
3. Check font stack in computed styles via browser CDP/`getComputedStyle` when typography is disputed.
4. Prefer fixing shared chrome once; then page-specific templates.
5. If Drupal markup structure differs but looks identical, prefer CSS-only; if IA differs (missing nav item), fix Twig/menu config.

## What not to “fix”

- Editorial content differences (article body copy, image crops from migration).
- Third-party widgets that WP loads and Drupal intentionally omits (unless already in theme).
- Admin-only Drupal UI.
- Pixel-perfect font raster differences under 1px if metrics and weights match.

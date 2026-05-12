# Path forward — Drupal parity with WordPress Impact Magazine

**Audience:** Engineering and product stakeholders.  
**Companion docs:** [DRUPAL_REBUILD_PLAN.md](../DRUPAL_REBUILD_PLAN.md), [AGENT_HANDOFF.md](AGENT_HANDOFF.md), [ADMIN_UX_OPTIONS.md](ADMIN_UX_OPTIONS.md).  
**Reference site:** [WordPress homepage](https://danafarberimpact.org/homepage/).

This document captures the gap analysis between the legacy WordPress site and the Drupal rebuild, and the phased plan to close those gaps. Implementation status for concrete repo changes is noted inline where applicable.

---

## 1. Objective

Deliver a **reader- and editor-friendly** Drupal site that matches the Impact Magazine experience—especially the homepage and primary reader journeys—while retaining the improved content model (structured issues, bylines, homepage placement, hierarchical topics).

---

## 2. Current strengths- Custom theme **`df_impact`** with homepage sections, article/issue/browse templates, paragraphs, header (search + Donate), and footer logos.
- Content model aligned with the rebuild plan: Article, Issue, In Brief, Page; taxonomies; Views for homepage blocks, issues, in-brief, topics, editorial tooling.
- Migration pipeline (`drupal/migration-data/process_wp_data.py`, `df_migrate`) for large-scale import, redirects, and ongoing HTML/media fixes.

---

## 3. Gap summary (WordPress vs Drupal)

| Area | WordPress / plan intent | Drupal repo (pre-fix) |
|------|-------------------------|------------------------|
| Homepage order & copy | H1 “Stories…”, Explore latest issue, featured row, Digital Exclusives, Recent Highlights | Different H2 order; no explore-latest; “View all” pointed at `/in-brief` |
| Hero | Full-width collage | `hero` region existed but no exported block |
| Digital Exclusives | Category + tags | Channels taxonomy (`field_channels`), Views alter for stable filtering |
| Article URLs | Plan: `/stories/...` | `articles.json` aliases were `/slug`; redirects targeted `/stories/...` |
| Front page | Real homepage | `system.site.page.front` was `/node` (invalid UX) |
| Search | Working search page | Theme linked to `view.df_impact_search` **missing from config sync** |
| Related stories | Auto by topic | Only manual `field_related_articles` in template |
| Issue page | Dark grid area | Partial styling vs spec |
| Translation / mega menu / GTM | WP plugins | Deferred or partial |

---

## 4. Phased plan

### Phase A — Foundations (parity-critical)

1. **Digital Exclusives** — Channels vocabulary (`Digital Exclusives` term); homepage + archive Views filter `field_channels` (applied in `df_setup.module` because term IDs are environment-specific).
2. **Article URLs** — Emit `/stories/{slug}` from the Python pipeline; keep redirects consistent. *(Implemented in `process_wp_data.py`; re-run script to regenerate JSON.)*
3. **Front page** — **`df_setup`** module creates a **Home** page at **`/home`** and sets the front path; **`system.site.yml`** ships with `page.front: /home`. Run `drush cim` so the module installs.
4. **Search** — Export Search API server + index into `files/sync` and add `views.view.df_impact_search`. *(Implemented.)*
5. **Homepage layout** — Reorder sections to match WordPress; add “Explore the Latest Issue” (`browse_issues` block display); fix DE “View all” to `/digital-exclusives`. *(Implemented.)*
6. **Digital exclusives archive** — View page at `/digital-exclusives` filtered by Digital Exclusives channel (taxonomy parity with WP category archive). *(Implemented.)*
7. **Bulk sync** — `drush df-migrate:sync-wordpress-paths` updates aliases, homepage placement and **Channels** from `articles.json`, and imports `redirects.json` plus dated `/YYYY/MM/slug` sources. *(Implemented.)*
8. **Default hero** — Front page uses Impact cover imagery when the Hero region has no block. *(Implemented.)*

### Phase B — Article & issue presentation

9. **Automatic “More stories”** — Embed `related_articles` when manual related field is empty. *(Implemented in theme preprocess.)*
10. **Issue page dark grid** — Match `#0f0e17` treatment for listing region; pagination polish. *(Listing band styled; refine as needed.)*
11. **CKEditor + CSS** — Drop cap CSS for `.has-drop-cap` in theme; optional further WP class cleanup in migration or text filter. *(Drop cap CSS shipped.)*

### Phase C — Navigation, translation, analytics

12. Mega menu parity (if required).
13. GTranslate equivalent or Drupal translation; CSP/snippet policy via `df_site_snippets`.
14. GTM, donate UTM parity, HSTS (per environment).

### Phase D — Editorial UX & launch

15. Admin: role-scoped menus + optional login destination ([ADMIN_UX_OPTIONS.md](ADMIN_UX_OPTIONS.md)).
16. QA matrix and redirects crawl (include `/YYYY/MM/slug/` patterns).
17. Post-launch: facets, visual regression, Search Console.

---

## 5. Operational notes

- **Config canonical path:** `drupal/web/sites/default/files/sync/` (see DRUPAL_REBUILD_PLAN Appendix A).
- **After pulling these changes:** Run `ddev drush updatedb -y && ddev drush cim -y && ddev drush updatedb -y && ddev drush cr` (updates run twice around `cim`: first clears legacy homepage placement keys from the DB; second attaches **Channels** after `field_channels` exists).
- Reindex search if needed (`ddev drush sapi-i`). Run `ddev drush df-migrate:sync-wordpress-paths` when `articles.json` / `redirects.json` change; re-run `fetch_wordpress_model.py` / `process_wp_data.py` when WP exports change.
- **Hero image:** The front page shows a **default** Impact cover image when the Hero region is empty; place a block in **Hero** to override.

---

## 6. Implementation log (repository)

| Change | Location / notes |
|--------|------------------|
| `field_channels` + `channels` vocab | `field.*.field_channels.yml`, `taxonomy.vocabulary.channels.yml`; term created by `df_setup` on config import |
| Homepage placement list | `field.storage.node.field_homepage_placement.yml` — Featured / Highlights / None only |
| Homepage DE view | `views.view.homepage_digital_exclusives.yml` — published articles; WHERE clause from `df_setup_views_query_alter()` |
| Current issue block | `views.view.browse_issues.yml` — display `current_issue` |
| DE archive page | `views.view.digital_exclusives_archive.yml` — path `/digital-exclusives`; channel filter via `df_setup_views_query_alter()` |
| Homepage template | `page--front.html.twig` |
| Article aliases | `drupal/migration-data/process_wp_data.py` → `/stories/{slug}` |
| Search in sync | `search_api.server.*`, `search_api.index.df_impact_content.yml`, `views.view.df_impact_search.yml` |
| Related articles fallback | `df_impact.theme` + `node--article--full.html.twig` |
| Core extensions | `core.extension.yml` — includes `df_setup`, `search_api_db`, migrate stack, `df_migrate`, `facets`, `seckit`, `google_tag` |
| Front page + Home node | `df_setup` module, `system.site.yml` → `page.front: /home` |
| Path / redirect sync | `drush df-migrate:sync-wordpress-paths` |
| Default hero | `page--front.html.twig` + `theme.css` (`.hero__default`) |
| Drop cap (migrated HTML) | `base.css` (`.has-drop-cap`) |
| Donate UTM (header) | `page.html.twig`, `page--front.html.twig`; menu skip uses substring match in `menu--main.html.twig` |
| Photon CDN images | `InlineImageHtmlProcessor::isWordPressImageUrl()` — `i0.wp.com` URLs with site name |

---

*Last updated with engineering changes in-repo; keep this section aligned with shipped config.*

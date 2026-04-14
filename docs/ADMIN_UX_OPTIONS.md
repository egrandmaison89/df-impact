# Administrative experience — options for stakeholders

This note compares practical ways to organize the Drupal back end for **non-technical editors** versus **technical / site owners**, without changing the public site. It complements the editorial how-to in [DRUPAL_REBUILD_PLAN.md §5.5](../DRUPAL_REBUILD_PLAN.md).

## Why the admin can feel “busy”

Drupal ships a full **site toolkit** (content, structure, appearance, extend, configuration). Many contributed modules add their own menu items. That is normal for a rebuild: some items are **launch-critical**, others are **power-user or future** features.

### Module inventory (typical enabled set)

Interpretation guide for conversations with stakeholders:

| Module | Why it exists | Typical editor sees it? |
|--------|----------------|-------------------------|
| **Gin** | Modern admin theme | Yes (entire `/admin` UI) |
| **Admin Toolbar** (+ Tools) | Faster navigation | Yes (top bar) |
| **Paragraphs** | Structured story components | Yes (when editing articles) |
| **Media / Media Library** | Images and files | Yes |
| **Content Moderation** + **Workflows** | Draft / review / publish | Yes (state on content forms) |
| **Scheduler** | Schedule publish / unpublish | Optional (if editors need it) |
| **Pathauto** | Automatic URLs | Rarely (mostly automatic) |
| **Redirect** | Legacy URL handling | No (config/reporting only) |
| **Metatag** + **Simple Sitemap** | SEO | Rarely (defaults + specialist edits) |
| **Search API** (+ DB backend) | Full-text search | No (indexing is automatic) |
| **Facets** | Filtered search UI | Deferred post-launch per engineering standup |
| **Google Analytics** | Traffic | Configuration only |
| **Editoria11y** | Accessibility checks for editors | Reports / notifications |
| **Field Group** | Cleaner content forms | Yes (grouped fields only) |
| **Linkit** | Link picker in editor | Yes (inside CKEditor) |
| **Focal Point** | Image crop focus | Yes (on image fields) |
| **Crop / Image API Optimize** | Image tooling | Mostly automatic |
| **Social Media Links** | Configurable social icons | If placed in blocks |
| **Twig Tweak** | Theme utilities | No |
| **Config Split** | Environment-specific config | No |
| **Stage File Proxy** (non-prod) | Remote file fallbacks | No |
| **Migrate / df_migrate** (if enabled) | One-time or delta imports | No (developer only) |
| **df_site_snippets** | Header/footer HTML for trusted users | Configuration → Site snippets |

Anything editors do not need for daily work can be **hidden by permission** without removing the module (see Option A).

---

## Option A — Role-scoped Drupal (recommended baseline)

**Idea:** Keep the current Gin + Admin Toolbar experience. Tighten **permissions** so the **Editor** / **Content editor** roles only see Content, Media, and a short list of approved configuration screens.

**Pros:** Lowest cost; familiar upgrade path; easy to adjust per role.  
**Cons:** Still “Drupal admin,” not a fully custom product.

**Typical permission strategy**

- **Editor:** Node: create / edit own content for allowed types; use editorial transitions; access Media; view (not configure) Views dashboards intended for editors.
- **Technical / Site owner:** Retain broader configuration; grant **Administer site snippets** only to people allowed to paste scripts (see [DRUPAL_REBUILD_PLAN.md §5.6](../DRUPAL_REBUILD_PLAN.md)).

Optional contrib to reduce clutter: menu-per-role tools or a **Simplify**-style module (evaluate compatibility with your Drupal branch before committing).

---

## Option B — Dashboard-first entry

**Idea:** After login, send users **straight to a task screen** (for example **Content → Editorial Dashboard** or `/admin/content`) instead of the generic **Manage** overview.

**Implementation sketch (engineering):** `login_destination` or a small custom route subscriber that only applies to selected roles.

**Pros:** Editors land on work; fewer accidental clicks into **Extend** or **Structure**.  
**Cons:** Power users may want a different default; needs clear “full admin” link for site owners.

---

## Option C — Separate “console” or headless admin (usually overkill)

**Idea:** Build or buy a thin administrative UI (or use decoupled tools on top of JSON:API) so editors never see core Drupal menus.

**Pros:** Maximum control over UX and wording.  
**Cons:** Highest build and maintenance cost; duplicates permissions, workflows, and accessibility work Drupal already provides.

**When it makes sense:** Large program with dedicated product/engineering capacity, not typical for a magazine editorial team.

---

## Frontend local tasks (editor strip)

When a user with **View / Edit / Delete** (and similar) permissions browses **published** content on the public site (`df_impact` theme), Drupal shows **primary and secondary local tasks** in the theme’s **Highlighted** region—the familiar **View · Edit · Delete · Revisions** links.

**Implementation (engineering):**

- Twig: [`drupal/web/themes/custom/df_impact/templates/navigation/menu-local-tasks.html.twig`](../drupal/web/themes/custom/df_impact/templates/navigation/menu-local-tasks.html.twig) and [`menu-local-task.html.twig`](../drupal/web/themes/custom/df_impact/templates/navigation/menu-local-task.html.twig) add tab markup and accessibility (`<nav>`, `aria-labelledby`) so they match the tab styles in `df_impact` CSS.
- Styling: [`drupal/web/themes/custom/df_impact/css/base.css`](../drupal/web/themes/custom/df_impact/css/base.css) (tabs, secondary row, delete accent) and [`layout.css`](../drupal/web/themes/custom/df_impact/css/layout.css) (`.highlighted` strip).
- Preprocess: `df_impact_preprocess_menu_local_task()` in [`df_impact.theme`](../drupal/web/themes/custom/df_impact/df_impact.theme) flags the **Delete** task for subtle destructive styling.

**Product note:** Full content forms and `/admin` use **Gin**; this strip is only the **in-context** shortcut bar on the frontend. Block placement remains in config export (for example `block.block.df_impact_primary_local_tasks`).

---

## Recommendation summary

| Stakeholder goal | Suggested path |
|------------------|----------------|
| Reduce fear and mistakes for writers | **Option A** + training + Editorial Dashboard bookmark |
| Strongest guardrails without custom product | **Option A** + **Option B** default landing |
| Fully branded, minimal admin | **Option C** only after budget and roadmap sign-off |

---

## Related documentation

- Engineering status: `standups/STANDUP.md` (local-only; folder gitignored)
- Full project spec and ADRs: [DRUPAL_REBUILD_PLAN.md](../DRUPAL_REBUILD_PLAN.md)
- Operational pointers: [AGENT_HANDOFF.md](AGENT_HANDOFF.md)

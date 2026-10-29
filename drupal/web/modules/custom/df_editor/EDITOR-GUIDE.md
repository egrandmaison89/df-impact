# Impact web editor guide

This guide is for **web editors** publishing a new magazine issue and its stories. You do **not** need site admin access for the normal launch flow.

## What you can change

- Create and edit **issues** and **articles**
- Set **Listing excerpt** on articles (archive/search teasers)
- Publish an issue and **all attached articles in one step** from the Issue dashboard or the issue edit form

The **homepage hero heading**, **hero video**, and **hero poster** are managed by **site admins** only (see below). When a new issue is published, the public homepage headline automatically follows the newest published issue’s hero heading unless a site admin has set a site-wide override.

## What only site admins can change

Web editors cannot open the admin routes below (HTTP 403). Site admins manage global chrome here:

| Area | Where to edit | Notes |
|------|----------------|-------|
| **Header logo** | [Impact site settings](/admin/config/df-impact/site-settings) | Logo URL and optional srcset |
| **Donate button** | Site settings | Label and Jimmy Fund donation URL |
| **Issues menu label** | Site settings | “See all issues” text in the Issues flyout; issue links follow publish order |
| **Translate disclaimer** | Site settings | Info popup next to the language selector |
| **Footer About links, address, disclaimer** | [Footer settings](/admin/config/df-impact/footer-settings) | Three About-column links, address HTML, patient-stories disclaimer |
| **Footer DEI + legal bar** | Footer settings | Inclusion paragraph and copyright / Privacy / Terms / Accessibility HTML |
| **Footer social** | Footer settings | Facebook, X, Instagram URLs |
| **Footer trust badges** | Footer settings | Optional image media for U.S. News and Charity Navigator badges (defaults match migrated WP artwork) |
| **Footer categories** | Structure → Taxonomy → Topics | Category links in the footer follow published topic terms (fixed display order) |
| **Homepage hero** | [Homepage hero](/admin/config/df-impact/homepage-hero) | Optional site-wide heading override (+ optional link); default heading/video/poster from the **newest published issue** (link on the form opens that issue) |
| **Menus & snippets** | Structure → Menus; DF site snippets | `header-primary` menu is reserved for future chrome; Issues flyout is issue-driven |

If you need a homepage **video**, **poster**, **headline**, logo, footer legal copy, or Donate URL updated, ask a site admin.

### GTranslate language list (developers)

The visible language dropdown is **not** editable in the Drupal admin UI. It is defined in theme markup and init JS to match the WordPress reference pack:

- Static `<select>` options: `drupal/web/themes/custom/df_impact/templates/includes/wp-gtranslate-static.html.twig` (included from the WP chrome header).
- Widget settings (default language, wrapper selector): `drupal/web/themes/custom/df_impact/js/gtranslate-init.js` (`window.gtranslateSettings`).

Site admins can change only the **disclaimer text** next to the selector via **Site settings**. Changing which languages appear requires a theme/code update.

## Admin quick reference (site admins)

1. **Configuration → Impact → Site settings** — header logo, Donate, Issues “see all” label, translate disclaimer, shortcut to homepage hero settings.
2. **Configuration → Impact → Footer settings** — footer links, address, disclaimers, DEI paragraph, legal bar, social URLs, trust badge images.
3. **Configuration → Impact → Homepage hero** — optional heading override; link to edit the current issue’s hero **video**, **poster**, and default **heading** field.
4. **Content → Issue dashboard** — publish workflow; publishing changes which issue drives the homepage hero and Issues menu order.

Automated UI checks:

```bash
cd parity && npx playwright test --project=admin-e2e
```

Run three consecutive green passes before merge when changing chrome RBAC or forms.

## Recommended launch workflow

### 1. Create the issue (draft)

1. Go to **Content → Add content → Issue** (`/node/add/issue`).
2. Enter **Title**, **Season**, and **Year** (for example *Winter 2028*).
3. Add **Cover image** and **Banner image** (required). Click **Add media** on each field, choose artwork from the library (or upload), then **Insert selected**.
4. Save as **Draft** (use the sticky **Save** bar at the top if the bottom button is blocked).

Ask a site admin to set the **Homepage hero heading** (and video/poster if needed) on the issue when you are close to launch.

### 2. Create three (or more) articles

1. **Content → Add content → Article** for each story.
2. On the **Content** tab, add **Title**, **Byline** (author name only), body (use **Source editing** if the rich-text area is awkward), and a **Featured image** (**Add media** → **Insert selected** — required).
3. On **Publication & placement**, set **Issue** to your draft issue (type `Title (node ID)`), and add at least one **Topic** tag.
4. On **SEO & Summary**, add **Excerpt** (listing teaser).
5. Save each article as **Draft** (sticky **Save** at the top).

### 3. Preview (optional)

1. Open **Content → Issue dashboard** (`/admin/content/issue-dashboard`).
2. Confirm your draft issue and attached articles appear in the table.
3. Use **Open homepage preview** to see how the site will treat your issue as the “current” issue (preview is only visible to you until publish).

### 4. Publish together

Choose either:

- **Issue dashboard** → confirm **Publish … and all attached articles now**, or
- On the **issue edit** form → **Publish issue and all attached articles**

Drupal publishes the issue and every article assigned to it. Homepage sort order for new articles is assigned automatically — no admin tuning required.

### 5. Verify on the public site

- **Issues** menu: your issue should appear first.
- **Homepage**: hero heading and “Explore the Latest Issue” stories from your issue.
- **`/browse-issues`**: your issue first, cover at 250px width.
- **Issue landing page**: lists all published articles for that issue.

## Automated checks

Developers and CI can run:

```bash
drush df-editor:test-issue-e2e
```

Playwright UI walkthrough (screenshots):

```bash
cd parity && npx playwright test --project=editor-e2e
```

Both create temporary content, assert the same ten checks, and clean up.

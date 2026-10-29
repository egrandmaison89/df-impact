# Site administrator guide — Impact (Drupal)

Site admins manage global chrome (header, footer, donate button), homepage hero media on the **current issue**, and navigation menus. Web editors handle day-to-day stories; this guide is for layout and launch tasks.

## Sign in

1. Open `/user/login` (or use the one-time login link from your administrator).
2. The admin theme is **Gin** (dark toolbar). The public site still uses the WordPress-matched **Impact** theme.

## Roles

| Role | Purpose |
| --- | --- |
| **Site admin** | Header/footer/site settings, menus, homepage hero fields on issues, taxonomy structure |
| **Web editor** | Issues, articles, In Brief, media, topics — not site chrome or blocks |

Assign roles at **People → edit user → Roles**.

## Header and donate button

**Configuration → Impact site settings** (`/admin/config/df-impact/site-settings`)

- **Donate button URL** — Jimmy Fund campaign link for the header CTA.
- **Header logo** — Optional override URL and srcset; leave blank to keep migrated defaults.
- **Main navigation menu** — Machine name of the menu for top links (default `header-primary`).

Edit menu links at **Structure → Menus → Header primary navigation**. Typical items:

- Current Issue (point to the latest issue path)
- Past Issues (`/past-issues`)
- About (`/about`)

The **Issues** flyout is built automatically from **published issues** (newest first). You do not add each issue to the menu manually.

## Footer

**Configuration → Impact site settings → Impact footer settings**

- About column links (About Impact, See All Issues, Jimmy Fund Events)
- Contact address HTML
- Patient stories disclaimer
- Facebook / X (Twitter) / Instagram URLs

Category links in the footer still follow the fixed topic list on the public site (WordPress parity).

## Homepage hero

The homepage cover video and headline come from the **newest published issue** (by year and season).

1. Edit that issue (or create the next issue as a draft first).
2. Open the **Homepage hero** field group:
   - **Homepage hero video** — ambient MP4 (autoplay, muted, loop)
   - **Homepage hero poster** — cover image while video loads
   - **Homepage hero heading** — headline over the video (default: “Stories From Our Community of Support”)
3. After publishing a new issue, the homepage hero and “Explore the Latest Issue” grid update automatically.

**Web editors** can edit hero fields only when they can edit the issue node; hero management is intended for **site admins** coordinating launch.

## Optional: header/footer HTML snippets

Trusted staff with **Administer site snippets** can add tracking or embed HTML at **Configuration → Site snippets**. This is equivalent to deploying code — use sparingly.

## Publishing a new issue (admin view)

See [WEB_EDITOR_GUIDE.md](./WEB_EDITOR_GUIDE.md) for the editorial workflow. Site admins may also use **Publish issue and all attached articles** on the issue edit form when helping with launch.

## Cache

After changing menus or site settings, if the public site looks stale, run **Configuration → Development → Performance → Clear all caches** (or ask engineering to run `drush cr` on Acquia).

# Impact web editor guide

This guide is for **web editors** publishing a new magazine issue and its stories. You do **not** need site admin access for the normal launch flow.

## What you can change

- Create and edit **issues** and **articles**
- Set each issue’s **Homepage hero heading** (the large headline on the public homepage when your issue is the newest published issue)
- Set **Listing excerpt** on articles (archive/search teasers)
- Publish an issue and **all attached articles in one step** from the Issue dashboard or the issue edit form

## What only site admins can change

- **Impact site settings** (`/admin/config/df-impact/site-settings`)
- **Footer settings** (`/admin/config/df-impact/footer-settings`)
- **Homepage hero video and poster** (global chrome on the current issue — `/admin/config/df-impact/homepage-hero`)
- Menus, blocks, and other structural configuration

If you need a homepage video or poster updated, ask a site admin.

## Recommended launch workflow

### 1. Create the issue (draft)

1. Go to **Content → Add content → Issue** (`/node/add/issue`).
2. Enter **Title**, **Season**, and **Year** (for example *Winter 2028*).
3. Fill **Homepage hero heading** — this is the headline visitors see on the homepage when this issue is live.
4. Add **Cover image** and **Banner image** (required). Click **Add media** on each field, choose artwork from the library (or upload), then **Insert selected**.
5. Save as **Draft** (use the sticky **Save** bar at the top if the bottom button is blocked).

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

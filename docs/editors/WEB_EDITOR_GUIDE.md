# Web editor guide — publishing a new issue

This walkthrough covers creating a magazine issue, adding articles, and publishing everything so the header **Issues** menu, homepage hero, and **Browse Issues** page update without manual menu or block edits.

## Before you start

- You need the **Web editor** role.
- You cannot open **Impact site settings**, **Site snippets**, block layout, user accounts, or module screens (you will get “access denied”).

## Step 1 — Create the issue (draft)

1. **Content → Add content → Issue**
2. Fill in **Season**, **Year**, and title (for example “Fall 2026 Issue”).
3. Add **Cover**, **Banner**, and **Homepage hero** fields if you have them (hero video/poster/heading are usually finalized with a site admin).
4. Set **Moderation state** to **Draft**.
5. Save.

## Step 2 — Create articles as drafts

For each story in the issue:

1. **Content → Add content → Article**
2. Write the story in the **Content** tab (title, body, featured image, byline, etc.).
3. In **Publication & placement**, set **Issue** to the issue you created.
4. Use **Show under Digital Exclusives** if the story should appear in that homepage row (independent of the issue).
5. Set **Moderation state** to **Draft**.
6. Save.

Repeat for all articles in the issue.

### Schedule a go-live time (optional)

On the issue or article edit form, use **Publish on** (Scheduler) to pick a date and time. The content will flip to published automatically.

## Step 3 — Submit for review (optional)

If your team uses review before launch:

1. Edit the issue or an article.
2. Set **Moderation state** to **In review**.
3. Save.

A site admin or another editor with publish rights can set **Published** when ready.

## Step 4 — Publish the issue and all articles together

When content is ready to go live:

1. Open the **issue** edit form.
2. Click **Publish issue and all attached articles** (below the moderation widget).

   **Or** set **Moderation state** to **Published** and save — attached articles in draft will also be published automatically.

3. Confirm success messages.

## What updates automatically

After the issue is published:

| Area | Behavior |
| --- | --- |
| Header **Issues** dropdown | Newest published issues appear first (this issue at the top when it is the latest by year/season) |
| Homepage hero | Video, poster, and heading from the newest published issue |
| Homepage article grid | Articles for that issue, ordered by **PostX / homepage sort weight** |
| `/browse-issues` | Issue sort matches year/season (newest first) |

No manual menu or block changes are required.

## In Brief and other content

- **In Brief**: **Content → Add content → In Brief** — same moderation and scheduling as articles.
- **Media**: **Content → Media** — upload images for use in stories.
- **Topics**: assign on articles; creating new topic terms is allowed for web editors when needed.

## Tips

- Use **Content → Editorial dashboard** (if linked in your toolbar) to see drafts and in-review items.
- Use **Revisions** on any node for history and rollback.
- Preview drafts using the moderation preview where available.

## Need site-wide changes?

Header logo, donate link, footer text, and main nav menus are managed by **Site admins** — see [ADMIN_GUIDE.md](./ADMIN_GUIDE.md).

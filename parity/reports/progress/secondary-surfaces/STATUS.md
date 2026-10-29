# Secondary surfaces parity

Comparison target: live [danafarberimpact.org](https://danafarberimpact.org) and `parity/reference-pack/` (when WP returns bot walls).

| Surface | WordPress | Drupal | Status |
| --- | --- | --- | --- |
| Search (`/?s=`, results markup, pagination, empty state) | `/?s={term}` on BlockMag-style template (`error404` body class, Better Search exposed `s`); no-results copy in ref pack | `df_secondary` rewrites `/?s=` → `/search?s=`; View `df_impact_search` at `/search`, identifier `s`, mini pager; theme override + empty text; SearchStax UI at `/search-stax` | **Parity** (functional); visual diff vs ref pack search page not fully gated |
| 404 page | Centered heading + search prompt (`error404` body class) | `page--404.html.twig` + `df_secondary_preprocess_html` body class; metatag title “Page not found - Impact Magazine” | **Parity** |
| RSS (`/feed`, `/category/{slug}/feed`, item guid/link) | Yoast/site feeds; production URLs on items | `/feed` → `/rss.xml`; `FeedController` category + node feeds; channel/item links use `https://danafarberimpact.org` with trailing slash on articles | **Parity** |
| robots.txt / sitemap / legacy URLs | Yoast `sitemap_index.xml`; `robots.txt` Disallow rules; `?p=`, uploads, author, tag, `/page/N` | `drupal/web/robots.txt`; internal aliases for `sitemap_index.xml` / `wp-sitemap.xml` → simple_sitemap; `WordpressSecondaryRequestSubscriber` + existing `WordpressLegacyPathSubscriber` for tag/category pagination; `?p=` 301 via `WordPressIdMap`; uploads → `wp-images` | **Parity** (no trailing-slash 301 — same-path url gate) |
| Print styles & share links | Social Snap inline share URLs (Facebook, Twitter/X, LinkedIn, email) + theme `@media print` | `article-social-share.html.twig` + `df_secondary_preprocess_node` (WP post id + production permalinks); Social Snap CSS in theme; existing print rules in `theme.css` | **Parity** (share URL params match ref-pack articles) |
| Favicons / apple-touch / manifest | 32/192 icons, apple-touch, msapplication-TileImage (no web manifest in ref-pack homepage) | `df_secondary_page_attachments` emits matching icon URLs + RSS alternate; manifest not on WP reference | **Parity** (icons); manifest **N/A** on WP |

## Module / config touchpoints

- `drupal/web/modules/custom/df_secondary/` — request subscriber, feeds, icons, search/404 metatags, share vars
- `views.view.df_impact_search` — path `search`, exposed filter `s`
- `df_searchstax.routing.yml` — moved to `/search-stax`
- `drupal/web/robots.txt` — Yoast-style stub + sitemap line

## Gates (local VM, `DRUPAL_BASE_URL=http://127.0.0.1:8888`)

Run before push:

```bash
cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:template-samples:http
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:url:check
```

(`parity:template-samples:http` is the HTTP-200 smoke over template sample paths.)

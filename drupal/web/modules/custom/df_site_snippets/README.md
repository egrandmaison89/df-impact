# DF Site snippets

Configuration-based HTML injected into every public page (and other non-embed HTML responses that use `html.html.twig`).

## Usage

1. Enable: `drush en df_site_snippets`
2. Grant **Administer site snippets** only to trusted roles (Administrators have all permissions by default).
3. Edit: **Configuration → System → Site snippets**

## Security

- Snippet editors can run arbitrary JavaScript for all visitors. Treat the permission like production deploy access.
- If Content-Security-Policy (for example via Seckit) blocks new third-party domains, update policy when adding external scripts.
- Changes are stored in configuration (`df_site_snippets.settings`); export with `drush cex` for version control.

## Cache

Pages depend on the config cache tag `config:df_site_snippets.settings`; saving the form clears affected render cache.

## Theme

The active theme must print the variables in `html.html.twig` (see `df_impact/templates/layout/html.html.twig`).

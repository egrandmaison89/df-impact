# Invocation examples

## Full parity on a category archive (commit at end)

```
Run the df-impact-wp-parity-loop skill:

- DRUPAL_BASE_URL=http://df-impact.ddev.site/category/cancer-research
- WP_BASE_URL=https://danafarberimpact.org/category/cancer-research/
- PARITY_SCOPE=full
```

## Chrome-only (header/footer/nav), no commit

```
Run df-impact-wp-parity-loop:

- DRUPAL_BASE_URL=http://df-impact.ddev.site/
- WP_BASE_URL=https://danafarberimpact.org/
- PARITY_SCOPE=chrome
- SKIP_COMMIT=1
```

Pay special attention to homepage vs interior navigation differences.

## Single interior page body

```
WP parity loop for this article vs WordPress.
Drupal: http://df-impact.ddev.site/path/to/article
WP: https://danafarberimpact.org/path/to/article/
PARITY_SCOPE=content
SKIP_COMMIT=1
```

## Expected agent behavior (abbreviated)

1. Create `.parity/<date>-cancer-research/`.
2. Screenshot WP + Drupal at 1440 and 390; also home headers if scope is `full`/`chrome`.
3. Write `gaps.md` with P0/P1/P2 items (nav, spacing, fonts, pager, etc.).
4. Write `plan.md`, then edit `df_impact` Twig/CSS/JS.
5. Re-screenshot until P0/P1 clear.
6. Run QA checklist (home + interior chrome).
7. Commit unless `SKIP_COMMIT=1`; report paths + remaining gaps.

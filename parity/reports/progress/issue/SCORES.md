# issue — Mac pack (reference-pack viewport.png)

| Path | Viewport Mac desktop | Before smoke median |
| --- | ---: | ---: |
| `/fall-2026-issue` | **60.1%** | 52.6% (smoke aggregate) |
| `/summer-2025-issue` | 71.0% | — |

Chrome medians (fall-2026 pack): desktop **13.1%**, tablet **16.8%**, mobile **19.3%**.

DOM ported: wp-block-cover banner, PostX “In This Issue” grid, Stackable In Brief band, promo ultp rows, shared `page.html.twig` alignfull shell.

Run: `PACK_COMPARE_PATHS=/fall-2026-issue PROGRESS_COMPONENT=issue DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-ref-pack`

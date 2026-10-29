# issue — Mac pack (reference-pack viewport.png)

| Metric | Smoke median | Mac pack template samples (3 issues) |
| --- | ---: | ---: |
| Viewport Mac **desktop** | 52.6% | **71.1%** |
| Viewport Mac **tablet** | — | **61.1%** |
| Viewport Mac **mobile** | — | **53.2%** |
| Chrome **desktop** | — | **12.5%** |

Run: `PARITY_TEMPLATE=issue DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-template`

DOM ported: wp-block-cover banner, PostX “In This Issue” grid, Stackable In Brief band, promo ultp rows, shared `page.html.twig` alignfull shell.

Run: `PACK_COMPARE_PATHS=/fall-2026-issue PROGRESS_COMPONENT=issue DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-ref-pack`

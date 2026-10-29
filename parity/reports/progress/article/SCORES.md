# article — Mac pack samples (2 paths with reference PNGs)

Paths: `parity/config/template-samples.json` → `article`

| Metric | Smoke baseline (25-url) | After lead fix + derivatives + median fix |
| --- | ---: | ---: |
| Viewport Mac median **desktop** | 33.7% | **31.5%** |
| Viewport Mac median **tablet** | 26.1% | **34.4%** |
| Viewport Mac median **mobile** | 27.8% | **28.1%** |
| Chrome median **desktop** | — | **9.5%** |

Run: `PARITY_TEMPLATE=article DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-template`

## 65151098 / 5ebad619 autopsy

- **5ebad619 DOM** (alignleft, meta before figure, caption after body) hurt parity vs WP reference HTML; fixed with export-driven **alignright/alignleft**, figure-first order, **figcaption**, and stripping duplicate Gutenberg lead from body.
- **Duplicate lead** (featured field + body `wp:image`) was a major layout bug; not specific to 5ebad619 scores on couple-commits A/B (~42.4% before vs after DOM-only).
- Reported “regression” vs smoke was inflated by (1) **even-length median bug** (picked high sample) and (2) **Mac pack PNG** reference vs smoke’s live WP captures (couple-commits tablet ~25% smoke vs ~51% Mac-pack).

Per-path (Mac pack): couple-commits desktop ~42.5%, tablet ~51.5%; charlie-landers desktop ~20.6%, tablet ~17.2%.

Next: Social Snap share row, pullquote styling, drop-cap on first paragraph, capture third article reference pack.

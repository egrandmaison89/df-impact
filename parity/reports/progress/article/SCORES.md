# article — Mac pack samples

Paths: `parity/config/template-samples.json` → `article` (newest, oldest, legacy).

| Metric | Smoke baseline (25-url) | After WP DOM + capture stabilize |
| --- | ---: | ---: |
| Viewport Mac median **desktop** | 33.7% | **42.5%** |
| Viewport Mac median **tablet** | 26.1% | **51.5%** |
| Viewport Mac median **mobile** | 27.8% | **33.0%** |
| Chrome median **desktop** | — | **12.6%** |

Run: `PARITY_TEMPLATE=article DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-template`

Next: lead image 363px style, Stackable share block, body `has-drop-cap`, embed/gallery samples.

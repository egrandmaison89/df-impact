# homepage — Mac pack + baseline

**Ref:** `parity/reference-pack/*/viewport.png` (Mac capture), `PARITY_WP_BASELINE=df-impact-2026-10-28`

| Metric | Before (smoke eval) | After WP DOM port |
| --- | ---: | ---: |
| Viewport median Mac **desktop** (`/`, `/homepage`) | 77.3% | **61.1%** |
| Viewport median Mac **tablet** | 61.6% | **49.0%** |
| Viewport median Mac **mobile** | 56.3% | **47.3%** |
| Chrome median **desktop** | — | **12.9%** |
| Chrome median **tablet** | — | **18.7%** |
| Chrome median **mobile** | — | **21.3%** |

PostX card images: `wp_card_870x570` + `width`/`height`/`object-fit:cover` on grid twigs (2026-10-28).

Run: `PACK_COMPARE_PATHS=/,/homepage PROGRESS_COMPONENT=homepage DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-ref-pack`

Smoke baseline: `PARITY_WP_BASELINE=df-impact-2026-10-28 npm run parity:smoke:baseline`

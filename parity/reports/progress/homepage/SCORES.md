# homepage — Mac pack + baseline

**Ref:** `parity/reference-pack/*/viewport.png` (Mac capture), `PARITY_WP_BASELINE=df-impact-2026-10-28`

| Metric | Before (smoke eval) | After WP DOM port |
| --- | ---: | ---: |
| Viewport median Mac **desktop** (`/`) | 77.3% | **61.1%** |
| Viewport median Mac **tablet** | 61.6% | **48.9%** |
| Viewport median Mac **mobile** | 56.3% | **47.5%** |
| Chrome median **desktop** | — | **6.6%** (after capture stabilize) |
| Chrome median **tablet** | — | **17.5%** |
| Chrome median **mobile** | — | **19.8%** |

Run: `PACK_COMPARE_PATHS=/,/homepage PROGRESS_COMPONENT=homepage DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-ref-pack`

Smoke baseline: `PARITY_WP_BASELINE=df-impact-2026-10-28 npm run parity:smoke:baseline`

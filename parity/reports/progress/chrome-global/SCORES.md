# chrome-global parity scores

| Metric | Before (unstyled DDEV / symlink 404) | After (vendored wp_port) |
| --- | ---: | ---: |
| Chrome median desktop | ~18.8% | **11.4%** |
| Chrome median tablet | ~26.1% | **8.2%** |
| Chrome median mobile | ~23.1% | **20.0%** |
| Header desktop (typical) | ~17–22% | **~1.6%** |
| Footer desktop (typical) | ~20–30% | **~21%** |

Run: `DRUPAL_BASE_URL=http://df-impact.ddev.site PROGRESS_COMPONENT=chrome-global npm run parity:compare-ref-pack`

Gate ≤5%: not met (footer + mobile header). See [BLOCKER.md](./BLOCKER.md).

# chrome-global parity scores

## Linux same-renderer (ref-pack server + DDEV, `summary.json`)

| Metric | Before symlink fix (~Mac pack) | After vendored assets | After chrome DOM/logo pass |
| --- | ---: | ---: | ---: |
| Chrome median desktop | ~18.8% | 11.4% (Mac clip) | **13.1%** |
| Chrome median tablet | ~26.1% | 8.2% | **16.2%** |
| Chrome median mobile | ~23.1% | 20.0% | **21.7%** |

Structural ref-vs-Drupal gate: still failing (header rect x≈72, height desktop vs mobile stacks). Footer/header Linux pixel medians ~13–15% desktop.

## Mac frozen viewport (legacy metric)

| Breakpoint | Chrome median |
| --- | ---: |
| desktop | 11.4% |
| tablet | 8.2% |
| mobile | 20.0% |

Run: `DRUPAL_BASE_URL=http://df-impact.ddev.site PROGRESS_COMPONENT=chrome-global npm run parity:compare-ref-pack`

Gate ≤5%: **not met**. See [BLOCKER.md](./BLOCKER.md).

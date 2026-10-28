# Acceptance criteria (WordPress ↔ Drupal parity)

Owner-approved thresholds for release. Measured by `parity/` unless noted.

## Visual

| Check | Threshold | Scope |
| --- | --- | --- |
| Pixel diff (full page) | **Median ≤ 2%** and **p90 ≤ 5%** per template | **Each breakpoint:** 1440, 768, 375 |
| Cookie / animation noise | Harness disables animations and blocks common consent scripts | Applied in all runs |

## Content

| Check | Threshold |
| --- | --- |
| Main-content text similarity | **≥ 98%** (token overlap on parity content region) |
| Page `<title>` | Exact match after normalization |
| Meta description | Exact match |
| Open Graph title, description, image | Exact match when present on WordPress |

## URL parity

| Check | Threshold |
| --- | --- |
| Path parity | **100%** of manifest URLs |
| Drupal response | **HTTP 200** at the **same path** as live WordPress |
| Redirects | **Not allowed** for parity URLs except where WordPress itself redirects (legacy only) |
| New editorial content | Pathauto / hooks produce WordPress-style permalinks (dated posts, `{season}-{year}-issue`, etc.) |

## Validation runs

| Command | Use |
| --- | --- |
| `npm run parity` | **Full manifest** (release gate) |
| `npm run parity:smoke` | Quick smoke (~sample URLs) |

Harness exits non-zero when thresholds fail (`parity/scripts/evaluate-thresholds.mjs`).

## Informational (non-blocking)

- axe critical violations (track trend)
- Lighthouse performance (track LCP)

## Phased implementation

See [PHASE1_ACCEPTANCE_CRITERIA.md](./PHASE1_ACCEPTANCE_CRITERIA.md) for historical Phase 1 proposal; this document supersedes thresholds for Phase 2+.

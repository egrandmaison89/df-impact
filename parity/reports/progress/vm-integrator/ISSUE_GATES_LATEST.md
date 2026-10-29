# Issue landing gates — latest VM snapshot (PR #5 integrator)

Generated after registry update; **record both scorers** on every merge.

| Check | Result |
|-------|--------|
| `verify_df_setup_schema.php` | schema=10032 max_hook=10032, `update_10027_applied` set |
| `parity:url:check` (integrator) | **801/801** |
| `test_issue_fixture_replay.php` | exit 0 (16 issues updated; live WP fetch errors on some rows) |

## Dual text metrics (16 issue URLs)

| Scorer | Comparable | Min text sim | Median | ≥98.9% |
|--------|------------|--------------|--------|--------|
| **`parity:issue-live-text`** | 14/16 | **0.989** | 1.0 | **0 below 98%** |
| **`parity:content:sweep:issues`** | 5/16 | **0.910** (among scored) | — | **0/16 at 98%** |

Report JSON:

- Live text: `parity/reports/progress/issue-landing-parity/issue-live-sweep-1793277108218.json`
- Content sweep: `parity/reports/content-sweep/sweep-1793277108503.json`

**Discrepancy:** Live-text (cached WP HTML + Playwright `extractMainText`) reads healthy while content-sweep (PR #23 extractors + frozen/live mix, many WP timeouts) scores ~0.91 on comparable rows. A **bad hook collision** can drive content-sweep toward **~0.03** while replay/live-text still look fine until DB is inspected — always log **both** numbers.

**PR #23 blocker:** merge only when `parity:url:check` is **801/801** on the content-sweep branch (reported **800/801** on #23).

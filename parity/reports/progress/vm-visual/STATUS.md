# VM visual parity — PR #33 pass4 fixes

**Branch:** `cursor/pass4-live-parity-fixes-2b1b` (rebased onto phase4 @ **`6abb0698`** — integrator convergence `e0a4f1f4`, **#32**, reports `.gitignore`).  
**Schema:** **10035** (no new numbered hooks).  
**Drupal:** `http://127.0.0.1:8888`  
**Updated:** 2026-10-29 (VM re-bootstrap + gates)

---

## Rebase / archive / 404

- Rebased onto **`6abb0698`**, not `8fc2b973`. About convergence stays on **`df_setup_post_update_ensure_about_impact_body`** + idempotent `_df_setup_apply_about_impact_gutenberg_body()` (staff sync + canonical Gutenberg).
- **GivingTuesday-first** restored for `/category/digital-exclusives` and `/tag/digital-exclusives` via live WP refresh → `archive_page_wp_order.json` + updated `reference-pack/category_digital-exclusives/*/rendered.html` (not CLL-first refpack sweep truth).
- Live **`/digital-exclusives/` → 404**; Drupal matches (404 body + `DigitalExclusivesLegacyNotFoundSubscriber`). Baseline: `reference-pack/digital-exclusives/`. `wp-live-404-paths.json` + template-samples gate accept 404 for that path.
- Post-migrate: **`sync_in_brief_export_from_nodes.php`** runs in `_df_setup_run_post_migrate_integrator_convergence()` so export JSON tracks `field_summary` after bootstrap.

---

## Apples-to-apples computed style (same `live-cache` WP refpack)

Same DB, same refpack server; only Drupal code differs (phase4 @ `6abb0698` checkout vs pass4 branch).

**Desktop avg `comparableSum` per template path** (`cs-phase4-vm` vs `cs-pass4-vm`):

| Template | Phase4 HEAD | Pass4 branch | Target |
|----------|------------:|-------------:|-------:|
| **landing_page** | 208 | 208 | ≤ 94 |
| **archive** | 240 | 251 | ≤ 179 |
| **in_brief** | 207 | 212 | ≤ 90 |
| **homepage** | 261 | 260 | hold |
| **issue** | 227 | 227 | hold |
| **article** | 132 | 132 | hold |

Pass4 **holds** homepage/issue/article; landing unchanged; archive/in_brief **not yet below targets** on this harness (refpack desktop sums are higher than integrator pass3 live-cache totals). Harness:

```bash
cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_WP_SOURCE=live-cache REPORT_RUN_ID=cs-pass4-vm \
  npm run parity:computed-style-diff:full:live-cache
# Phase4 column: checkout 6abb0698 drupal custom modules+theme, drush cr, REPORT_RUN_ID=cs-phase4-vm, same command
```

---

## VM DB bootstrap

```bash
sudo mysql -e "DROP DATABASE IF EXISTS df_impact; CREATE DATABASE df_impact ..."
bash scripts/repro/bootstrap-vm.sh   # + df_migrate reinstall if migrations missing after cim
```

Then: `verify_*`, issue page seed, gates below.

---

## Gates (this VM, clean bootstrap 2026-10-29)

| Gate | Result |
|------|--------|
| `drush updatedb:status` | **None pending** |
| `verify_df_setup_schema.php` | **OK** (10035) |
| `verify_df_setup_post_updates.php` | **OK** |
| `parity:smoke:200` | **0** fails |
| `parity:template-samples:http` | **19/19** (404 OK for `/digital-exclusives`) |
| `parity:url:check` | **801/801** |
| `PARITY_CONTENT_SWEEP_NO_LIVE_WP=1 parity:content:sweep` | **794/801** text≥0.98 (7× Spring 2026 in-brief URLs: export vs render extract; phase4-only code on same DB → **792/801**) |
| `parity:issue-live-text:cache` | **15/15** compared, **0** below 0.98 |
| `PARITY_CONTENT_SWEEP_NO_LIVE_WP=1 parity:content:sweep:issues:cache` | **16/16** text≥0.98 |
| `parity:article-variants:samples` | **in progress** on VM (classify done; samples long-running) |

**Merge readiness:** Rebase, DE order/404, bootstrap path, and most gates are green on this VM. Content sweep **794/801** (not 801) and CS desktop targets **not met** on refpack sums; pass4 CS vs phase4 is mixed on archive/in_brief. Re-run `parity:article-variants:samples` locally if the VM run does not finish.

Generated run output under `parity/reports/**` is untracked except this file.

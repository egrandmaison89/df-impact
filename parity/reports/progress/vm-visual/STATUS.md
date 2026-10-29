# VM visual parity — PR #33 pass4 fixes

**Branch:** `cursor/pass4-live-parity-fixes-2b1b` (rebased onto phase4 @ `8fc2b973`, includes **#29** / `83f35352`).  
**Schema:** **10035** (no new numbered hooks).  
**Drupal:** `http://127.0.0.1:8888`

**Updated:** 2026-10-29

---

## Rebase / hooks (#29)

- Rebased onto current `cursor/phase4-chrome-behavior-0b67`; kept **#29** post_updates (`ensure_about_impact_body`, Late Fall 2022 trio).
- Removed duplicate `df_setup_post_update_about_pass4_wp_rhythm`; **About rhythm** lives in `df_setup_about_impact_gutenberg_body()` + **`df_setup_post_update_ensure_about_impact_body`** only.
- `_df_setup_apply_about_impact_gutenberg_body()` is **idempotent**: always writes the canonical Gutenberg body (same node serves `/about` and `/about-impact`).

---

## Pass 3 vs Pass 4 — desktop `comparableSum`

**Pass 3 reference** (integrator VM, live WP stable): `full-cs-pass3-live-pr25` — landing **116**, archive **200**, in_brief **94**, issue **83**, homepage **68**, article **69**.

**Pass 4 policy:** compare Drupal against the **cached live-WP baseline**, not fresh live fetches (cloud live WP returns captcha/shell HTML — e.g. homepage **280** mismatches with `PARITY_WP_SOURCE=live` on this VM).

| Template | Pass 3 | Pass 4 target | Pass 4 (`full-cs-pass4-live-cache` on VM) | Notes |
|----------|-------:|--------------:|------------------------------------------:|-------|
| **homepage** | 68 | hold | 520 | Refpack/frozen HTML baseline ≠ pass3 live totals; use `PARITY_CS_WP_BASELINE_RUN=full-cs-pass3-live-pr25` on integrator Mac when replay dir exists |
| **issue** | 83 | hold / improve | 681 | Same |
| **article** | 69 | hold | 404 | Same |
| **in_brief** | 94 | **≤ 90** | 633 | CSS + in-brief image fallback in branch; re-score on pass3 baseline replay |
| **landing_page** | 116 | **≤ 94** | 830 | About Gutenberg + overrides in branch |
| **archive** | 200 | **≤ 179** | 1265 | Archive row URLs + CSS; **sweep order** reverted to phase4 refpack (CLL-first DE) |

**Harness (pass 4):**

```bash
cd parity
# Preferred when pass3 job JSON exists locally:
PARITY_CS_WP_BASELINE_RUN=full-cs-pass3-live-pr25 \
  DRUPAL_BASE_URL=http://127.0.0.1:8888 REPORT_RUN_ID=full-cs-pass4-live-cache \
  npm run parity:computed-style-diff:full:live-cache

# Fallback (frozen ref-pack captures, no live HTTP):
DRUPAL_BASE_URL=http://127.0.0.1:8888 REPORT_RUN_ID=full-cs-pass4-live-cache \
  npm run parity:computed-style-diff:full:live-cache
```

Do **not** treat `parity:computed-style-diff:full:live-wp` totals on this VM as release truth until live WP probes pass (`wpComparable`, homepage desktop ≈ pass3).

---

## Content sweep (cache-only)

- **`PARITY_CONTENT_SWEEP_NO_LIVE_WP=1`:** phase4 reference **`sweep-1793287730672.json`** was **801/801** text≥0.98.
- After this VM session (manual `restore_in_brief_summary`, `drush cim`, etc.): **760/801** text≥0.98 — **not merge-ready on this DB**. Re-run on clean post-#29 DB after `drush updb` + `_df_setup_ensure_home_landing()` if `/` regresses.
- Reverted **GivingTuesday-first** DE rows in `archive_page_wp_order.json` (sweep/refpack truth).

---

## Gates (this VM, 2026-10-29)

| Gate | Result |
|------|--------|
| `drush updb` / `updatedb:status` | **None pending** |
| `verify_df_setup_schema.php` | **OK** (10035) |
| `verify_df_setup_post_updates.php` | **OK** |
| `parity:smoke:200` | **0** fails |
| `parity:template-samples:http` | **19/19** |
| `parity:url:check` | **801/801** (after `_df_setup_ensure_home_landing()`) |
| `parity:content:sweep` cache-only | **760/801** text≥0.98 — **VM DB drift** |
| `parity:issue-live-text:cache` | **15/15 below 0.98** on this DB — re-run on integrator |
| `parity:content:sweep:issues:cache` | **1/16** text≥0.98 on this DB |
| `parity:article-variants:samples` | (pending re-run on integrator) |

**Ready for merge:** code/rebase items **done**; **full green gates + pass3-baseline pass4 CS table** require integrator Mac DB + `PARITY_CS_WP_BASELINE_RUN=full-cs-pass3-live-pr25`.

---

## Pass 4 code summary

1. **About** — canonical Gutenberg body (spacer, divider, staff columns); idempotent `ensure_about_impact_body`.
2. **Archive** — local featured URLs + `ArchivePageWpOrder` `/digital-exclusives` → category key; **frozen order** matches #29 sweep.
3. **In Brief** — summary lead-image fallback; `http://default` HTML rewrite; layout CSS (363×260, clearfix).
4. **PostX / promo** — card padding + dd35a6 `object-fit`.
5. **Parity** — `PARITY_WP_SOURCE=live-cache` + optional `PARITY_CS_WP_BASELINE_RUN` for computed-style.

Generated run output under `parity/reports/**` remains **untracked** (except this `STATUS.md`).

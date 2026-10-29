# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67`  
**Updated:** 2026-10-28 (ET)  
**Parallel agents:** `cursor/templates-inbrief-archives` (not on remote yet), **PR #6** `cursor/meta-head-behavior-0751` (**merged locally** at `129368e3`, same tip as remote PR branch)

## PR #6 — DDEV verification (2026-10-29)

| Check | Result | Notes |
|-------|--------|--------|
| `ddev drush updatedb -y && cim -y && cr` | OK | No pending updates; config already imported |
| `npm run parity:head:refpack` | **metaMatch 0%** (0/13) | Canonical/og URLs double-prefix prod + DDEV (`https://danafarberimpact.org/http://df-impact.ddev.site/...`); empty `<title>` in scrape — **meta agent to fix**, not Mac visual |
| `npm run parity:url:check` | **98.9%** (792/801) | Report: `parity/reports/url-parity/check-1793235874561.json` |
| `npm run parity:behavior:smoke` | **0/3 pass** | 1 failed (desktop: header element / scroll class), 2 skipped |
| Smoke baseline (25-url) | **38/38 Playwright pass** | Run `2026-10-29T01-04-12-321Z`; eval **metaMatchPercent 0%**, pixel gates fail as before (expected) |
| Mac template regression | **None** | Article viewport median desktop **~31.6%** after verify (unchanged) |

**Push:** Merge already on `origin/cursor/phase4-chrome-behavior-0b67`; no extra merge push (meta not green on DDEV).

## Scope (this agent only)

Article/homepage/issue pixel tune, WP image derivatives, chrome ≤5%, Mac measurement authority.

## Latest scores (Mac pack, DDEV)

| Area | Desktop | Tablet | Mobile |
|------|--------:|-------:|-------:|
| **article** viewport | **31.5%** | 34.4% | 28.1% |
| **homepage** viewport | 61.1% | 49.0% | 47.3% |
| **issue** viewport | **65.6%** ↓ from 71.1% | 53.1% | 45.3% |
| **chrome-global** (13 URLs) | **12.7%** | **17.3%** | **17.9%** ↓ |

Gate **≤5% chrome** not met; mobile chrome improved after footer `wp-images` URL fix.

## Done (recent commits)

- [x] Merged `wp-assets-vendor-cdf8` + `meta-head-behavior-0751` into phase4
- [x] Article lead + card/issue banner image styles; vendored derivatives
- [x] Issue cover: `wp_issue_banner_1024x396`, cover img dimensions/object-fit
- [x] Footer chrome: point badge logos at `sites/default/files/wp-images/*`
- [x] Article first paragraph `has-drop-cap` when body stripped
- [x] `mac-visual/STATUS.md` (this file)

## In progress

- [ ] Smoke baseline (25-url) run `2026-10-29T00-53-07-976Z` — `/tmp/parity-smoke.log`
- [ ] Chrome ≤5%: header font/menu AA; footer badge files vs pack PNG bytes
- [ ] Homepage PostX grids + hero (viewport ~61% desktop)
- [ ] Article couple-commits tablet viewport outlier (~51%)
- [ ] 738-url run — still running alongside smoke; poll latest `parity/reports/runs/*/evaluation.json`

## Next

1. `git pull origin cursor/phase4-chrome-behavior-0b67` + merge cloud PRs when they land  
2. `PARITY_TEMPLATE=* npm run parity:compare-template` after each merge  
3. Smoke: `cd parity && PARITY_WP_BASELINE=df-impact-2026-10-28 npm run parity:smoke:baseline`  
4. Do **not** port in-brief/archives/landing/meta — only verify and pixel-fix templates above

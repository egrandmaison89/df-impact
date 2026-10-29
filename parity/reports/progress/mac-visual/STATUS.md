# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67`  
**Updated:** 2026-10-28 (ET)  
**Parallel agents:** `cursor/templates-inbrief-archives` (not on remote yet), `cursor/meta-head-behavior-0751` (**merged locally**)

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

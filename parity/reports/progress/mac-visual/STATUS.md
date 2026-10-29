# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67`  
**Updated:** 2026-10-28 (ET)  
**Merges:** PR **#6** `meta-head-behavior-0751` (`129368e3`), PR **#8** `templates-inbrief-archives-185b` (`42697ec7`)

## PR #8 — DDEV verification (2026-10-29)

| Step | Result |
|------|--------|
| `ddev drush updatedb -y && cim -y && cr` | OK (no pending updates) |
| `PARITY_TEMPLATE=in_brief` compare | **structural pass**; viewport median Mac **22.5%** desktop / **13.8%** tablet / **21.4%** mobile (listing `/in-brief` only; other sample paths lack Mac ref PNGs) |
| `PARITY_TEMPLATE=archive` compare | **structural pass**; viewport median **46.5%** desktop / **60.9%** tablet / **45.4%** mobile |
| `PARITY_TEMPLATE=landing_page` compare | **structural pass**; viewport median **34.4%** desktop / **41.0%** tablet / **40.6%** mobile |
| Smoke baseline (25-url) | **38/38** Playwright pass; run `2026-10-29T01-11-21-945Z`; **metaMatchPercent 0%** (unchanged; meta agent) |
| Core template regression | **None** — article viewport median desktop **~31.7%** (was ~31.5%) |

## Chrome header fix (same checkpoint)

Nested `.alignfull` under `.desktop-header` was stacking blockmag `-outer` margins (**1584px** vs Mac pack **1440px**). Override in `drupal-overrides.css` restores single bleed; **structuralPackPassAll** now **true** for in_brief, archive, landing_page, article, and chrome-global-mac (13 URLs).

| Area | Desktop chrome | Desktop viewport |
|------|---------------:|-----------------:|
| **chrome-global-mac** | **18.4%** | 44.5% median |
| **in_brief** (`/in-brief`) | 18.5% | 22.5% |
| **archive** | 19.0% | 46.5% |
| **landing_page** | 12.9% | 34.4% |
| **article** | 19.0% | **31.7%** |

Gate **≤5% chrome** still not met (pixel AA); structural chrome rects now align with Mac pack.

## PR #6 — DDEV verification (prior)

| Check | Result | Notes |
|-------|--------|--------|
| `parity:head:refpack` | **metaMatch 0%** (0/13) | Canonical double-prefix — **meta agent** |
| `parity:url:check` | **98.9%** (792/801) | |
| `parity:behavior:smoke` | **0/3 pass** | Desktop header/scroll — **meta/behavior agent** |
| Smoke baseline (earlier) | **38/38** | Run `2026-10-29T01-04-12-321Z` |

## Scope (this agent only)

Article/homepage/issue pixel tune, WP image derivatives, chrome ≤5%, Mac measurement authority. Template **DOM** from PR #8; pixel on in-brief/archive/landing as measured above.

## Latest scores (Mac pack, DDEV) — core templates

| Area | Desktop | Tablet | Mobile |
|------|--------:|-------:|-------:|
| **article** viewport | **31.7%** | 34.2% | 28.3% |
| **homepage** viewport | 61.1% | 49.0% | 47.3% |
| **issue** viewport | 65.6% | 53.1% | 45.3% |
| **chrome-global** | **18.4%** | 17.6% | 18.2% |

## In progress

- [ ] Chrome ≤5% pixel: header/footer AA, mega menu fonts
- [ ] Homepage ~61% viewport — hero + PostX 870×570
- [ ] Archive/landing viewport (content depth vs WP; not structural blockers)
- [ ] 738-url run — poll `parity/reports/runs/*/evaluation.json`

## Next

1. `git pull origin cursor/phase4-chrome-behavior-0b67` after push  
2. Re-run head/behavior when meta agent fixes DDEV base URL  
3. Do **not** implement meta or full archive content ports — measure + chrome pixel only

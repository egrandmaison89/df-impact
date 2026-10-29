# Phase 4 — Chrome, behavior, and baseline scoring

**Branch:** `cursor/phase4-chrome-behavior-0b67` (builds on rebased behavior PR #4)  
**PR #4:** Rebased onto `main` (`99aa1079`) — single behavior commit only; base retargeted to `main`.

---

## 1. Article desktop 15.5% → 19.2% (Phase 2 vs Phase 3 smoke)

| Signal | Phase 2 smoke | Phase 3 smoke | Interpretation |
| --- | ---: | ---: | --- |
| **Pixel median** (article::desktop) | 15.5% | 19.2% | **Not a Drupal article-template regression** |
| **Text median** (article::desktop) | ~47% | **95.9%** | Phase 2 WP side was **captcha/noise** (`wpCaptcha`, HTTP 202) |
| **Theme diff** `32163d04..99aa1079` | — | 1 line in `layout.css` (header padding) | No article CSS changes |

**Conclusion:** The pixel median shift is from **smoke cohort composition** (Phase 3 reached more dated article URLs + archives) and **unstable live WP** in cloud runs—not from article layout regressions. **Release scoring must use frozen baseline** `df-impact-2026-10-28` on the owner Mac (`PARITY_WP_BASELINE` + `PARITY_REQUIRE_BASELINE=1`).

---

## 2. Phase 4 deliverables

| Area | Change |
| --- | --- |
| **Hero video** | `df_setup_update_10021` copies `Impact_Ambient.mp4` + `Fall2026-Cover.jpg` into `public://wp-content/uploads/…`, creates Media, sets latest **Issue** `field_hero_video` / `field_hero_poster` when empty |
| **Header** | WXR `desktop-header` / `impact-header` markup; supplement + disclaimer; Main Nav links; Mega Menu flyout CSS |
| **Mobile drawer** | Height/opacity/transform transition; `body.header-drawer-open` scroll lock |
| **Footer** | WXR class hook `footer-with-three-columns` (DEI + legal already present) |
| **Scroll reveal** | `scroll-reveal.js` + `.df-reveal` (0.6s ease); `prefers-reduced-motion` shows content immediately |
| **View metatags** | `metatag_views` enabled; titles/descriptions on `/issues`, `/browse-issues`, `/past-issues`, `/in-brief` |
| **Baseline scoring** | `parity:smoke:baseline`, `PARITY_REQUIRE_BASELINE=1` guard in `run-parity.mjs` |

---

## 3. Scores (before → after)

**Before (Phase 3 smoke, live WP, captcha-noisy)** — from [PHASE3_REPORT.md](./PHASE3_REPORT.md):

| Template × BP | Pixel median | Text median |
| --- | ---: | ---: |
| article::desktop | 19.2% | 95.9% |
| article::tablet | 27.3% | — |
| article::mobile | 20.2% | 48.5% |
| homepage::desktop | 34.0% | 26.0% |
| issue::desktop | 42.1% | 47.4% |
| archive::desktop | 14.4% | — |

**After Phase 4:** Re-measure on owner Mac with **frozen baseline only** (see §4). Cloud agents should not treat live WP smoke as release truth.

---

## 4. Behavior status

| Behavior | Status |
| --- | --- |
| Hero video attributes + playback | **Pass** after `updatedb` 10021 (or manual media on Issue) |
| Header sticky / logo shrink | **Match** (`.header--scrolled` + `.header-logo.scrolled`) |
| Mega Menu flyout animation | **Closer** (CSS opacity/transform; no Max Mega Menu JS) |
| Mobile drawer animation | **Improved** (Phase 4 CSS/JS) |
| PostX scroll reveal | **Approximate** (IntersectionObserver, same duration/easing class) |
| SearchStax UX | Still partial |

---

## 5. Owner Mac — local update & parity

After merging **#4** then **Phase 4 PR**:

```bash
git pull origin main
ddev start
cd drupal
export DDEV_DB_PORT="$(ddev describe -j | python3 -c "import sys,json; print(json.load(sys.stdin)['raw']['dbinfo']['host_db_port'])")"
DRUSH="./vendor/bin/drush -r web"
${DRUSH} updatedb -y
${DRUSH} en metatag_views -y 2>/dev/null || true
${DRUSH} cim -y
${DRUSH} cr
```

**Frozen baseline static smoke** (after lite zip unpacked to `parity/baselines/df-impact-2026-10-28/`):

```bash
cd parity && npm ci
export PARITY_WP_BASELINE=df-impact-2026-10-28
export DRUPAL_BASE_URL="http://127.0.0.1:$(ddev describe -j | python3 -c "import sys,json; print(json.load(sys.stdin)['raw']['host_webserver_port'])")"
npm run parity:smoke:baseline
```

**Behavior** (live WP optional for video; baseline for static hero JSON when captured):

```bash
export PARITY_BEHAVIOR_HIT_WP=1
npm run parity:behavior:smoke
# or baseline-first Drupal compare:
npm run parity:behavior:baseline
```

Send back: `parity/reports/runs/<runId>/`, `parity/reports/behavior/<runId>/`, and baseline lite zip if updated.

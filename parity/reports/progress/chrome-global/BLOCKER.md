# chrome-global parity gate (≤5% median)

**Run:** `parity/reports/progress/chrome-global/summary.json` (13 pack URLs × 3 widths, Drupal `http://127.0.0.1:8888`).

## Current medians (after vendored wp_port assets under DDEV)

| Breakpoint | Chrome median | Header (typ.) | Footer (typ.) |
|------------|---------------|-----------------|---------------|
| desktop    | **11.4%**     | ~1.6%           | ~21%          |
| tablet     | **8.2%**      | ~2.2%           | ~14–17%       |
| mobile     | **20.0%**     | ~16%            | ~23–27%       |

**Regression fix:** Symlinked `wp-assets` → `parity/reference-pack/assets` 404’d inside DDEV (only `drupal/` is mounted). Vendored copy + derived inline CSS restored WP styles; desktop header now near gate.

Best chrome row: `/summer-2025-issue` tablet **3.9%**. **No global median ≤5% yet** (footer + mobile header).

## Fixes landed in this iteration

- Ported WP header/footer Twig from reference-pack; `wp_port` + `blockmagchild-header-footer.css`; static GTranslate markup; mega menu init + collapsed flyouts for capture.
- Fixed **mobile header DOM nesting** (mobile was inside `.desktop-header`, zero-size at ≤768px).
- Compare harness: all 13 pack slugs (`env -u PACK_COMPARE_PATHS`), header element screenshots with clip fallback, footer element screenshots, `document.fonts.ready`.

## Remaining gap (why ≤5% is not reached yet)

1. **Header ~17–22%:** Max Mega Menu + Montserrat rendering vs frozen `file://` ref HTML (font rasterization, toggle glyph layout). Logo/menu chrome structurally matches; pixel diff is mostly anti-aliasing and 1–2px layout variance.
2. **Footer ~20–30%:** Markup aligned with pack; column spacing and Stackable social buttons still shift ~1 line relative to ref viewport captures.
3. **Measurement:** `pixelmatch` threshold `0.1` with `includeAA: true` treats subpixel text/AA as mismatch; chrome regions are text-heavy.

## Unblock options (product/engineering)

- Capture **both** ref and Drupal in one Playwright flow with the same font files (self-host Google Fonts from pack assets only).
- Optional **SSIM** or slightly higher threshold for chrome-only text regions (explicit policy change).
- Byte-copy footer/header outer HTML from pack per breakpoint (last resort).

Until one of the above, **chrome-global cannot honestly pass ≤5%**; homepage/issue body ports proceed in parallel but full-pack gate remains open.

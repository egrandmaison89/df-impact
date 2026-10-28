# chrome-global parity gate (≤5% median)

**Run:** `parity/reports/progress/chrome-global/summary.json` (13 pack URLs × 3 widths, Drupal `http://127.0.0.1:8888`).

## Current medians (after WP chrome port + compare fixes)

| Breakpoint | Chrome median |
|------------|---------------|
| desktop    | ~18.8%        |
| tablet     | ~26.1%        |
| mobile     | ~23.1%        |

Best single page/chrome/bp: ~11.2% (article desktop). **No page meets ≤5% at all three widths.**

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

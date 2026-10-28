#!/usr/bin/env python3
"""Build docs/PHASE1_GAP_REGISTER.md from parity results + heuristics."""

from __future__ import annotations

import argparse
import json
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
PARITY = REPO / "parity"
DOCS_OUT = REPO / "docs" / "PHASE1_GAP_REGISTER.md"


def load_results(run_id: str) -> dict:
    p = PARITY / "reports" / "runs" / run_id / "results.json"
    if not p.is_file():
        raise SystemExit(f"Missing {p}")
    return json.loads(p.read_text(encoding="utf-8"))


def severity(pixel: float | None, text: float | None) -> str:
    if pixel is None and text is None:
        return "unknown"
    px = pixel or 1.0
    tx = text or 0.0
    if px > 0.35 or tx < 0.5:
        return "critical"
    if px > 0.2 or tx < 0.75:
        return "high"
    if px > 0.08 or tx < 0.9:
        return "medium"
    return "low"


def component_for(template: str, path: str) -> str:
    if "homepage" in path or path in ("/home", "/homepage/"):
        return "homepage / hero"
    if template == "issue":
        return "issue template"
    if template == "article":
        return "article template"
    if template == "in_brief":
        return "in-brief template"
    if template in ("archive", "topic"):
        return "archives / listing"
    if path.startswith("/stories") or path.startswith("/issues"):
        return "navigation / IA"
    return "global chrome (header, footer, nav, typography)"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--run-id", required=True)
    parser.add_argument(
        "--baseline-run-id",
        help="Optional prior run id for before/after template×breakpoint scores",
    )
    args = parser.parse_args()
    data = load_results(args.run_id)
    results = data.get("results") or []
    summary = data.get("summary") or {}

    groups: dict[str, list] = defaultdict(list)
    for r in results:
        if r.get("breakpoint") != "desktop":
            continue
        comp = component_for(r.get("template") or "other", r.get("path") or "")
        groups[comp].append(r)

    gaps = []
    for comp, rows in groups.items():
        pixels = [x["pixelDiffRatio"] for x in rows if isinstance(x.get("pixelDiffRatio"), (int, float))]
        texts = [x["textSimilarity"] for x in rows if isinstance(x.get("textSimilarity"), (int, float))]
        avg_px = sum(pixels) / len(pixels) if pixels else None
        avg_tx = sum(texts) / len(texts) if texts else None
        sev = severity(avg_px, avg_tx)
        gaps.append(
            {
                "component": comp,
                "severity": sev,
                "pages": len(rows),
                "avg_pixel_diff": avg_px,
                "avg_text_similarity": avg_tx,
                "sample_paths": [x.get("path") for x in rows[:5]],
            }
        )

    gaps.sort(key=lambda g: {"critical": 0, "high": 1, "medium": 2, "low": 3, "unknown": 4}[g["severity"]])

    baseline_tb = {}
    if args.baseline_run_id:
        try:
            base = load_results(args.baseline_run_id)
            baseline_tb = (base.get("summary") or {}).get("byTemplateBreakpoint") or {}
        except SystemExit:
            baseline_tb = {}

    lines = [
        "# Parity gap register (auto-generated)",
        "",
        f"Generated: {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')}",
        f"Parity run: `{args.run_id}`",
        "",
        "## Headline scores (desktop)",
        "",
        "```json",
        json.dumps(summary.get("headline") or summary.get("byBreakpoint", {}).get("desktop"), indent=2),
        "```",
        "",
        "## Scores by template × breakpoint",
        "",
        "| Template × BP | Pixel median | Pixel p90 | Text median | Before pixel med | Before text med |",
        "| --- | ---: | ---: | ---: | ---: | ---: |",
    ]

    tb = (summary.get("byTemplateBreakpoint") or {})
    for key in sorted(tb.keys()):
        cur = tb[key]
        before = baseline_tb.get(key) or {}
        px = cur.get("pixelDiffMedian")
        p90 = cur.get("pixelDiffP90")
        tx = cur.get("textSimilarityMedian")
        bpx = before.get("pixelDiffMedian")
        btx = before.get("textSimilarityMedian")
        fmt = lambda v: f"{(v or 0)*100:.1f}%" if v is not None else "n/a"
        lines.append(
            f"| `{key}` | {fmt(px)} | {fmt(p90)} | {fmt(tx)} | {fmt(bpx) if bpx is not None else '—'} | {fmt(btx) if btx is not None else '—'} |"
        )

    lines.extend(
        [
            "",
            "## Ranked gaps (by shared component)",
            "",
            "| Severity | Component | Pages (sampled) | Avg pixel diff | Avg text similarity | Evidence | Proposed fix |",
            "| --- | --- | ---: | ---: | ---: | --- | --- |",
        ]
    )

    fix_hints = {
        "global chrome (header, footer, nav, typography)": "Align DF Impact theme tokens, menu block placement, snippet HTML; single CSS pass on header/footer partials.",
        "homepage / hero": "Match front page block layout, hero region, featured story placement vs WP homepage template.",
        "issue template": "Issue node display mode, cover image field, article grid View; verify issue term/tag mapping.",
        "article template": "Body format filters, byline/credit fields, featured image styles, related issue link.",
        "in-brief template": "Compact card layout, image aspect ratio, issue placement field.",
        "archives / listing": "Views pagination, filters, infinite scroll vs WP grid plugin.",
        "navigation / IA": "Path aliases, redirects, main menu structure from WP menus.",
    }

    for g in gaps[:25]:
        px = f"{(g['avg_pixel_diff'] or 0)*100:.1f}%" if g["avg_pixel_diff"] is not None else "n/a"
        tx = f"{(g['avg_text_similarity'] or 0)*100:.1f}%" if g["avg_text_similarity"] is not None else "n/a"
        paths = ", ".join(f"`{p}`" for p in g["sample_paths"][:3])
        fix = fix_hints.get(g["component"], "Triage per template after visual diff review.")
        lines.append(
            f"| {g['severity']} | {g['component']} | {g['pages']} | {px} | {tx} | {paths}; see `parity/reports/latest-report.html` | {fix} |"
        )

    lines.extend(
        [
            "",
            "## Content model & editor experience (static review)",
            "",
            "- **Latest issue on live WP:** Spring 2026 content exists in committed `articles.json` / `in_brief.json`; fresh migrate import created `Spring 2026` issue node locally. Re-run `fetch_wordpress_model.py` on a machine with WP cookies before production cutover.",
            "- **Media migration:** `df_media_images` failed in clean bootstrap (853 errors); inline images rely on committed `files/wp-images/` and remote URLs. Editors need reliable media library + focal point workflow.",
            "- **End-to-end issue publish:** Issue is a node + articles reference issue placement fields — verify editorial checklist in `DRUPAL_REBUILD_PLAN.md` §editorial; no automated test yet.",
            "- **Forms:** No public submission forms identified on WP templates (search + navigation only). Drupal contact/webform modules not required for parity.",
            "",
            "## Owner actions",
            "",
            "- Provide periodic **SQL dump** OR browser **cookies.txt** for SiteGround so CI/agents can refresh manifest and migration JSON.",
            "- Confirm whether **SearchStax** keys are needed for local parity (search template optional).",
            "",
        ]
    )

    DOCS_OUT.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {DOCS_OUT}")


if __name__ == "__main__":
    main()

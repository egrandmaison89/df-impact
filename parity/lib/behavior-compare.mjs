/** Compare WP vs Drupal dynamic behavior snapshots. */

export function mediaBasename(url) {
  if (!url || typeof url !== 'string') return '';
  try {
    const u = new URL(url, 'https://danafarberimpact.org');
    return decodeURIComponent(u.pathname.split('/').pop() || '');
  } catch {
    return url.split('/').pop() || '';
  }
}

export function parseRgb(color) {
  if (!color || typeof color !== 'string') return null;
  const m = color.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/);
  if (!m) return null;
  return [Number(m[1]), Number(m[2]), Number(m[3])];
}

/** Colors within ~2/255 per channel (subpixel / rounding). */
export function colorsClose(a, b, tolerance = 3) {
  const ra = parseRgb(a);
  const rb = parseRgb(b);
  if (!ra || !rb) return a === b;
  return ra.every((v, i) => Math.abs(v - rb[i]) <= tolerance);
}

export function styleFieldMatch(wpVal, drVal, field) {
  if (wpVal == null && drVal == null) return true;
  if (wpVal == null || drVal == null) return false;
  if (field === 'color' || field === 'backgroundColor') {
    return colorsClose(wpVal, drVal);
  }
  if (field === 'transform') {
    const norm = (t) => (t === 'none' || !t ? 'none' : t);
    return norm(wpVal) === norm(drVal);
  }
  return String(wpVal) === String(drVal);
}

export function compareVideo(wp, dr) {
  const diffs = [];
  if (!wp?.found && !dr?.found) return { ok: true, diffs };
  if (!wp?.found || !dr?.found) {
    diffs.push({ field: 'found', wp: wp?.found, dr: dr?.found });
    return { ok: false, diffs };
  }
  const boolFields = ['autoplay', 'muted', 'loop', 'playsInline', 'controls'];
  for (const f of boolFields) {
    if (wp[f] !== dr[f]) diffs.push({ field: f, wp: wp[f], dr: dr[f] });
  }
  const wpBase = mediaBasename(wp.src);
  const drBase = mediaBasename(dr.src);
  if (wpBase && drBase && wpBase !== drBase) {
    diffs.push({ field: 'src.basename', wp: wpBase, dr: drBase });
  }
  if (wp.poster !== dr.poster && (wp.poster || dr.poster)) {
    diffs.push({ field: 'poster', wp: wp.poster, dr: dr.poster });
  }
  if (wp.objectFit !== dr.objectFit) {
    diffs.push({ field: 'objectFit', wp: wp.objectFit, dr: dr.objectFit });
  }
  return { ok: diffs.length === 0, diffs };
}

export function compareStyles(wp, dr, fields = ['color', 'backgroundColor', 'transform']) {
  const diffs = [];
  if (!wp && !dr) return { ok: true, diffs };
  if (!wp || !dr) {
    diffs.push({ field: 'present', wp: !!wp, dr: !!dr });
    return { ok: false, diffs };
  }
  for (const f of fields) {
    if (!styleFieldMatch(wp[f], dr[f], f)) {
      diffs.push({ field: f, wp: wp[f], dr: dr[f] });
    }
  }
  return { ok: diffs.length === 0, diffs };
}

export function hoverChanged(defaultStyle, hoverStyle) {
  if (!defaultStyle || !hoverStyle) return false;
  return (
    !colorsClose(defaultStyle.color, hoverStyle.color) ||
    !colorsClose(defaultStyle.backgroundColor, hoverStyle.backgroundColor) ||
    defaultStyle.transform !== hoverStyle.transform
  );
}

export function dropdownVisible(panel) {
  if (!panel?.found) return false;
  const disp = String(panel.display || '');
  if (disp === 'none') return false;
  const op = Number.parseFloat(panel.opacity ?? '1');
  if (op <= 0.05) return false;
  // Max Mega Menu often uses visibility:hidden while flyout is positioned.
  if (disp === 'block' || panel.visibility === 'visible') return true;
  return panel.visibility !== 'hidden';
}

export function compareDropdown(wp, dr) {
  const wpOk = dropdownVisible(wp);
  const drOk = dropdownVisible(dr);
  if (wpOk === drOk) return { ok: true, diffs: [] };
  return {
    ok: false,
    diffs: [{ field: 'dropdown.visible', wp: wpOk, dr: drOk, wpRaw: wp, drRaw: dr }],
  };
}

export function compareScrollHeader(wpBefore, wpAfter, drBefore, drAfter) {
  const diffs = [];
  const wpScroll = Boolean(wpAfter?.scrolled);
  const drScroll = Boolean(drAfter?.scrolled);
  if (wpBefore?.found && drBefore?.found && wpScroll !== drScroll) {
    diffs.push({ field: 'scroll.class', wp: wpScroll, dr: drScroll });
  }
  if (drBefore?.found && !drScroll) {
    diffs.push({ field: 'scroll.class.dr', wp: wpScroll, dr: drScroll });
  }
  return { ok: diffs.length === 0, diffs };
}

export function compareReveal(wp, dr) {
  if (!wp && !dr) return { ok: true, diffs: [] };
  const wpCount = wp?.count ?? 0;
  const drCount = dr?.count ?? 0;
  if (wpCount > 0 && drCount === 0) {
    return { ok: false, diffs: [{ field: 'reveal.count', wp: wpCount, dr: drCount }] };
  }
  return { ok: true, diffs: [] };
}

export function compareWidgets(wp, dr) {
  const diffs = [];
  const keys = new Set([...(wp ? Object.keys(wp) : []), ...(dr ? Object.keys(dr) : [])]);
  for (const k of keys) {
    if ((wp?.[k] ?? 0) !== (dr?.[k] ?? 0)) {
      diffs.push({ field: `widgets.${k}`, wp: wp?.[k], dr: dr?.[k] });
    }
  }
  return { ok: diffs.length === 0, diffs };
}

/** Flatten mismatches for JSON report. */
export function buildCrossSiteReport(pathNorm, bp, wpSnap, drSnap) {
  const sections = {};
  const allDiffs = [];

  sections.hero = compareVideo(wpSnap.hero, drSnap.hero);
  sections.navDefault = compareStyles(wpSnap.navDefault, drSnap.navDefault);
  sections.navHover = compareStyles(wpSnap.navHover, drSnap.navHover);
  sections.donateDefault = compareStyles(wpSnap.donateDefault, drSnap.donateDefault);
  sections.donateHover = compareStyles(wpSnap.donateHover, drSnap.donateHover);
  sections.footerHover = compareStyles(wpSnap.footerLinkHover, drSnap.footerLinkHover);
  sections.bodyLinkHover = compareStyles(wpSnap.bodyLinkHover, drSnap.bodyLinkHover);
  sections.issuesDropdown = compareDropdown(wpSnap.issuesDropdown, drSnap.issuesDropdown);
  sections.headerScroll = compareScrollHeader(
    wpSnap.headerBeforeScroll,
    wpSnap.headerAfterScroll,
    drSnap.headerBeforeScroll,
    drSnap.headerAfterScroll,
  );
  sections.reveal = compareReveal(wpSnap.reveal, drSnap.reveal);
  sections.widgets = compareWidgets(wpSnap.widgets, drSnap.widgets);

  for (const [name, result] of Object.entries(sections)) {
    if (!result.ok) {
      allDiffs.push({ section: name, diffs: result.diffs });
    }
  }

  return {
    path: pathNorm,
    breakpoint: bp.label,
    match: allDiffs.length === 0,
    mismatchCount: allDiffs.length,
    mismatches: allDiffs,
    sections,
  };
}

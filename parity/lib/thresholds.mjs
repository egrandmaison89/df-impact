/** Owner-approved parity thresholds (see docs/ACCEPTANCE_CRITERIA.md). */

export const PIXEL_MEDIAN_MAX = 0.02;
export const PIXEL_P90_MAX = 0.05;
export const TEXT_SIMILARITY_MIN = 0.98;

export const BREAKPOINT_LABELS = ['desktop', 'tablet', 'mobile'];

export function normalizeMetaValue(s) {
  return (s || '').replace(/\s+/g, ' ').trim();
}

export function normalizeJsonLd(s) {
  const raw = normalizeMetaValue(s);
  if (!raw) return '';
  try {
    return JSON.stringify(JSON.parse(raw));
  } catch {
    return raw;
  }
}

export function metaMatches(wpMeta, drMeta) {
  if (!wpMeta || !drMeta) return false;
  const n = normalizeMetaValue;
  const wpCanon = n(wpMeta.canonical);
  const drCanon = n(drMeta.canonical);
  const canonOk = wpCanon === drCanon;
  const wpLd = normalizeJsonLd(wpMeta.jsonLd);
  const drLd = normalizeJsonLd(drMeta.jsonLd);
  const jsonOk = wpLd === drLd || (!wpLd && !drLd);
  return (
    n(wpMeta.title) === n(drMeta.title) &&
    n(wpMeta.description) === n(drMeta.description) &&
    n(wpMeta.ogTitle) === n(drMeta.ogTitle) &&
    n(wpMeta.ogDescription) === n(drMeta.ogDescription) &&
    n(wpMeta.ogImage) === n(drMeta.ogImage) &&
    canonOk &&
    jsonOk
  );
}

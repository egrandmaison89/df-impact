/** Owner-approved parity thresholds (see docs/ACCEPTANCE_CRITERIA.md). */

export const PIXEL_MEDIAN_MAX = 0.02;
export const PIXEL_P90_MAX = 0.05;
export const TEXT_SIMILARITY_MIN = 0.98;

export const BREAKPOINT_LABELS = ['desktop', 'tablet', 'mobile'];

export function metaMatches(wpMeta, drMeta) {
  if (!wpMeta || !drMeta) return false;
  const n = (s) =>
    (s || '')
      .replace(/\s+/g, ' ')
      .trim();
  return (
    n(wpMeta.title) === n(drMeta.title) &&
    n(wpMeta.description) === n(drMeta.description) &&
    n(wpMeta.ogTitle) === n(drMeta.ogTitle) &&
    n(wpMeta.ogDescription) === n(drMeta.ogDescription) &&
    n(wpMeta.ogImage) === n(drMeta.ogImage)
  );
}

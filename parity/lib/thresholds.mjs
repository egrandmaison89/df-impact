/** Owner-approved parity thresholds (see docs/ACCEPTANCE_CRITERIA.md). */

export const PIXEL_MEDIAN_MAX = 0.02;
export const PIXEL_P90_MAX = 0.05;
export const TEXT_SIMILARITY_MIN = 0.98;

export const BREAKPOINT_LABELS = ['desktop', 'tablet', 'mobile'];

function decodeHtmlEntities(s) {
  if (!s) return '';
  return s
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#(\d+);/g, (_, code) => String.fromCharCode(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
}

/** Normalize meta strings for comparison (decode entities, strip simple HTML tags). */
export function normalizeMetaValue(s) {
  const decoded = decodeHtmlEntities(s || '');
  const stripped = decoded.replace(/<[^>]+>/g, '');
  return stripped.replace(/\s+/g, ' ').trim();
}

function fieldMatches(expected, actual) {
  const exp = normalizeMetaValue(expected);
  if (!exp) return true;
  return exp === normalizeMetaValue(actual);
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
  const wpCanon = normalizeMetaValue(wpMeta.canonical);
  const drCanon = normalizeMetaValue(drMeta.canonical);
  const canonOk = !wpCanon || wpCanon === drCanon;
  const wpLd = normalizeJsonLd(wpMeta.jsonLd);
  const drLd = normalizeJsonLd(drMeta.jsonLd);
  const jsonOk = !wpLd || wpLd === drLd;
  const wpOg = normalizeMetaValue(wpMeta.ogImage);
  const ogImageOk = !wpOg || wpOg === normalizeMetaValue(drMeta.ogImage);
  return (
    fieldMatches(wpMeta.title, drMeta.title) &&
    fieldMatches(wpMeta.description, drMeta.description) &&
    fieldMatches(wpMeta.ogTitle, drMeta.ogTitle) &&
    fieldMatches(wpMeta.ogDescription, drMeta.ogDescription) &&
    ogImageOk &&
    canonOk &&
    jsonOk
  );
}

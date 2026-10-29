/** Parse head/meta fields used by parity metaMatch + extended offline checks. */

import { normalizeJsonLd } from './thresholds.mjs';

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

export function pickMetaFromHtml(html) {
  const pick = (re) => {
    const m = html.match(re);
    return m ? decodeHtmlEntities(m[1].replace(/\s+/g, ' ').trim()) : '';
  };
  return {
    title: pick(/<title[^>]*>([^<]*)<\/title>/i),
    description: pick(/<meta\s+name=["']description["']\s+content="([^"]*)"/i),
    ogTitle: pick(/<meta\s+property=["']og:title["']\s+content="([^"]*)"/i),
    ogDescription: pick(/<meta\s+property=["']og:description["']\s+content="([^"]*)"/i),
    ogImage: pick(/<meta\s+property=["']og:image["']\s+content="([^"]*)"/i),
    canonical: pick(/<link\s+rel=["']canonical["']\s+href=["']([^"']*)["']/i),
    robots: pick(/<meta\s+name=["']robots["']\s+content=["']([^"']*)["']/i),
    ogType: pick(/<meta\s+property=["']og:type["']\s+content=["']([^"']*)["']/i),
    ogUrl: pick(/<meta\s+property=["']og:url["']\s+content=["']([^"']*)["']/i),
    ogSiteName: pick(/<meta\s+property=["']og:site_name["']\s+content=["']([^"']*)["']/i),
    twitterCard: pick(/<meta\s+name=["']twitter:card["']\s+content=["']([^"']*)["']/i),
    articlePublished: pick(/<meta\s+property=["']article:published_time["']\s+content=["']([^"']*)["']/i),
    articleModified: pick(/<meta\s+property=["']article:modified_time["']\s+content=["']([^"']*)["']/i),
    jsonLd: normalizeJsonLd(pickJsonLd(html)),
  };
}

export { normalizeJsonLd };

function pickJsonLd(html) {
  const m = html.match(
    /<script[^>]*type=["']application\/ld\+json["'][^>]*class=["']yoast-schema-graph["'][^>]*>([\s\S]*?)<\/script>/i,
  );
  if (!m) {
    const m2 = html.match(/<script[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/i);
    return m2 ? m2[1].trim() : '';
  }
  return decodeHtmlEntities(m[1].trim());
}

import * as cheerio from 'cheerio';

const CONTENT_SELECTORS = [
  'main .wp-block-query',
  '.wp-block-query',
  '.page-node__body.wp-block-post-content',
  '.about-impact-page__body',
  '.browse-issues__inner',
  'article.in-brief-post .entry-content',
  'article.post .entry-content',
  '.entry-content.wp-block-post-content',
  '.wp-block-post-content',
  '.entry-content',
  '#main-content',
  'main#main-content',
  'main[role="main"]',
  'main',
];

const REMOVE_SELECTORS = [
  'header',
  'footer',
  'nav',
  '.pager',
  '.wp-block-query-pagination',
  '.ultp-loadmore',
  '.js-in-brief-load-more',
  '.related-posts',
  '.share-links',
  '.social-share',
  '.ss-inline-share-wrapper',
  '.wp-block-jetpack-related-posts',
  '#jp-relatedposts',
  '.sharedaddy',
  'script',
  'style',
  'noscript',
  '.gtranslate_wrapper',
];

export function isChallengeHtml(html, url = '') {
  const len = html.length;
  const lower = html.toLowerCase();
  if (
    /_incapsula|incapsula|cf-browser-verification|bot verification|access denied|please enable javascript/i.test(
      lower,
    )
  ) {
    return true;
  }
  if (
    len < 8000 &&
    !/entry-content|wp-block-post-content|ultp-block-items-wrap|wp-block-query|archive|search-form/i.test(
      html,
    )
  ) {
    return true;
  }
  if (len < 15000 && /lighthouse|performance audit/i.test(lower) && !/entry-content/i.test(html)) {
    return true;
  }
  return false;
}

function pickRoot($) {
  const query = $('main .wp-block-query, .wp-block-query').first();
  if (query.length && query.find('.wp-block-post, li.wp-block-post').length >= 2) {
    return query;
  }
  const aboutBody = $('.about-impact-page__body, .page-node__body').first();
  if (aboutBody.length && (aboutBody.text() || '').replace(/\s+/g, ' ').trim().length > 80) {
    return aboutBody;
  }
  const entry = $('article.post .entry-content, .entry-content.wp-block-post-content').first();
  if (entry.length && (entry.text() || '').replace(/\s+/g, ' ').trim().length > 80) {
    return entry;
  }
  for (const sel of CONTENT_SELECTORS) {
    const el = $(sel).first();
    if (el.length && (el.text() || '').replace(/\s+/g, ' ').trim().length > 40) {
      return el;
    }
  }
  return $('body');
}

function stripIssueDeckParagraphs($, clone) {
  clone.find('p').each((_, el) => {
    const p = $(el);
    const link = p.find('a[href]').first();
    const href = link.attr('href') || '';
    const linkText = link.text().replace(/\s+/g, ' ').trim();
    const paraText = p.text().replace(/\s+/g, ' ').trim();
    const isIssueLink =
      /\/issues\//i.test(href) ||
      /-(spring|summer|fall|winter)-\d{4}-issue/i.test(href) ||
      /(spring|summer|fall|winter)\s+\d{4}/i.test(linkText);
    if (isIssueLink) {
      p.remove();
      return;
    }
    if (/^by\s+/i.test(paraText) && paraText.length < 160) {
      p.remove();
    }
  });
}

function cloneRoot($, root) {
  const clone = root.clone();
  REMOVE_SELECTORS.forEach((sel) => {
    clone.find(sel).remove();
  });
  clone.find('.is-collapsed, [hidden], [aria-hidden="true"]').remove();
  stripIssueDeckParagraphs($, clone);
  clone.find('.wp-block-post-terms, .byline, .article__byline').remove();
  return clone;
}

function normalizeText(s) {
  let t = (s || '')
    .replace(/\u00a0/g, ' ')
    .replace(/['']/g, "'")
    .replace(/[""]/g, '"')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
  t = t.replace(
    /this page could not be found\.?\s*maybe try a search\?/gi,
    'search landing empty state',
  );
  t = t.replace(
    /sorry, but nothing matched your search terms\.?\s*please try again with some different keywords\.?/gi,
    'search landing empty state',
  );
  return t;
}

/** Block-level join avoids cheerio .text() gluing adjacent card titles (listing grids). */
function structuredMainText($, root) {
  const blocks = root.find(
    'p, h1, h2, h3, h4, li, figcaption, blockquote, .ultp-block-item, .wp-block-post-title, .ultp-block-title, .wp-block-column, .stk-block-column',
  );
  const chunks =
    blocks.length > 0
      ? blocks
          .map((_, el) => $(el).text().replace(/\s+/g, ' ').trim())
          .get()
          .filter(Boolean)
      : [root.text().replace(/\s+/g, ' ').trim()];
  return normalizeText(chunks.join(' '));
}

function basenameFromSrc(src) {
  if (!src) return '';
  try {
    const u = src.startsWith('http') ? new URL(src) : { pathname: src };
    const base = u.pathname.split('/').pop() || '';
    return base.replace(/\?.*$/, '').replace(/-\d+x\d+(\.[a-z]+)$/i, '$1');
  } catch {
    return src.split('/').pop()?.split('?')[0] || '';
  }
}

export function extractContentMetrics(html) {
  const $ = cheerio.load(html);
  const root = pickRoot($);
  const cleaned = cloneRoot($, root);
  const text = structuredMainText($, cleaned);
  const h1 = normalizeText(root.find('h1').first().text());
  const title = normalizeText($('title').first().text()).replace(/\s*\|\s*impact.*$/i, '');
  const publishDate =
    $('meta[property="article:published_time"]').attr('content') ||
    $('time[datetime]').first().attr('datetime') ||
    normalizeText($('.posted-on, .entry-date, .article__date').first().text());
  const author = normalizeText(
    $('[rel="author"], .author, .byline, .article__byline, .wp-block-post-author-name').first().text(),
  );
  const categories = [
    ...new Set(
      root
        .find('.wp-block-post-terms a, .category a, .article__category a, a[rel="category tag"]')
        .map((_, el) => normalizeText($(el).text()))
        .get()
        .filter(Boolean),
    ),
  ].sort();
  let featuredSrc = '';
  const feat =
    root.find('.wp-block-post-featured-image img').first().attr('src') ||
    root.find('article.post img').first().attr('src') ||
    root.find('.ultp-block-item:first-child img').first().attr('src');
  if (feat) featuredSrc = basenameFromSrc(feat);
  const inlineImages = root.find('img').length;
  const embeds =
    root.find('iframe, video, .wp-block-embed, blockquote.twitter-tweet, blockquote.instagram-media').length;
  const links = [];
  root.find('a[href]').each((_, el) => {
    const href = $(el).attr('href') || '';
    if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    links.push(href);
  });
  const shortcodes = (cleaned.html() || '').match(/\[(caption|embed|gallery|audio|video|playlist)[^\]]*\]/gi) || [];
  const wpInternalInBody = links.filter(
    (h) =>
      /danafarberimpact\.org\/wp-/i.test(h) ||
      /^\/wp-content\//i.test(h) ||
      /^https?:\/\/[^/]*danafarberimpact\.org\/wp-content/i.test(h),
  );
  return {
    text,
    textLength: text.length,
    h1,
    title,
    publishDate: normalizeText(publishDate),
    author,
    categories,
    featuredBasename: featuredSrc,
    inlineImages,
    embeds,
    links,
    unrenderedShortcodes: shortcodes.length,
    shortcodeSamples: shortcodes.slice(0, 3),
    wpInternalLinks: wpInternalInBody,
  };
}

export function wordJaccardSimilarity(a, b) {
  const ta = new Set(a.split(/\s+/).filter((w) => w.length > 2));
  const tb = new Set(b.split(/\s+/).filter((w) => w.length > 2));
  if (ta.size === 0 && tb.size === 0) return 1;
  if (ta.size === 0 || tb.size === 0) return 0;
  let inter = 0;
  for (const w of ta) {
    if (tb.has(w)) inter++;
  }
  return inter / (ta.size + tb.size - inter);
}

export function listsEqual(a, b) {
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) {
    if (a[i] !== b[i]) return false;
  }
  return true;
}

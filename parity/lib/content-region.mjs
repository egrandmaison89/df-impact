/**
 * Browser-side main content extraction (string passed to page.evaluate).
 * Keeps WordPress entry content and Drupal #main-content in parity.
 */
export const EXTRACT_MAIN_TEXT_FN = () => {
  const REMOVE_SELECTORS = [
    'header',
    'footer',
    'nav',
    '.header',
    '.footer',
    '.site-header',
    '.site-footer',
    '#header',
    '#footer',
    '.menu',
    '.breadcrumb',
    '.pager',
    '.tabs',
    '.related-posts',
    '.related-articles',
    '.share-links',
    '.social-share',
    'aside',
    '.sidebar',
    '.layout-with-sidebar__sidebar',
    'script',
    'style',
    'noscript',
    '.gtranslate_wrapper',
    '.cookie',
    '#cookie',
    '.wp-block-template-part',
  ];

  const CONTENT_SELECTORS = [
    '.entry-content.wp-block-post-content',
    '.wp-block-post-content',
    '.entry-content',
    'article .entry-content',
    '#main-content .layout-with-sidebar__content',
    '#main-content .page__content',
    'main#main-content',
    '#main-content',
    'main[role="main"]',
    '#primary',
    'main.site-main',
    '.site-content',
    'article.node--article',
    'article.node--in-brief',
    'article.node--issue',
    '.node__content',
  ];

  function cloneWithoutChrome(root) {
    const clone = root.cloneNode(true);
    for (const sel of REMOVE_SELECTORS) {
      clone.querySelectorAll(sel).forEach((el) => el.remove());
    }
    return clone;
  }

  let root = null;
  for (const sel of CONTENT_SELECTORS) {
    const el = document.querySelector(sel);
    if (el && (el.textContent || '').trim().length > 40) {
      root = el;
      break;
    }
  }
  if (!root) {
    root = document.body;
  }
  const cleaned = cloneWithoutChrome(root);
  const headings = [...cleaned.querySelectorAll('h1,h2,h3,h4')].map((h) => ({
    tag: h.tagName.toLowerCase(),
    text: (h.textContent || '').replace(/\s+/g, ' ').trim(),
  }));
  const text = (cleaned.innerText || '').replace(/\s+/g, ' ').trim();
  return { text, headings, length: text.length, selector: root.className || root.id || root.tagName };
};

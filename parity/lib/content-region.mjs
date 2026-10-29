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
    '.wp-block-query-pagination',
    'nav.wp-block-query-pagination',
    '.wp-block-post-excerpt__more-text',
    '.wp-block-post-excerpt__more-link',
    '.ultp-loadmore',
    '.ultp-loadmore-action',
    '.ultp-pagination',
    '.ultp-pagination-wrap',
    '.pagination-block-html',
    '.ultp-block-readmore',
    '.js-in-brief-load-more',
    '.tabs',
    '.related-posts',
    '.related-articles',
    '.share-links',
    '.social-share',
    '.ss-inline-share-wrapper',
    '.ss-social-share-label',
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
    'main:has(.wp-block-query)',
    'main .wp-block-query',
    '.wp-block-query',
    '.page-node__body.wp-block-post-content',
    '.about-impact-page__body',
    '.browse-issues__inner',
    'main .browse-issues',
    'article.in-brief-post .entry-content',
    'article.post .entry-content.alignwide',
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
    // Cloned nodes ignore display:none; drop collapsed archive rows (In Brief load-more).
    clone.querySelectorAll('.is-collapsed, [hidden], [aria-hidden="true"]').forEach((el) => {
      el.remove();
    });
    // PostX grids in static ref HTML omit inter-card whitespace in innerText.
    clone.querySelectorAll('.ultp-block-title, .ultp-block-excerpt, .ultp-block-item').forEach((el) => {
      el.append(document.createTextNode(' '));
    });
    clone.querySelectorAll('.about-impact-page__staff-column p, .about-impact-page__staff-column h3').forEach((el) => {
      el.append(document.createTextNode(' '));
    });
    return clone;
  }

  function isVisible(el) {
    if (!(el instanceof HTMLElement)) {
      return false;
    }
    if (el.closest('.is-collapsed, [hidden], [aria-hidden="true"]')) {
      return false;
    }
    const style = getComputedStyle(el);
    return style.display !== 'none' && style.visibility !== 'hidden';
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
  const headings = [...root.querySelectorAll('h1,h2,h3,h4')]
    .filter(isVisible)
    .map((h) => ({
      tag: h.tagName.toLowerCase(),
      text: (h.textContent || '').replace(/\s+/g, ' ').trim(),
    }));
  // In Brief load-more: live innerText respects display:none on collapsed rows.
  const useLiveInnerText =
    root.isConnected &&
    (root.querySelector('.ultp-block-item.is-collapsed') ||
      root.querySelector('.in-brief-page__issue-section, .view-in-brief-listing'));
  const text = (useLiveInnerText ? root.innerText : cleaned.innerText || '')
    .replace(/\s+/g, ' ')
    .trim();
  return { text, headings, length: text.length, selector: root.className || root.id || root.tagName };
};

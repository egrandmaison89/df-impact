/**
 * @file
 * SearchStax search page initialization for /search.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  const STUDIO_JS = 'https://static.searchstax.com/studio-js/v4.1.55/js/search-ux.js';
  const FEEDBACK_MODULE_JS = 'https://static.searchstax.com/studio-js/v4.1.55/js/feedbackWidget.mjs';

  function randomId(length) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let out = '';
    for (let i = 0; i < length; i++) {
      out += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return out;
  }

  /**
   * Normalize absolute links from the index to the current site origin.
   *
   * Documents use absolute canonical URLs (Solr id / uniqueId), often built
   * with public_base_url or the indexer host. The shared SearchStax index must
   * still open the right path on DDEV, Acquia, or any other hostname.
   */
  function localizeSearchResultUrl(url) {
    if (url == null) {
      return '';
    }
    const s = String(url).trim();
    if (!s) {
      return '';
    }
    try {
      const parsed = new URL(s, window.location.origin);
      if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
        if (parsed.origin === window.location.origin) {
          return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }
        return `${window.location.origin}${parsed.pathname}${parsed.search}${parsed.hash}`;
      }
    }
    catch (e) {
      /* Invalid URL: fall through. */
    }
    if (s.startsWith('/')) {
      return `${window.location.origin}${s}`;
    }
    return s;
  }

  let clientRefineState = {
    kinds: new Set(),
    issues: new Set(),
    categories: new Set(),
    topics: new Set(),
    cancers: new Set(),
  };

  /**
   * Strip tags / highlighting so "Story" matches filter chips (SearchStax snippets).
   */
  function stripHtmlLite(htmlish) {
    return String(htmlish == null ? '' : htmlish).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  /**
   * Unescape entity-encoded ribbon (e.g. &lt;!--df:... from API).
   */
  function decodeHtmlEntitiesBrowser(s) {
    if (typeof document === 'undefined') {
      return String(s || '');
    }
    const t = document.createElement('textarea');
    t.innerHTML = String(s || '');
    return t.value;
  }

  function solrMultiValue(raw) {
    if (raw == null) {
      return [];
    }
    if (Array.isArray(raw)) {
      return raw.map((x) => stripHtmlLite(x)).filter(Boolean);
    }
    const one = stripHtmlLite(raw);
    if (!one) {
      return [];
    }
    return one.split(/\s*,\s*/).filter(Boolean);
  }

  function solrScalar(raw) {
    const v = Array.isArray(raw) ? raw[0] : raw;
    return stripHtmlLite(v);
  }

  function fieldEntryFromResult(result, key) {
    const buckets = [result.allFields, result.unmappedFields].filter(Boolean);
    for (let b = 0; b < buckets.length; b++) {
      const list = buckets[b];
      if (!Array.isArray(list)) {
        continue;
      }
      const hit = list.find((x) => x && x.key === key);
      if (hit && hit.value != null && hit.value !== '') {
        return hit.value;
      }
    }
    return null;
  }

  function ribbonLeadTypeHeuristic(ribbonPlain) {
    const s = String(ribbonPlain || '');
    const seg = s.split(/\s*[·•]\s*/)[0] || s;
    return stripHtmlLite(seg);
  }

  function parseDfRibbonComment(ribbonHtml) {
    if (ribbonHtml == null || ribbonHtml === '') {
      return { display: '', type: '', categories: [], topics: [], cancers: [] };
    }
    let s = decodeHtmlEntitiesBrowser(String(ribbonHtml));
    const m = s.match(/<!--df:([^>]+)-->/);
    if (!m) {
      const lead = ribbonLeadTypeHeuristic(s);
      return {
        display: s.trim(),
        type: lead,
        categories: [],
        topics: [],
        cancers: [],
      };
    }
    const display = s.replace(/<!--df:[^>]+-->/, '').trim();
    const parts = m[1].split('|');
    const topics = [];
    const cancers = [];
    const categories = [];
    let type = '';
    parts.forEach((p) => {
      const idx = p.indexOf(':');
      if (idx === -1) {
        return;
      }
      const k = p.slice(0, idx);
      let v = p.slice(idx + 1);
      try {
        v = decodeURIComponent(v);
      } catch (e) {
        /* ignore */
      }
      if (k === 't') {
        type = v;
      }
      if (k === 'cf') {
        categories.push(v);
      }
      if (k === 'tp') {
        topics.push(v);
      }
      if (k === 'c') {
        cancers.push(v);
      }
    });
    return { display, type, categories, topics, cancers };
  }

  /**
   * Infer Story / Issue / Page / In Brief from canonical URL when Solr has no
   * content_type_s (common with crawler indexes). Matches Pathauto in this site.
   */
  function inferContentKindFromUrl(urlStr) {
    if (urlStr == null || String(urlStr).trim() === '') {
      return '';
    }
    let path = '';
    try {
      path = new URL(String(urlStr).trim(), window.location.origin).pathname || '';
    } catch (e) {
      path = String(urlStr);
    }
    if (!path || path === '') {
      return '';
    }
    path = '/' + path.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean).join('/');
    if (path === '/') {
      return 'Page';
    }
    const lower = path.toLowerCase();
    if (lower.startsWith('/stories/') || lower === '/stories') {
      return 'Story';
    }
    if (lower.startsWith('/issues/') || lower === '/issues') {
      return 'Issue';
    }
    if (lower.startsWith('/in-brief/') || lower === '/in-brief') {
      return 'In Brief';
    }
    return 'Page';
  }

  function applyDfResultFacets(result) {
    const ribbonParsed = parseDfRibbonComment(result.ribbon);
    result.ribbon = ribbonParsed.display;

    const ctypeRaw = fieldEntryFromResult(result, 'content_type_s');
    const acatRaw = fieldEntryFromResult(result, 'article_category_s');
    const topicRaw = fieldEntryFromResult(result, 'topic_ss');
    const cancerRaw = fieldEntryFromResult(result, 'cancer_type_ss');

    let type = (ribbonParsed.type && String(ribbonParsed.type).trim()) || solrScalar(ctypeRaw);
    if (!type) {
      type = ribbonLeadTypeHeuristic(result.ribbon);
    }
    result.dfFacetType = type;

    const catFromRibbon = ribbonParsed.categories[0] ? String(ribbonParsed.categories[0]).trim() : '';
    result.dfFacetCategory = catFromRibbon || solrScalar(acatRaw) || '';

    const topicsRibbon = ribbonParsed.topics.filter(Boolean);
    const topicsSolr = solrMultiValue(topicRaw);
    result.dfFacetTopics = [...new Set([...topicsRibbon, ...topicsSolr])].join(',');

    const cancersRibbon = ribbonParsed.cancers.filter(Boolean);
    const cancersSolr = solrMultiValue(cancerRaw);
    result.dfFacetCancers = [...new Set([...cancersRibbon, ...cancersSolr])].join(',');
  }

  function applyDfUrlFallbackKind(result) {
    if ((result.dfFacetType && String(result.dfFacetType).trim()) !== '') {
      return;
    }
    let candidate = (result.url && String(result.url).trim()) || '';
    if (!candidate && result.uniqueId != null) {
      const uid = String(result.uniqueId).trim();
      if (/^https?:\/\//i.test(uid)) {
        candidate = uid;
      }
    }
    const inferred = inferContentKindFromUrl(candidate);
    if (inferred) {
      result.dfFacetType = inferred;
    }
  }

  let refineDebounceTimer;

  function scheduleApplyClientRefineVisibility() {
    window.clearTimeout(refineDebounceTimer);
    refineDebounceTimer = window.setTimeout(() => {
      applyClientRefineVisibility();
      requestAnimationFrame(() => applyClientRefineVisibility());
    }, 45);
  }

  function bindSearchResultsRefineObserver() {
    const root = document.getElementById('searchstax-search-results');
    if (!root || root.dataset.dfRefineObs === '1') {
      return;
    }
    root.dataset.dfRefineObs = '1';
    const obs = new MutationObserver(() => scheduleApplyClientRefineVisibility());
    obs.observe(root, { childList: true, subtree: true });
  }

  function clientRefineCardMatches(link) {
    const kind = (link.getAttribute('data-df-type') || '').trim();
    const category = (link.getAttribute('data-df-category') || '').trim();
    const topics = (link.getAttribute('data-df-topics') || '').split(',').map((x) => x.trim()).filter(Boolean);
    const cancers = (link.getAttribute('data-df-cancers') || '').split(',').map((x) => x.trim()).filter(Boolean);
    if (clientRefineState.kinds.size && !clientRefineState.kinds.has(kind)) {
      return false;
    }
    if (clientRefineState.categories.size) {
      if (!category || !clientRefineState.categories.has(category)) {
        return false;
      }
    }
    if (clientRefineState.topics.size && !topics.some((x) => clientRefineState.topics.has(x))) {
      return false;
    }
    if (clientRefineState.cancers.size && !cancers.some((x) => clientRefineState.cancers.has(x))) {
      return false;
    }
    return true;
  }

  function applyClientRefineVisibility() {
    const root = document.getElementById('searchstax-search-results');
    if (!root) {
      return;
    }
    const active = clientRefineState.kinds.size > 0
      || clientRefineState.categories.size > 0
      || clientRefineState.topics.size > 0
      || clientRefineState.cancers.size > 0;
    root.querySelectorAll('a.df-impact-search-card-link').forEach((link) => {
      const ok = !active || clientRefineCardMatches(link);
      link.classList.toggle('df-searchstax-refine-hidden', !ok);
      link.setAttribute('aria-hidden', ok ? 'false' : 'true');
    });
    const vis = [...root.querySelectorAll('a.df-impact-search-card-link')].filter((l) => !l.classList.contains('df-searchstax-refine-hidden')).length;
    const status = document.getElementById('df-searchstax-refine-status');
    if (status) {
      const total = root.querySelectorAll('a.df-impact-search-card-link').length;
      if (!active) {
        status.textContent = '';
      }
      else if (!total) {
        status.textContent = Drupal.t('Run a search to filter results.');
      }
      else {
        status.textContent = Drupal.formatPlural(vis, '1 article on this page matches filters.', '@count articles on this page match filters.');
      }
    }
  }

  function syncFilterCheckboxesFromState() {
    const wrap = document.getElementById('df-searchstax-client-facets');
    if (!wrap) {
      return;
    }
    wrap.querySelectorAll('button.df-searchstax-filter__option').forEach((button) => {
      const g = button.getAttribute('data-df-group');
      const v = button.getAttribute('data-df-value') || '';
      let decoded = v;
      try {
        decoded = decodeURIComponent(v);
      } catch (e) {
        /* ignore */
      }
      const set = g === 'issue' ? clientRefineState.issues
        : g === 'kind' ? clientRefineState.kinds
        : g === 'category' ? clientRefineState.categories
          : g === 'topic' ? clientRefineState.topics : clientRefineState.cancers;
      const selected = set.has(decoded);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      button.classList.toggle('is-selected', selected);
    });
  }

  function onFilterOptionClick(ev, searchstax) {
    const button = ev.target && ev.target.closest ? ev.target.closest('button.df-searchstax-filter__option') : null;
    if (!button) {
      return;
    }
    const g = button.getAttribute('data-df-group');
    let v = button.getAttribute('data-df-value') || '';
    try {
      v = decodeURIComponent(v);
    } catch (e) {
      /* ignore */
    }
    const set = g === 'issue' ? clientRefineState.issues
      : g === 'kind' ? clientRefineState.kinds
      : g === 'category' ? clientRefineState.categories
        : g === 'topic' ? clientRefineState.topics : clientRefineState.cancers;
    if (set.has(v)) {
      set.delete(v);
    }
    else {
      set.add(v);
    }
    syncFilterCheckboxesFromState();
    rerunCurrentSearch(searchstax);
  }

  function buildPermanentFilterPanel(settings, features, searchstax) {
    const wrap = document.getElementById('df-searchstax-client-facets');
    if (!wrap) {
      return;
    }
    if (!features.article_filters && !features.client_ribbon_facets) {
      wrap.hidden = true;
      wrap.innerHTML = '';
      clientRefineState = { kinds: new Set(), issues: new Set(), categories: new Set(), topics: new Set(), cancers: new Set() };
      return;
    }

    const fo = settings.filter_options || {};
    const iconSearch = '<svg class="df-searchstax-filter__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>';
    const iconFunnel = '<svg class="df-searchstax-filter__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>';

    const esc = (s) => Drupal.checkPlain(String(s));
    const valAttr = (s) => encodeURIComponent(String(s));

    const checkboxRow = (group, value, label, idSuffix) => {
      const id = `df-filter-${group}-${idSuffix}`;
      return `
        <button type="button" class="df-searchstax-filter__option" id="${id}" data-df-group="${group}" data-df-value="${valAttr(value)}" aria-pressed="false">
          <span class="df-searchstax-filter__option-box" aria-hidden="true"></span>
          <span class="df-searchstax-filter__row-label">${esc(label)}</span>
        </button>`;
    };

    const detailsBlock = (summaryDefault, innerHtml, open) => {
      if (!innerHtml) {
        return '';
      }
      return `
        <details class="df-searchstax-filter__details"${open ? ' open' : ''}>
          <summary class="df-searchstax-filter__summary">${esc(summaryDefault)}</summary>
          <div class="df-searchstax-filter__panel">${innerHtml}</div>
        </details>`;
    };

    let idCounter = 0;
    const nextId = () => {
      idCounter += 1;
      return `i${idCounter}`;
    };

    let sections = '';
    const issues = Array.isArray(fo.issues) ? fo.issues : [];
    if (issues.length) {
      const rows = issues.map((issue) => checkboxRow('issue', issue, issue, nextId())).join('');
      sections += detailsBlock(Drupal.t('All issues'), rows, true);
    }

    const categories = Array.isArray(fo.categories) ? fo.categories : [];
    if (categories.length) {
      const rows = categories.map((term) => checkboxRow('category', term, term, nextId())).join('');
      sections += detailsBlock(Drupal.t('All categories'), rows, true);
    }

    wrap.hidden = false;
    wrap.innerHTML = `
      <div class="df-searchstax-filter-sidebar">
        <div class="df-searchstax-filter-sidebar__search-heading">
          ${iconSearch}
          <span class="df-searchstax-filter-sidebar__heading-text">${Drupal.t('Search')}</span>
        </div>
        <p class="df-searchstax-filter-sidebar__search-hint">${Drupal.t('Use the search field above to find articles.')}</p>
        <div class="df-searchstax-filter-sidebar__filters">
          <div class="df-searchstax-filter-sidebar__filters-heading">
            ${iconFunnel}
            <span class="df-searchstax-filter-sidebar__heading-text">${Drupal.t('Filters')}</span>
          </div>
          <div class="df-searchstax-filter-sidebar__actions">
            <button type="button" class="df-searchstax-filter__clear">${Drupal.t('Clear all filters')}</button>
          </div>
          <div class="df-searchstax-filter-sidebar__accent">
            ${sections}
          </div>
          <p class="visually-hidden" id="df-searchstax-refine-status" aria-live="polite"></p>
        </div>
      </div>
    `;

    wrap.querySelector('.df-searchstax-filter-sidebar__accent').addEventListener('click', (ev) => onFilterOptionClick(ev, searchstax));
    const clearBtn = wrap.querySelector('.df-searchstax-filter__clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        clientRefineState = { kinds: new Set(), issues: new Set(), categories: new Set(), topics: new Set(), cancers: new Set() };
        syncFilterCheckboxesFromState();
        rerunCurrentSearch(searchstax);
      });
    }
    syncFilterCheckboxesFromState();
  }

  function mergeSettings() {
    const defaults = {
      language: 'en',
      search_url: '',
      suggester_url: '',
      search_auth: '',
      auth_type: 'token',
      analytics_base_url: 'https://analytics-us.searchstax.com',
      track_api_key: '',
      analytics_src: 'https://static.searchstax.com/studio-js/v4.1.55/js/studio-analytics.js',
      question_url: '',
      related_searches_url: '',
      related_searches_api_key: '',
      popular_searches_url: '',
      geocoding_url: 'https://geocoding.searchstax.com',
      country_code: 'us',
      app_id: '',
      results_render_method: 'pagination',
      facets_items_per_page_desktop: 6,
      facets_items_per_page_mobile: 99,
      faceting_type: 'and',
      features: {
        article_filters: true,
        facets: false,
        sorting: true,
        related_searches: true,
        popular_searches: true,
        external_promotions: true,
        answer_widget: true,
        search_feedback: true,
        feedback_widget: true,
        location_search: false,
        view_style_toggle: true,
        client_ribbon_facets: false,
      },
      required_filter_queries: [],
      server_facets: [
        'issue_title_s',
        'article_category_s',
      ],
      filter_options: {
        contentKinds: [
          { value: 'Article', label: 'Article' },
        ],
        issues: [],
        categories: [],
        topics: [],
        cancerTypes: [],
      },
    };

    const incoming = drupalSettings.dfSearchstax || {};
    const merged = Object.assign({}, defaults, incoming);
    merged.features = Object.assign({}, defaults.features, incoming.features || {});
    merged.filter_options = Object.assign({}, defaults.filter_options, incoming.filter_options || {});
    merged.required_filter_queries = Array.isArray(incoming.required_filter_queries)
      ? incoming.required_filter_queries : defaults.required_filter_queries;
    merged.server_facets = Array.isArray(incoming.server_facets)
      ? incoming.server_facets : defaults.server_facets;
    return merged;
  }

  function loadScript(src, cb) {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      if (typeof cb === 'function') {
        cb();
      }
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.onload = cb;
    document.head.appendChild(script);
  }

  /**
   * Query param SearchStax router uses (default routeName "searchstax").
   */
  function readUrlSearchQueryParam() {
    try {
      return new URLSearchParams(window.location.search).get('searchstax[query]');
    } catch (e) {
      return null;
    }
  }

  /**
   * Run a full-catalog search when /search loads without a query. SearchStax
   * only calls the Select API when searchObject.query is non-empty; an empty
   * string leaves the page with no results so client filters cannot work.
   */
  function scheduleDefaultFullCatalogSearch(searchstax) {
    window.setTimeout(() => {
      const urlQ = readUrlSearchQueryParam();
      if (urlQ !== null && String(urlQ).trim() !== '') {
        return;
      }
      const dl = searchstax && searchstax.dataLayer;
      if (!dl || typeof dl.setSearchObject !== 'function') {
        return;
      }
      const cur = dl.searchObject || {};
      const q = cur.query;
      if (q !== undefined && q !== null && q !== '' && q !== 'undefined' && String(q).trim() !== '') {
        return;
      }
      dl.setSearchObject({
        ...cur,
        query: '*',
        page: 1,
      });
    }, 150);
  }

  function executeSearch(searchstax, term) {
    const t = term != null ? String(term).trim() : '';
    if (!t || t === 'undefined') {
      return;
    }
    const dl = searchstax && searchstax.dataLayer;
    if (dl && typeof dl.setSearchObject === 'function') {
      const cur = dl.searchObject || {};
      dl.setSearchObject({
        ...cur,
        query: t,
        page: 1,
      });
      return;
    }
    if (searchstax && typeof searchstax.search === 'function') {
      searchstax.search({ ...(searchstax.dataLayer && searchstax.dataLayer.searchObject ? searchstax.dataLayer.searchObject : {}), query: t, page: 1 });
      return;
    }
    const input = document.getElementById('searchstax-search-input');
    if (!input) {
      return;
    }
    input.value = t;
    input.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', bubbles: true }));
  }

  function withRequiredFilterQueries(searchObject, filterQueries) {
    const copy = { ...(searchObject || {}) };
    const existing = Array.isArray(copy.additionalProps)
      ? copy.additionalProps.filter((item) => item && item.key !== 'fq') : [];
    const hasPair = (key, value) => existing.some((item) => item && item.key === key && item.value === value);
    [...(Array.isArray(filterQueries) ? filterQueries : []), ...buildArticleFilterQueries()].forEach((fq) => {
      const value = String(fq || '').trim();
      if (value && !hasPair('fq', value)) {
        existing.push({ key: 'fq', value });
      }
    });
    copy.additionalProps = existing;
    return copy;
  }

  function escapeSolrPhrase(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function phraseFilter(field, values) {
    const list = [...values].map(escapeSolrPhrase).filter(Boolean);
    if (!list.length) {
      return '';
    }
    if (list.length === 1) {
      return `${field}:"${list[0]}"`;
    }
    return `${field}:(${list.map((item) => `"${item}"`).join(' OR ')})`;
  }

  function buildArticleFilterQueries() {
    return [
      phraseFilter('tcngramm_X3b_en_field_story_teaser', clientRefineState.issues),
      phraseFilter('tcngramm_X3b_en_field_story_teaser', clientRefineState.categories),
    ].filter(Boolean);
  }

  function rerunCurrentSearch(searchstax) {
    const dl = searchstax && searchstax.dataLayer;
    if (!dl || typeof dl.setSearchObject !== 'function') {
      return;
    }
    const cur = dl.searchObject || {};
    dl.setSearchObject({
      ...cur,
      page: 1,
    });
  }

  function setViewStyle(searchResultsContainer) {
    const toggleContainer = document.getElementById('df-searchstax-view-style-container');
    const toggleButton = document.getElementById('df-searchstax-toggle-view-style');
    const icon = document.getElementById('df-searchstax-icon-view-style');
    if (!toggleContainer || !toggleButton || !icon || !searchResultsContainer) {
      return;
    }

    const applyStyle = (style) => {
      const previous = style === 'grid' ? 'list' : 'grid';
      searchResultsContainer.classList.remove(`searchstax-results-container-${previous}`);
      searchResultsContainer.classList.add(`searchstax-results-container-${style}`);
      icon.classList.remove(`icon-${previous}`);
      icon.classList.add(`icon-${style}`);
      toggleButton.setAttribute('aria-label', `Toggle result view style. Current style: ${style}`);
    };

    const current = localStorage.getItem('dfSearchstaxViewStyle') || 'list';
    applyStyle(current);
    toggleContainer.hidden = false;

    toggleButton.addEventListener('click', () => {
      const next = (localStorage.getItem('dfSearchstaxViewStyle') || 'list') === 'list' ? 'grid' : 'list';
      localStorage.setItem('dfSearchstaxViewStyle', next);
      applyStyle(next);
    });
  }

  function parsePopularSearchTerms(data) {
    const raw = Array.isArray(data?.popular_searches)
      ? data.popular_searches
      : (Array.isArray(data?.results) ? data.results : []);

    return raw
      .map((item) => {
        if (typeof item === 'string') {
          return item.trim();
        }
        if (item && typeof item === 'object') {
          return String(item.popular_search || item.term || item.query || '').trim();
        }
        return '';
      })
      .filter(Boolean);
  }

  function renderPopularSearches(settings, searchstax) {
    const container = document.getElementById('df-searchstax-popular-searches-container');
    if (!container || !settings.popular_searches_url || !settings.related_searches_api_key) {
      return;
    }

    fetch(settings.popular_searches_url, {
      headers: {
        Authorization: `Token ${settings.related_searches_api_key}`,
        'Content-Type': 'application/json',
      },
    })
      .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
      .then((data) => {
        const terms = parsePopularSearchTerms(data);
        if (!terms.length) {
          return;
        }
        const title = document.createElement('p');
        title.className = 'df-searchstax-popular-title';
        title.textContent = 'Popular searches';

        const list = document.createElement('div');
        list.className = 'df-searchstax-popular-list';

        terms.slice(0, 10).forEach((term) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'df-searchstax-popular-item';
          button.textContent = term;
          button.addEventListener('click', () => {
            executeSearch(searchstax, term);
          });
          list.appendChild(button);
        });

        if (list.children.length) {
          container.innerHTML = '';
          container.appendChild(title);
          container.appendChild(list);
        }
      })
      .catch(() => {
        // Non-blocking: if popular searches fail, search UI remains functional.
      });
  }

  function initializeSearchPage() {
    const mountRoot = document.querySelector('.df-searchstax-page-layout-container');
    if (!mountRoot || mountRoot.dataset.searchstaxInit === '1') {
      return;
    }
    mountRoot.dataset.searchstaxInit = '1';

    const settings = mergeSettings();
    if (!settings.search_url || !settings.suggester_url) {
      return;
    }

    const studio = window['@searchstaxInc/searchstudioUxJs'];
    const SearchstaxCtor = studio && studio.Searchstax;
    if (!SearchstaxCtor) {
      return;
    }

    const searchstax = new SearchstaxCtor();
    const features = settings.features || {};

    if (features.article_filters || features.client_ribbon_facets) {
      buildPermanentFilterPanel(settings, features, searchstax);
    }
    else {
      const facetWrap = document.getElementById('df-searchstax-client-facets');
      if (facetWrap) {
        facetWrap.hidden = true;
        facetWrap.innerHTML = '';
      }
    }

    searchstax.initialize({
      language: settings.language,
      searchURL: settings.search_url,
      suggesterURL: settings.suggester_url,
      searchAuth: settings.search_auth,
      trackApiKey: settings.track_api_key,
      authType: settings.auth_type || 'token',
      analyticsBaseUrl: settings.analytics_base_url,
      sessionId: randomId(25),
      questionURL: settings.question_url || undefined,
      analyticsSrc: settings.analytics_src,
      hooks: {
        beforeSearch: (searchObject) => withRequiredFilterQueries(searchObject, settings.required_filter_queries),
        afterSearch: (results) => {
          const viewContainer = document.getElementById('df-searchstax-view-style-container');
          if (viewContainer && features.view_style_toggle) {
            viewContainer.hidden = !results.length;
          }
          const processed = results.map((result) => {
            applyDfResultFacets(result);
            const unmapped = Array.isArray(result.unmappedFields) ? result.unmappedFields : [];
            if (!result.url || String(result.url).trim() === '') {
              const urlField = unmapped.find((field) => field.key === 'url_s' || field.key === 'url');
              if (urlField && typeof urlField.value === 'string' && urlField.value.trim() !== '') {
                result.url = urlField.value.trim();
              }
            }
            if (!result.url || String(result.url).trim() === '') {
              const uid = result.uniqueId != null ? String(result.uniqueId).trim() : '';
              if (/^https?:\/\//i.test(uid)) {
                result.url = uid;
              }
            }
            if (result.url && String(result.url).trim() !== '') {
              result.url = localizeSearchResultUrl(result.url);
            }
            if ((!result.title || String(result.title).trim() === '') && result.url) {
              try {
                const path = new URL(result.url, window.location.origin).pathname.replace(/\/$/, '');
                const seg = path.split('/').filter(Boolean).pop();
                if (seg) {
                  result.title = decodeURIComponent(seg).replace(/-/g, ' ');
                }
              } catch (e) {
                /* ignore */
              }
            }
            if ((!result.description || result.description.trim() === '') && unmapped.length) {
              const fallbackDescription = unmapped.find((field) => field.key === 'description' && typeof field.value === 'string');
              if (fallbackDescription) {
                result.description = fallbackDescription.value;
              }
            }
            if (typeof result.description === 'string' && result.description.length > 800) {
              const stripped = result.description.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
              if (stripped.length > 400) {
                const cut = stripped.slice(0, 400);
                const lastSp = cut.lastIndexOf(' ');
                result.description = (lastSp > 200 ? cut.slice(0, lastSp) : cut) + '…';
              }
            }
            const authorField = unmapped.find((field) => field.key === 'author_t' && typeof field.value === 'string');
            if (authorField && authorField.value && result.description && !result.description.includes(authorField.value)) {
              result.description = `${result.description} <span class="df-searchstax-result-author">${authorField.value}</span>`;
            }
            if (unmapped.length) {
              result.unmappedFields = unmapped.filter((field) => field.key !== 'description');
            }
            applyDfUrlFallbackKind(result);
            return result;
          });
          if (features.client_ribbon_facets) {
            scheduleApplyClientRefineVisibility();
          }
          return processed;
        },
      },
    });

    searchstax.addSearchInputWidget('df-searchstax-input-container', {
      templates: {
        mainTemplate: {
          template: `
            <label for="searchstax-search-input" class="searchstax-search-input-label">Enter article keywords</label>
            <div class="searchstax-search-input-container searchstax-search-input-container-new {{#locationEnabled}}searchstax-alternative-render{{/locationEnabled}}">
              <div class="searchstax-search-input-wrapper">
                <input type="text" id="searchstax-search-input" class="searchstax-search-input" placeholder="Search articles..." aria-label="Search articles" />
              </div>
              <div id="searchstax-location-container" class="searchstax-location-container"></div>
              <button class="searchstax-spinner-icon" id="searchstax-search-input-action-button" aria-label="Search" type="button"></button>
            </div>`,
          searchInputId: 'searchstax-search-input',
        },
        autosuggestItemTemplate: {
          template: '<div class="searchstax-autosuggest-item-term-container">{{{term}}}</div>',
        },
      },
    });

    if (features.location_search) {
      searchstax.addSearchLocationWidget('searchstax-location-container', {
        locationSearchEnabled: true,
        hooks: {
          locationDecode: (term) => new Promise((resolve) => {
            const geocodingURL = `${settings.geocoding_url}/forward?location=${encodeURIComponent(term)}&components=country:${settings.country_code}&app_id=${settings.app_id}`;
            fetch(geocodingURL, {
              headers: {
                Authorization: `Token ${settings.related_searches_api_key}`,
                'Content-Type': 'application/json',
              },
            })
              .then((response) => response.json())
              .then((data) => {
                if (data.status === 'OK' && data.results?.length) {
                  resolve({
                    lat: data.results[0].geometry.lat,
                    lon: data.results[0].geometry.lng,
                    address: data.results[0].formatted_address,
                  });
                  return;
                }
                resolve({ address: undefined, lat: undefined, lon: undefined, error: true });
              })
              .catch(() => resolve({ address: undefined, lat: undefined, lon: undefined, error: true }));
          }),
        },
      });
    }

    if (features.answer_widget && settings.question_url) {
      searchstax.addAnswerWidget('df-searchstax-answer-container', {
        showMoreAfterWordCount: 90,
      });
    }

    if (features.search_feedback) {
      searchstax.addSearchFeedbackWidget('df-searchstax-feedback-container', {
        templates: {
          main: {
            template: `
              {{#searchExecuted}}
                {{#hasResults}}
                  <h4 class="searchstax-feedback-container">
                    <span>Showing <b>{{startResultIndex}} - {{endResultIndex}}</b> of <b>{{totalResults}}</b> results {{#searchTerm}}for "<b>{{searchTerm}}</b>"{{/searchTerm}}</span>
                    {{#autoCorrectedQuery}}
                      <div class="searchstax-feedback-container-suggested">
                        Search instead for <a href="#" class="searchstax-feedback-original-query">{{originalQuery}}</a>
                      </div>
                    {{/autoCorrectedQuery}}
                  </h4>
                {{/hasResults}}
              {{/searchExecuted}}`,
            originalQueryClass: 'searchstax-feedback-original-query',
          },
        },
      });
    }

    if (!features.article_filters && !features.client_ribbon_facets && features.facets) {
      searchstax.addFacetsWidget('df-searchstax-facets-container', {
        facetingType: settings.faceting_type || 'and',
        specificFacets: settings.server_facets || ['issue_title_s', 'article_category_s'],
        itemsPerPageDesktop: settings.facets_items_per_page_desktop || 8,
        itemsPerPageMobile: settings.facets_items_per_page_mobile || 99,
        templates: {
          mainTemplateDesktop: {
            template: `
      {{#hasResultsOrExternalPromotions}}
        <div class="searchstax-facets-container-desktop"></div>
      {{/hasResultsOrExternalPromotions}}
      `,
            facetsContainerId: '',
          },
          mainTemplateMobile: {
            template: `
        <div class="searchstax-facets-pills-container">
          <div class="searchstax-facets-pills-selected">
          </div>
        </div>

        <div class="searchstax-facets-mobile-overlay {{#overlayOpened}} searchstax-show{{/overlayOpened}}" data-test-id="searchstax-facets-mobile-overlay">
          <div class="searchstax-facets-mobile-overlay-header">
            <div class="searchstax-facets-mobile-overlay-header-title">${Drupal.t('Filters')}</div>
            <div class="searchstax-search-close" tabindex="0" aria-label="${Drupal.t('Close filters')}" role="button" data-test-id="searchstax-search-close"></div>
          </div>
          <div class="searchstax-facets-container-mobile"></div>
          <button type="button" class="searchstax-facets-mobile-overlay-done" data-test-id="searchstax-facets-mobile-overlay-done">${Drupal.t('Done')}</button>
        </div>
      `,
            facetsContainerClass: 'searchstax-facets-container-mobile',
            closeOverlayTriggerClasses: [
              'searchstax-facets-mobile-overlay-done',
              'searchstax-search-close',
            ],
            filterByContainerClass: 'searchstax-facets-pills-container',
            selectedFacetsContainerClass: 'searchstax-facets-pills-selected',
          },
          showMoreButtonContainerTemplate: {
            template: `
      <div class="searchstax-facet-show-more-container" data-test-id="searchstax-facet-show-more-container">
      {{#showingAllFacets}}
        <div class="searchstax-facet-show-less-button searchstax-facet-show-button" tabindex="0" data-test-id="searchstax-facet-show-less-button" data-focus="{{focusId}}" role="button">${Drupal.t('Show fewer')}</div>
      {{/showingAllFacets}}
      {{^showingAllFacets}}
        <div class="searchstax-facet-show-more-button searchstax-facet-show-button" tabindex="0" data-test-id="searchstax-facet-show-more-button" data-focus="{{focusId}}" role="button">${Drupal.t('Show')} {{onShowMoreLessClick}}</div>
      {{/showingAllFacets}}
    </div>
      `,
            showMoreButtonClass: 'searchstax-facet-show-more-container',
          },
          facetItemContainerTemplate: {
            template: `
      <div class="searchstax-facet-container">
        <div class="searchstax-facet-title-container" data-test-id="searchstax-facet-title-container">
            <div class="searchstax-facet-title" aria-label="Facet group: {{label}}" tabindex="0" role="button">
            {{label}}
            </div>
            <div class="searchstax-facet-title-arrow active"></div>
        </div>
        <div class="searchstax-facet-values-container"></div>
      </div>
      `,
            facetListTitleContainerClass: 'searchstax-facet-title-container',
            facetListTitleContainerInner: 'searchstax-facet-title',
            facetListContainerClass: 'searchstax-facet-values-container',
          },
          clearFacetsTemplate: {
            template: `
      {{#shouldShow}}
        <div class="searchstax-facets-pill searchstax-clear-filters searchstax-facets-pill-clear-all" tabindex="0" role="button" data-test-id="searchstax-facets-pill-clear-all">
        <div class="searchstax-facets-pill-label">${Drupal.t('Clear all')}</div>
        </div>
      {{/shouldShow}}
      `,
            containerClass: 'searchstax-facets-pill-clear-all',
          },
          facetItemTemplate: {
            template: `
      <div class="searchstax-facet-input">
        <input type="checkbox" class="searchstax-facet-input-checkbox" data-test-id="searchstax-facet-input-checkbox" {{#disabled}}disabled{{/disabled}} {{#isChecked}}checked{{/isChecked}} aria-label="{{value}}, {{count}} results" tabindex="0"/>
      </div>
      <div class="searchstax-facet-value-label" data-test-id="searchstax-facet-value-label">{{value}}</div>
      <div class="searchstax-facet-value-count" data-test-id="searchstax-facet-value-count">({{count}})</div>
      `,
            inputCheckboxClass: 'searchstax-facet-input-checkbox',
            checkTriggerClasses: [
              'searchstax-facet-value-label',
              'searchstax-facet-value-count',
            ],
          },
          filterByTemplate: {
            template: `
      <div class="searchstax-facets-pill searchstax-facets-pill-filter-by" tabindex="0" role="button" data-test-id="searchstax-facets-pill-filter-by" >
        <div class="searchstax-facets-pill-label">${Drupal.t('Filters')}</div>
      </div>
      `,
            containerClass: 'searchstax-facets-pill-filter-by',
          },
          selectedFacetsTemplate: {
            template: `
      <div class="searchstax-facets-pill searchstax-facets-pill-facets" tabindex="0" role="button" data-test-id="searchstax-facets-pill-facets">
        <div class="searchstax-facets-pill-label">{{value}} ({{count}})</div>
        <div class="searchstax-facets-pill-icon-close"></div>
      </div>
      `,
            containerClass: 'searchstax-facets-pill-facets',
          },
        },
      });
    }

    if (features.sorting) {
      searchstax.addSearchSortingWidget('df-searchstax-sorting-container', {
        templates: {
          main: {
            template: `
              {{#searchExecuted}}
                {{#hasResultsOrExternalPromotions}}
                  {{#sortOptions.length}}
                    <div class="searchstax-sorting-container" data-test-id="searchstax-sorting-container">
                      <label class="searchstax-sorting-label" for="searchstax-search-order-select">Sort By</label>
                      <select id="searchstax-search-order-select" class="searchstax-search-order-select">
                        {{#sortOptions}}
                          <option value="{{key}}">{{value}}</option>
                        {{/sortOptions}}
                      </select>
                    </div>
                  {{/sortOptions.length}}
                {{/hasResultsOrExternalPromotions}}
              {{/searchExecuted}}`,
            selectId: 'searchstax-search-order-select',
          },
        },
      });
    }

    searchstax.addSearchResultsWidget('df-searchstax-results-container', {
      templates: {
        mainTemplate: {
          template: `
            <section aria-label="search results container">
              <div class="searchstax-search-results-container" id="searchstax-search-results-container">
                <div class="searchstax-search-results" id="searchstax-search-results"></div>
              </div>
            </section>`,
          searchResultsContainerId: 'searchstax-search-results',
        },
        searchResultTemplate: {
          template: `
            <a href="{{url}}" data-searchstax-unique-result-id="{{uniqueId}}" data-df-type="{{dfFacetType}}" data-df-category="{{dfFacetCategory}}" data-df-topics="{{dfFacetTopics}}" data-df-cancers="{{dfFacetCancers}}" class="searchstax-result-item-link searchstax-result-item-link-wrapping df-impact-search-card-link" tabindex="0">
              <article class="searchstax-search-result searchstax-search-result-wrapping df-impact-search-card {{#thumbnail}}has-thumbnail{{/thumbnail}}">
                {{#promoted}}
                  <div class="searchstax-search-result-promoted" data-test-id="searchstax-search-result-promoted"></div>
                {{/promoted}}
                {{#thumbnail}}
                  <div class="df-impact-search-card__media">
                    <img alt="" src="{{thumbnail}}" class="searchstax-thumbnail df-impact-search-card__img" loading="lazy" decoding="async" width="160" height="120">
                  </div>
                {{/thumbnail}}
                <div class="df-impact-search-card__body">
                  {{#ribbon}}
                    <div class="searchstax-search-result-ribbon df-impact-search-card__ribbon">{{{ribbon}}}</div>
                  {{/ribbon}}
                  <div class="searchstax-search-result-title-container">
                    <h3 class="searchstax-search-result-title df-impact-search-card__title">{{{title}}}</h3>
                  </div>
                  {{#paths}}
                    <p class="searchstax-search-result-common df-impact-search-card__paths">{{{paths}}}</p>
                  {{/paths}}
                  {{#description}}
                    <p class="searchstax-search-result-description searchstax-search-result-common df-impact-search-card__description">{{{description}}}</p>
                  {{/description}}
                </div>
              </article>
            </a>`,
          searchResultUniqueIdAttribute: 'data-searchstax-unique-result-id',
        },
        noSearchResultTemplate: {
          template: `
            {{#searchExecuted}}
              <div class="searchstax-no-results-wrap">
                <div class="searchstax-no-results">
                  Showing <strong>no results</strong> for <strong>"{{searchTerm}}"</strong>
                </div>
                <ul class="searchstax-no-results-list">
                  <li>Try fewer keywords or broader terms.</li>
                  <li>Use filters to narrow from broader results.</li>
                </ul>
              </div>
            {{/searchExecuted}}`,
        },
      },
      renderMethod: settings.results_render_method || 'pagination',
    });

    searchstax.addPaginationWidget('df-searchstax-pagination-container', {
      templates: {
        mainTemplate: {
          template: `
            {{#results.length}}
              <div class="searchstax-pagination-container">
                <div class="searchstax-pagination-content">
                  <a role="link" class="searchstax-pagination-previous {{#isFirstPage}}disabled{{/isFirstPage}}" aria-disabled="{{#isFirstPage}}true{{/isFirstPage}}{{^isFirstPage}}false{{/isFirstPage}}" id="searchstax-pagination-previous" tabindex="0" aria-label="Previous page">&lt; Previous</a>
                  <div class="searchstax-pagination-details">{{startResultIndex}} - {{endResultIndex}} of {{totalResults}}</div>
                  <a role="link" class="searchstax-pagination-next {{#isLastPage}}disabled{{/isLastPage}}" aria-disabled="{{#isLastPage}}true{{/isLastPage}}{{^isLastPage}}false{{/isLastPage}}" id="searchstax-pagination-next" tabindex="0" aria-label="Next page">Next &gt;</a>
                </div>
              </div>
            {{/results.length}}`,
          previousButtonClass: 'searchstax-pagination-previous',
          nextButtonClass: 'searchstax-pagination-next',
        },
      },
    });

    if (features.related_searches && settings.related_searches_url && settings.related_searches_api_key) {
      searchstax.addRelatedSearchesWidget('df-searchstax-related-searches-container', {
        relatedSearchesURL: settings.related_searches_url,
        relatedSearchesAPIKey: settings.related_searches_api_key,
        templates: {
          main: {
            template: `
              {{#hasRelatedSearches}}
                <div class="searchstax-related-searches-container" id="searchstax-related-searches-container">
                  Related searches: <span id="searchstax-related-searches"></span>
                </div>
              {{/hasRelatedSearches}}`,
            relatedSearchesContainerClass: 'searchstax-related-search',
          },
          relatedSearch: {
            template: `
              <span role="button" class="searchstax-related-search searchstax-related-search-item" aria-label="Related search: {{related_search}}" tabindex="0">
                {{related_search}}{{^last}}<span>, </span>{{/last}}
              </span>`,
            relatedSearchContainerClass: 'searchstax-related-search-item',
          },
        },
      });
    }

    if (features.external_promotions) {
      searchstax.addExternalPromotionsWidget('df-searchstax-external-promotions-layout-container', {
        templates: {
          mainTemplate: {
            template: `
              {{#hasExternalPromotions}}
                <div class="searchstax-external-promotions-container" id="searchstax-external-promotions-container"></div>
              {{/hasExternalPromotions}}`,
            externalPromotionsContainerId: 'searchstax-external-promotions-container',
          },
          externalPromotion: {
            template: `
              <div class="searchstax-external-promotion searchstax-search-result">
                {{#url}}
                  <a href="{{url}}" data-searchstax-unique-result-id="{{uniqueId}}" class="searchstax-result-item-link"></a>
                {{/url}}
                <div class="searchstax-search-result-title-container">
                  <span class="searchstax-search-result-title">{{name}}</span>
                </div>
                {{#description}}
                  <p class="searchstax-search-result-description searchstax-search-result-common">{{description}}</p>
                {{/description}}
              </div>`,
          },
        },
      });
    }

    if (features.feedback_widget && settings.track_api_key) {
      import(FEEDBACK_MODULE_JS)
        .then((mod) => {
          const SearchstaxFeedbackWidget = mod.default;
          new SearchstaxFeedbackWidget({
            analyticsKey: settings.track_api_key,
            containerId: 'df-searchstax-feedback-widget-container',
            lightweight: false,
          });
        })
        .catch(() => {
          // Non-blocking.
        });
    }

    if (features.popular_searches) {
      renderPopularSearches(settings, searchstax);
    }

    if (features.view_style_toggle) {
      setViewStyle(document.getElementById('df-searchstax-results-container'));
    }

    if (features.client_ribbon_facets) {
      window.setTimeout(() => bindSearchResultsRefineObserver(), 0);
    }

    scheduleDefaultFullCatalogSearch(searchstax);
  }

  Drupal.behaviors.dfSearchstaxPage = {
    attach(context) {
      if (!context.querySelector('.df-searchstax-page-layout-container')) {
        return;
      }
      loadScript(STUDIO_JS, initializeSearchPage);
    },
  };
})(Drupal, drupalSettings);

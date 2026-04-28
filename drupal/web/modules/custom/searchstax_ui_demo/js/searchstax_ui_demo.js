/**
 * @file
 * Initializes SearchStax Studio using the same public demo config as search_ui_demo.
 */

(function (Drupal) {
  'use strict';

  const STUDIO_JS = 'https://static.searchstax.com/studio-js/v4.1.55/js/search-ux.js';

  function randomId(length) {
    const chars =
      'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let out = '';
    for (let i = 0; i < length; i++) {
      out += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return out;
  }

  /**
   * Public SearchStax demo endpoints (Jimmy Fund–style sample index).
   * Copied from search_ui_demo/index.html for local learning only.
   */
  const demoConfig = {
    language: 'en',
    searchURL:
      'https://searchcloud-19-us-east-1.searchstax.com/29847/jimmyfunddemo-9082/emselect',
    suggesterURL:
      'https://searchcloud-19-us-east-1.searchstax.com/29847/jimmyfunddemo-9082_suggester/emsuggest',
    searchAuth: '59443d830d5cb68fcbbdd7b59748005fbb7b218c',
    trackApiKey: 'DRtQgTLOzs8RmhdqQ9Xhd6AEbTFWl8az5HfqJG2dss4',
    authType: 'token',
    relatedSearchesURL:
      'https://app.searchstax.com/api/v1/9082/related-search/',
    relatedSearchesAPIKey: '51481ca12b0ad50a4e8912a4afd8061ef966e368',
    analyticsBaseUrl: 'https://analytics-us.searchstax.com',
    questionURL: 'https://search-ai-us.searchstax.com/api/v1/9082/answer/',
    appId: '9082',
    analyticsSrc:
      'https://static.searchstax.com/studio-js/v4.1.55/js/studio-analytics.js',
    sessionId: randomId(25),
  };

  function initSearchstax() {
    const studio = window['@searchstaxInc/searchstudioUxJs'];
    const SearchstaxCtor = studio && studio.Searchstax;
    if (!SearchstaxCtor) {
      return;
    }

    const searchstax = new SearchstaxCtor();

    searchstax.initialize({
      language: demoConfig.language,
      searchURL: demoConfig.searchURL,
      suggesterURL: demoConfig.suggesterURL,
      searchAuth: demoConfig.searchAuth,
      trackApiKey: demoConfig.trackApiKey,
      authType: demoConfig.authType,
      analyticsBaseUrl: demoConfig.analyticsBaseUrl,
      sessionId: demoConfig.sessionId,
      questionURL: demoConfig.questionURL,
      analyticsSrc: demoConfig.analyticsSrc,
    });

    searchstax.addSearchInputWidget('searchstax-input-container', {
      templates: {
        mainTemplate: {
          template: `
          <label for="searchstax-search-input" class="searchstax-search-input-label">Keywords</label>
          <div class="searchstax-search-input-container">
            <div class="searchstax-search-input-wrapper">
              <input type="text" id="searchstax-search-input" class="searchstax-search-input" placeholder="Try a search…" aria-label="Search" />
            </div>
            <button class="searchstax-spinner-icon" id="searchstax-search-input-action-button" aria-label="Search" type="button"></button>
          </div>`,
          searchInputId: 'searchstax-search-input',
        },
        autosuggestItemTemplate: {
          template: `<div class="searchstax-autosuggest-item-term-container">{{{term}}}</div>`,
        },
      },
    });

    searchstax.addSearchResultsWidget('searchstax-results-container', {
      templates: {
        mainTemplate: {
          template: `
          <section aria-label="search results">
            <div class="searchstax-search-results-container" id="searchstax-search-results-container">
              <div class="searchstax-search-results" id="searchstax-search-results"></div>
            </div>
          </section>`,
          searchResultsContainerId: 'searchstax-search-results',
        },
        searchResultTemplate: {
          template: `
    <a href="{{url}}" data-searchstax-unique-result-id="{{uniqueId}}" class="searchstax-result-item-link searchstax-result-item-link-wrapping" tabindex="0">
    <div class="searchstax-search-result searchstax-search-result-wrapping {{#thumbnail}} has-thumbnail {{/thumbnail}}">
        {{#thumbnail}}
            <img alt="" src="{{thumbnail}}" class="searchstax-thumbnail" data-test-id="searchstax-thumbnail">
        {{/thumbnail}}
        <div class="searchstax-search-result-title-container">
            <h3 class="searchstax-search-result-title" id="title-{{uniqueId}}">{{{title}}}</h3>
        </div>
        {{#description}}
            <p class="searchstax-search-result-description searchstax-search-result-common">{{{description}}}</p>
        {{/description}}
    </div>
    </a>`,
          searchResultUniqueIdAttribute: 'data-searchstax-unique-result-id',
        },
        noSearchResultTemplate: {
          template: `
    {{#searchExecuted}}
    <div class="searchstax-no-results-wrap">
      <div class="searchstax-no-results">No results for <strong>"{{ searchTerm }}"</strong></div>
    </div>
    {{/searchExecuted}}`,
        },
      },
      renderMethod: 'pagination',
    });

    searchstax.addPaginationWidget('searchstax-pagination-container', {
      templates: {
        mainTemplate: {
          template: `
      {{#results.length}}
        <div class="searchstax-pagination-container">
          <div class="searchstax-pagination-content">
            <a role="link" class="searchstax-pagination-previous {{#isFirstPage}}disabled{{/isFirstPage}}" id="searchstax-pagination-previous" tabindex="0" aria-label="Previous Page">&lt; Previous</a>
            <div class="searchstax-pagination-details">{{startResultIndex}} - {{endResultIndex}} of {{totalResults}}</div>
            <a role="link" class="searchstax-pagination-next {{#isLastPage}}disabled{{/isLastPage}}" id="searchstax-pagination-next" tabindex="0" aria-label="Next Page">Next &gt;</a>
          </div>
        </div>
      {{/results.length}}`,
          previousButtonClass: 'searchstax-pagination-previous',
          nextButtonClass: 'searchstax-pagination-next',
        },
      },
    });
  }

  Drupal.behaviors.searchstaxUiDemo = {
    attach(context) {
      const root = context.querySelector('.searchstax-ui-demo');
      if (!root || root.dataset.searchstaxLoaded) {
        return;
      }
      root.dataset.searchstaxLoaded = '1';

      const script = document.createElement('script');
      script.src = STUDIO_JS;
      script.onload = initSearchstax;
      document.head.appendChild(script);
    },
  };
})(Drupal);

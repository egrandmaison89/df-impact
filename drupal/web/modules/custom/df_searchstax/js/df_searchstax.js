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
        facets: true,
        sorting: true,
        related_searches: true,
        popular_searches: true,
        external_promotions: true,
        answer_widget: true,
        search_feedback: true,
        feedback_widget: true,
        location_search: false,
        view_style_toggle: true,
      },
    };

    return Object.assign(defaults, drupalSettings.dfSearchstax || {});
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

  function executeSearch(searchstax, term) {
    if (!term) {
      return;
    }
    if (searchstax && typeof searchstax.search === 'function') {
      searchstax.search(term);
      return;
    }
    const input = document.getElementById('searchstax-search-input');
    if (!input) {
      return;
    }
    input.value = term;
    input.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', bubbles: true }));
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
        beforeSearch: (searchObj) => searchObj,
        afterSearch: (results) => {
          const viewContainer = document.getElementById('df-searchstax-view-style-container');
          if (viewContainer && features.view_style_toggle) {
            viewContainer.hidden = !results.length;
          }
          return results.map((result) => {
            if ((!result.description || result.description.trim() === '') && Array.isArray(result.unmappedFields)) {
              const fallbackDescription = result.unmappedFields.find((field) => field.key === 'description' && typeof field.value === 'string');
              if (fallbackDescription) {
                result.description = fallbackDescription.value;
              }
            }
            if (Array.isArray(result.unmappedFields)) {
              result.unmappedFields = result.unmappedFields.filter((field) => field.key !== 'description');
            }
            return result;
          });
        },
      },
    });

    searchstax.addSearchInputWidget('df-searchstax-input-container', {
      templates: {
        mainTemplate: {
          template: `
            <label for="searchstax-search-input" class="searchstax-search-input-label">Enter your keywords</label>
            <div class="searchstax-search-input-container searchstax-search-input-container-new {{#locationEnabled}}searchstax-alternative-render{{/locationEnabled}}">
              <div class="searchstax-search-input-wrapper">
                <input type="text" id="searchstax-search-input" class="searchstax-search-input" placeholder="Search this site..." aria-label="Search" />
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

    if (features.facets) {
      searchstax.addFacetsWidget('df-searchstax-facets-container', {
        facetingType: settings.faceting_type || 'and',
        itemsPerPageDesktop: settings.facets_items_per_page_desktop || 6,
        itemsPerPageMobile: settings.facets_items_per_page_mobile || 99,
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
            <a href="{{url}}" data-searchstax-unique-result-id="{{uniqueId}}" class="searchstax-result-item-link searchstax-result-item-link-wrapping" tabindex="0">
              <div class="searchstax-search-result searchstax-search-result-wrapping {{#thumbnail}}has-thumbnail{{/thumbnail}}">
                {{#promoted}}
                  <div class="searchstax-search-result-promoted" data-test-id="searchstax-search-result-promoted"></div>
                {{/promoted}}
                {{#ribbon}}
                  <div class="searchstax-search-result-ribbon">{{{ribbon}}}</div>
                {{/ribbon}}
                {{#thumbnail}}
                  <img alt="" src="{{thumbnail}}" class="searchstax-thumbnail">
                {{/thumbnail}}
                <div class="searchstax-search-result-title-container">
                  <h3 class="searchstax-search-result-title">{{{title}}}</h3>
                </div>
                {{#paths}}
                  <p class="searchstax-search-result-common">{{{paths}}}</p>
                {{/paths}}
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

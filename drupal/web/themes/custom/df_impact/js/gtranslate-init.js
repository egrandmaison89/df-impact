/**
 * GTranslate: single dropdown (WP reference-pack static markup + translate lib).
 */
(function () {
  'use strict';

  if (window.__dfImpactGtranslateInit) {
    return;
  }
  window.__dfImpactGtranslateInit = true;

  window.gtranslateSettings = {
    default_language: 'en',
    native_language_names: true,
    languages: ['en', 'es', 'fr', 'pt', 'zh-CN', 'ar', 'ht', 'ru', 'vi', 'ko', 'ja'],
    wrapper_selector: '.gtranslate_wrapper',
  };

  // WP chrome ships a static `.gt_selector`; do not load dropdown.js (it injects a second select).
  function loadTranslateLib(callback) {
    if (window.gt_translate_script) {
      callback && callback();
      return;
    }
    window.gt_translate_script = document.createElement('script');
    window.gt_translate_script.src = 'https://cdn.gtranslate.net/widgets/latest/lib.min.js';
    window.gt_translate_script.defer = true;
    if (callback) {
      window.gt_translate_script.addEventListener('load', callback, { once: true });
    }
    document.body.appendChild(window.gt_translate_script);
  }

  function bindStaticSelectors() {
    document.querySelectorAll('.gtranslate_wrapper .gt_selector').forEach(function (select) {
      if (select.dataset.dfGtranslateBound) {
        return;
      }
      select.dataset.dfGtranslateBound = '1';
      select.addEventListener('change', function () {
        loadTranslateLib(function () {
          if (typeof window.doGTranslate === 'function') {
            window.doGTranslate(select.value);
          }
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindStaticSelectors);
  } else {
    bindStaticSelectors();
  }
})();

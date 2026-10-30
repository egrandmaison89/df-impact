/**
 * GTranslate: single dropdown (WP reference-pack static markup + translate lib).
 */
(function () {
  'use strict';

  if (window.__dfImpactGtranslateInit) {
    return;
  }
  window.__dfImpactGtranslateInit = true;

  var defaultLanguage = 'en';
  var htmlLang = (document.documentElement.getAttribute('lang') || defaultLanguage).toLowerCase();
  if (htmlLang.indexOf('en') === 0) {
    htmlLang = 'en';
  }

  window.gtranslateSettings = window.gtranslateSettings || {
    default_language: defaultLanguage,
    native_language_names: true,
    languages: ['en', 'es', 'fr', 'pt', 'zh-CN', 'ar', 'ht', 'ru', 'vi', 'ko', 'ja'],
    wrapper_selector: '.gtranslate_wrapper',
    url_structure: 'none',
  };

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

  window.doGTranslate = function (langPair) {
    if (langPair && langPair.value) {
      langPair = langPair.value;
    }
    if (!langPair) {
      return;
    }
    var parts = langPair.split('|');
    var sourceLang = parts[0];
    var targetLang = parts[1];
    loadTranslateLib(function () {
      if (window.__GT && window.__GT.translator && typeof window.__GT.translator.translate === 'function') {
        window.__GT.translator.translate(sourceLang, targetLang);
      }
    });
  };

  function bindStaticSelectors() {
    document.querySelectorAll('.gtranslate_wrapper .gt_selector').forEach(function (select) {
      if (select.dataset.dfGtranslateBound) {
        return;
      }
      select.dataset.dfGtranslateBound = '1';
      select.addEventListener('change', function () {
        window.doGTranslate(select.value);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindStaticSelectors);
  } else {
    bindStaticSelectors();
  }
})();

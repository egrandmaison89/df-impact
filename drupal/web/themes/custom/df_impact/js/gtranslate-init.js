/**
 * @file
 * Loads GTranslate dropdown (legacy WordPress GTranslate-style widget).
 */
(function () {
  'use strict';

  window.gtranslateSettings = {
    default_language: 'en',
    native_language_names: true,
    languages: ['en', 'es', 'fr', 'pt', 'zh-CN', 'ar', 'ht', 'ru', 'vi', 'ko', 'ja'],
    wrapper_selector: '.gtranslate_wrapper'
  };

  var s = document.createElement('script');
  s.src = 'https://cdn.gtranslate.net/widgets/latest/dropdown.js';
  s.defer = true;
  document.head.appendChild(s);
})();

/**
 * @file
 * Hand Drupal's jQuery back after the ported WordPress scripts.
 *
 * Those scripts need the bundled jQuery 3.3.1. Leaving it as window.jQuery
 * makes Drupal.dialog throw on Preview: the dialog widget reads $.ui.dialog
 * on a jQuery that never received jQuery UI.
 */
(function () {
  var jq = window.jQuery;
  if (!jq || !jq.fn || String(jq.fn.jquery).indexOf('3.3.1') !== 0) {
    return;
  }
  jq.noConflict(true);
})();

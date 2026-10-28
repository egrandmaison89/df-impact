/**
 * WordPress chrome behaviors (reference-pack parity).
 */
(function ($, Drupal) {
  'use strict';

  function initMegaMenu($root) {
    $root.find('.mega-menu').each(function () {
      var $menu = $(this);
      if ($menu.hasClass('mega-no-js') && typeof $.fn.maxmegamenu === 'function') {
        $menu.maxmegamenu();
        $menu.removeClass('mega-no-js');
      }
    });
  }

  Drupal.behaviors.dfImpactWpChrome = {
    attach: function (context) {
      var $context = $(context);
      initMegaMenu($context);
      if (context === document) {
        initMegaMenu($(document));
      }

      $(window)
        .off('scroll.dfImpactWpChrome')
        .on('scroll.dfImpactWpChrome', function () {
          var scrolled = $(window).scrollTop() > 1;
          $('.header-logo').toggleClass('scrolled', scrolled);
        });

      $('.disclaimer-popup, .disclaimer-popup-mobile', $context)
        .off('click.dfImpactWpChrome')
        .on('click.dfImpactWpChrome', function () {
          $(this).find('.popup-text').toggle();
        });

      // Reference-pack captures chrome with flyouts collapsed.
      $('.mega-sub-menu', $context).each(function () {
        $(this).css('display', 'none');
      });
    },
  };
})(jQuery, Drupal);

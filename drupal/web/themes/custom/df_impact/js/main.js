/**
 * @file
 * Dana-Farber Impact Magazine — main JavaScript.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Sticky header scroll behavior.
   */
  Drupal.behaviors.dfImpactHeader = {
    attach: function (context) {
      once('df-header', '.header', context).forEach(function (header) {
        var scrollThreshold = 50;

        function onScroll() {
          if (window.scrollY > scrollThreshold) {
            header.classList.add('header--scrolled');
          } else {
            header.classList.remove('header--scrolled');
          }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      });
    }
  };

  /**
   * Mobile menu toggle.
   */
  Drupal.behaviors.dfImpactMobileMenu = {
    attach: function (context) {
      once('df-mobile-menu', '.header__menu-toggle', context).forEach(function (toggle) {
        var nav = document.querySelector('.header__nav');
        if (!nav) return;

        toggle.addEventListener('click', function () {
          var isOpen = nav.classList.toggle('header__nav--open');
          toggle.setAttribute('aria-expanded', isOpen);
        });

        // Close on escape key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && nav.classList.contains('header__nav--open')) {
            nav.classList.remove('header__nav--open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
          }
        });
      });
    }
  };

})(Drupal, once);

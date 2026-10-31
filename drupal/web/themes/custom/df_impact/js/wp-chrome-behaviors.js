/**
 * WordPress chrome behaviors (reference-pack parity).
 */
(function ($, Drupal) {
  'use strict';

  function applyReducedMotionHeroVideos() {
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    $('.homepage-banner video, .wp-block-cover__video-background, .hero__cover-video').each(function () {
      var video = this;
      if (reduced) {
        video.pause();
        video.removeAttribute('autoplay');
        video.currentTime = 0;
        return;
      }
      if (video.autoplay && video.paused) {
        var playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(function () {});
        }
      }
    });
  }

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
        applyReducedMotionHeroVideos();
        var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (motionQuery.addEventListener) {
          motionQuery.addEventListener('change', applyReducedMotionHeroVideos);
        }
        else if (motionQuery.addListener) {
          motionQuery.addListener(applyReducedMotionHeroVideos);
        }
      }

      $(window)
        .off('scroll.dfImpactWpChrome')
        .on('scroll.dfImpactWpChrome', function () {
          var scrolled = $(window).scrollTop() > 50;
          // WP CSS `.desktop-header .scrolled { max-width: 250px }` applies only to `.header-logo.scrolled`.
          // Do not add `.scrolled` to `.impact-header` / `.desktop-header` or the whole header collapses.
          $('.header, .desktop-header, .impact-header').toggleClass('header--scrolled', scrolled);
          $('.header-logo').toggleClass('scrolled', scrolled);
        })
        .trigger('scroll');

      $('.disclaimer-popup, .disclaimer-popup-mobile', $context)
        .off('click.dfImpactWpChrome')
        .on('click.dfImpactWpChrome', function () {
          $(this).find('.popup-text').toggle();
        });

      $('.mega-toggle-label', $context)
        .off('click.dfImpactWpChromeMenu')
        .on('click.dfImpactWpChromeMenu', function (e) {
          e.preventDefault();
          var $wrap = $(this).closest('.mega-menu-wrap');
          var $menu = $wrap.find('.mega-menu').first();
          $wrap.toggleClass('mega-menu-open');
          $menu.toggleClass('mega-menu-open');
          var open = $wrap.hasClass('mega-menu-open');
          $(this).attr('aria-expanded', open ? 'true' : 'false');
        });
    },
  };

  // Ivory Search binds its popup trigger on window load; replay an earlier click once bound.
  if (document.readyState !== 'complete') {
    var pendingSearchLink = null;
    var captureEarlySearchClick = function (e) {
      var link = e.target.closest ? e.target.closest('.is-menu.popup > a') : null;
      if (!link) {
        return;
      }
      e.preventDefault();
      pendingSearchLink = link;
    };
    document.addEventListener('click', captureEarlySearchClick, true);
    window.addEventListener('load', function () {
      document.removeEventListener('click', captureEarlySearchClick, true);
      if (pendingSearchLink) {
        window.setTimeout(function () {
          $(pendingSearchLink).trigger('click');
        }, 0);
      }
    });
  }
})(jQuery, Drupal);

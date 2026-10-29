/** Stable viewport captures: no video frame drift, lazy images loaded, animations off. */

import { dismissOverlays, scrollForLazyLoad, waitForFonts } from './page-utils.mjs';

/**
 * Prepare both Drupal and reference HTML pages before viewport/header screenshots.
 */
export async function stabilizeParityViewport(page) {
  await dismissOverlays(page);
  await page.evaluate(() => {
    document.querySelectorAll('.homepage-banner video, .wp-block-cover__video-background').forEach((video) => {
      video.pause();
      try {
        video.currentTime = 0;
      } catch {
        /* ignore seek errors */
      }
      video.style.opacity = '0';
      video.style.pointerEvents = 'none';
    });

    // Mac WP pack captures often have video-only heroes (no poster img). Hide Drupal poster
    // backgrounds during compare so both sides match the stabilized blank cover + title.
    document
      .querySelectorAll(
        '.homepage-banner .wp-block-cover__image-background, .homepage-banner .hero__cover-poster',
      )
      .forEach((img) => {
        img.style.opacity = '0';
        img.style.pointerEvents = 'none';
      });

    document.querySelectorAll('img[data-src], img[data-lazy-src], img.lazyload').forEach((img) => {
      const src = img.getAttribute('data-src') || img.getAttribute('data-lazy-src');
      if (src && (!img.getAttribute('src') || img.src.startsWith('data:'))) {
        img.src = src;
      }
      img.classList.remove('lazyload');
      img.classList.add('lazyloaded');
    });
  });

  await scrollForLazyLoad(page);
  await waitForFonts(page);

  await page.evaluate(async () => {
    const imgs = [...document.images];
    await Promise.all(
      imgs.map((img) =>
        img.complete
          ? Promise.resolve()
          : new Promise((resolve) => {
              img.onload = () => resolve(undefined);
              img.onerror = () => resolve(undefined);
            }),
      ),
    );
  });

  await page.waitForTimeout(400);
}

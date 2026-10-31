// Captures WordPress reference HTML, CSS, JS, fonts, and screenshots for key templates.
// Run from the owner's Mac (SiteGround captchas cloud IPs). No cookies or credentials.
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
const BASE = process.env.WP_BASE_URL || 'https://danafarberimpact.org';
const OUT = path.resolve(process.env.PACK_DIR || 'reference-pack');
const PAGES = (process.env.PACK_PATHS || '/,/homepage,/fall-2026-issue,/summer-2025-issue,/2024/06/the-charlie-landers-foundation,/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials,/issues,/in-brief,/browse-issues,/past-issues,/category/cancer-research,/about-impact,/search').split(',');
const BPS = { desktop: 1440, tablet: 768, mobile: 375 };
const assetsDir = path.join(OUT, 'assets');
fs.mkdirSync(assetsDir, { recursive: true });
const assetIndex = {};
const slug = p => (p === '/' ? 'root' : p.replace(/^\/|\/$/g, '').replace(/\//g, '_'));
const browser = await chromium.launch();
for (const p of PAGES) {
  for (const [bp, width] of Object.entries(BPS)) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await ctx.newPage();
    page.on('response', async r => {
      const t = r.request().resourceType();
      if (!['stylesheet', 'script', 'font'].includes(t)) return;
      const u = r.url();
      if (assetIndex[u]) return;
      try {
        const body = await r.body();
        const name = u.replace(/^https?:\/\//, '').replace(/[?#].*$/, '').replace(/[^a-zA-Z0-9._-]/g, '_').slice(-180);
        fs.writeFileSync(path.join(assetsDir, name), body);
        assetIndex[u] = { file: `assets/${name}`, type: t, bytes: body.length };
      } catch {}
    });
    const dir = path.join(OUT, slug(p), bp);
    fs.mkdirSync(dir, { recursive: true });
    const resp = await page.goto(BASE + p, { waitUntil: 'networkidle', timeout: 60000 }).catch(e => null);
    await page.waitForTimeout(1500);
    await stabilizeParityViewport(page);
    const html = await page.content();
    const captcha = /sgcaptcha|captcha/i.test(html) && html.length < 20000;
    fs.writeFileSync(path.join(dir, 'rendered.html'), html);
    await page.screenshot({ path: path.join(dir, 'viewport.png') });
    await page.screenshot({ path: path.join(dir, 'fullpage.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    // Computed styles of key chrome elements.
    const styles = await page.evaluate(() => {
      const sel = ['header', 'header nav', '.desktop-header', '.wp-block-navigation', 'footer', '.homepage-banner', '.homepage-banner video', 'main', 'h1', 'h2', 'h3', 'p', 'a', '.wp-block-button__link'];
      const props = ['display','position','top','width','height','margin','padding','font-family','font-size','font-weight','line-height','letter-spacing','text-transform','color','background-color','background-image','border','border-bottom','box-shadow','transition','gap','justify-content','align-items','max-width'];
      const o = {};
      for (const s of sel) { const el = document.querySelector(s); if (!el) continue; const cs = getComputedStyle(el); const r = el.getBoundingClientRect(); o[s] = { rect: { x: r.x, y: r.y, w: r.width, h: r.height } }; for (const k of props) o[s][k] = cs.getPropertyValue(k); }
      return o;
    });
    fs.writeFileSync(path.join(dir, 'computed-styles.json'), JSON.stringify({ path: p, bp, status: resp?.status(), captcha, styles }, null, 2));
    console.log(`${captcha ? 'CAPTCHA' : 'ok'} ${resp?.status()} ${bp} ${p}`);
    await ctx.close();
  }
}
fs.writeFileSync(path.join(OUT, 'assets-index.json'), JSON.stringify(assetIndex, null, 2));
await browser.close();

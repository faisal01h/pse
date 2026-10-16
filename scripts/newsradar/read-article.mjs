// Renders a news article in headless Chromium and prints JSON:
//   { title, text, final_url, screenshot? }
// Usage: node read-article.mjs <article-url> [--screenshot]
// --screenshot adds a base64 JPEG of the rendered viewport (used by the admin browser test).
// Requires: npm install (playwright package) and a Chromium build for the same Playwright version.

import { chromium } from 'playwright';

const args = process.argv.slice(2);
const wantsScreenshot = args.includes('--screenshot');
const articleUrl = args.find((arg) => !arg.startsWith('--'));

if (!articleUrl || !/^https?:\/\//i.test(articleUrl)) {
    console.error('Invalid article URL');
    process.exitCode = 2;
} else {
    main(articleUrl, wantsScreenshot).catch((error) => {
        console.error(error?.message ?? String(error));
        process.exitCode = 1;
    });
}

async function main(url, withScreenshot) {
    const browser = await chromium.launch({ headless: true });

    try {
        const context = await browser.newContext({
            userAgent: 'PolitiSEE-NewsRadar/1.0',
            locale: 'id-ID',
            viewport: { width: 1280, height: 800 },
        });
        const page = await context.newPage();

        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        if (response && response.status() >= 400) {
            throw new Error(`HTTP ${response.status()}`);
        }

        // Scroll to the bottom so lazy-loaded paragraphs are rendered before reading.
        for (let i = 0; i < 10; i++) {
            const atBottom = await page.evaluate(() => {
                window.scrollBy(0, window.innerHeight * 2);
                return window.innerHeight + window.scrollY >= document.body.scrollHeight - 4;
            });
            await page.waitForTimeout(300);
            if (atBottom) break;
        }

        const article = await page.evaluate(() => {
            const candidates = [
                ...document.querySelectorAll('article, main, [role="main"], .article-body, .entry-content, .post-content'),
            ];
            if (candidates.length === 0) candidates.push(document.body);

            // Pick the container that holds the most text, so a short <article> teaser does not hide the body.
            const best = candidates.reduce((a, b) =>
                (b.innerText || '').length > (a.innerText || '').length ? b : a,
            );

            const clone = best.cloneNode(true);
            clone
                .querySelectorAll('script, style, noscript, template, iframe, svg, nav, aside, footer, form, button')
                .forEach((el) => el.remove());

            return {
                title: document.title || '',
                text: clone.textContent || '',
            };
        });

        const result = { ...article, final_url: page.url() };

        if (withScreenshot) {
            await page.evaluate(() => window.scrollTo(0, 0));
            const buffer = await page.screenshot({ type: 'jpeg', quality: 70, fullPage: false });
            result.screenshot = buffer.toString('base64');
        }

        process.stdout.write(JSON.stringify(result));
    } finally {
        await browser.close();
    }
}

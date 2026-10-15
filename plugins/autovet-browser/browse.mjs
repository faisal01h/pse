#!/usr/bin/env node
import { chromium } from 'playwright';

const url = process.argv[2];
if (!url) {
  console.error('Usage: node browse.mjs <url>');
  process.exit(2);
}

const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage();
  page.setDefaultTimeout(25000);
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  const title = await page.title();
  const text = await page.evaluate(() => {
    const body = document.body?.innerText || '';
    return body.replace(/\s+/g, ' ').trim().slice(0, 8000);
  });
  console.log(JSON.stringify({ title, text }, null, 0));
} finally {
  await browser.close();
}

// Prints JSON { package, browser, browser_path? } so the server can tell whether Playwright is ready.
// Usage: node check-playwright.mjs

import { existsSync } from 'node:fs';

let chromium = null;
try {
    ({ chromium } = await import('playwright'));
} catch {
    chromium = null;
}

if (!chromium) {
    process.stdout.write(JSON.stringify({ package: false, browser: false }));
} else {
    const browserPath = chromium.executablePath();
    process.stdout.write(JSON.stringify({ package: true, browser: existsSync(browserPath), browser_path: browserPath }));
}

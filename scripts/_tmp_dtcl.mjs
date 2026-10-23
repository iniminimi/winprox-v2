import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
const __dirname = dirname(fileURLToPath(import.meta.url));
const { chromium } = await import(pathToFileURL(join(__dirname, 'capture-pkg/node_modules/playwright/index.mjs')).href);
const browser = await chromium.launch({ args: ['--lang=en-US'] });
const page = await browser.newPage({ viewport: { width: 800, height: 400 }, locale: 'en-US' });
await page.setContent(`
<html><body>
<p>datetime-local met lang=nl-NL:</p>
<input type="datetime-local" lang="nl-NL" value="2026-03-05T18:00" style="font-size:20px">
<p>datetime-local zonder lang:</p>
<input type="datetime-local" value="2026-03-05T18:00" style="font-size:20px">
<p>time zonder lang:</p>
<input type="time" value="18:00" style="font-size:20px">
</body></html>`);
await page.waitForTimeout(500);
await page.screenshot({ path: 'scripts/_tmp_dtcl.png' });
await browser.close();
console.log('done');

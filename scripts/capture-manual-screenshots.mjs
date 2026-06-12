import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/** @type {typeof import('playwright')} */
const playwright = await importPlaywright();
const { chromium } = playwright;

async function importPlaywright() {
    const bundled = join(__dirname, 'capture-pkg/node_modules/playwright/index.mjs');
    if (existsSync(bundled)) {
        return import(pathToFileURL(bundled).href);
    }

    return import('playwright');
}

const baseUrl = (process.env.MANUAL_CAPTURE_BASE_URL ?? 'http://127.0.0.1').replace(/\/$/, '');
const hostHeader = process.env.MANUAL_CAPTURE_HOST ?? '';
const email = process.env.MANUAL_CAPTURE_EMAIL ?? '';
const password = process.env.MANUAL_CAPTURE_PASSWORD ?? '';
const outputDir = process.env.MANUAL_CAPTURE_OUTPUT_DIR ?? join(process.cwd(), 'public/images/manual');
const configPath = process.env.MANUAL_CAPTURE_CONFIG_PATH ?? join(__dirname, 'manual-capture.config.json');
const locales = ['nl', 'en', 'fr', 'de'];
const localeCookieName = 'locale';

const pathVars = {
    location_id: process.env.MANUAL_CAPTURE_LOCATION_ID ?? '',
    issue_id: process.env.MANUAL_CAPTURE_ISSUE_ID ?? '',
    task_id: process.env.MANUAL_CAPTURE_TASK_ID ?? '',
    unit_token: process.env.MANUAL_CAPTURE_UNIT_QR_TOKEN ?? '',
    team_token: process.env.MANUAL_CAPTURE_TEAM_QR_TOKEN ?? '',
};

if (!email || !password) {
    console.error('MANUAL_CAPTURE_EMAIL and MANUAL_CAPTURE_PASSWORD are required.');
    process.exit(1);
}

/** @type {{ targets: Array<{ id: string, path: string, selector: string, viewport?: { width: number, height: number }, auth?: boolean, prepareClick?: string, workerSignIn?: boolean }> }} */
const config = JSON.parse(readFileSync(configPath, 'utf8'));

const browser = await chromium.launch({ headless: true });
const contextOptions = hostHeader !== '' ? { extraHTTPHeaders: { Host: hostHeader } } : {};

try {
    const adminContext = await browser.newContext(contextOptions);
    const adminPage = await adminContext.newPage();
    await login(adminPage);

    const publicContext = await browser.newContext(contextOptions);
    const publicPage = await publicContext.newPage();

    let captured = 0;
    let skipped = 0;

    for (const locale of locales) {
        for (const target of config.targets) {
            const resolvedPath = resolvePath(target.path);
            if (resolvedPath === null) {
                console.warn(`Skip ${target.id}: unresolved path ${target.path}`);
                skipped++;
                continue;
            }

            const useAuth = target.auth !== false;
            const page = useAuth ? adminPage : publicPage;
            const context = useAuth ? adminContext : publicContext;

            await context.addCookies([
                { name: localeCookieName, value: locale, url: baseUrl },
            ]);

            const viewport = target.viewport ?? { width: 1280, height: 800 };
            await page.setViewportSize(viewport);
            await page.goto(`${baseUrl}${resolvedPath}`, { waitUntil: 'networkidle' });

            if (target.workerSignIn) {
                const signInPath = target.workerSignInPath
                    ? resolvePath(target.workerSignInPath)
                    : resolvedPath;

                if (signInPath === null) {
                    console.warn(`Skip ${target.id}: unresolved worker sign-in path`);
                    skipped++;
                    continue;
                }

                if (signInPath !== resolvedPath) {
                    await page.goto(`${baseUrl}${signInPath}`, { waitUntil: 'networkidle' });
                }

                const signedIn = await workerSignIn(page);
                if (!signedIn) {
                    console.warn(`Skip ${target.id}: worker sign-in env not set or failed`);
                    skipped++;
                    continue;
                }

                if (signInPath !== resolvedPath) {
                    await page.goto(`${baseUrl}${resolvedPath}`, { waitUntil: 'networkidle' });
                }

                await page.waitForLoadState('networkidle');
            }

            if (target.prepareClick) {
                const trigger = page.locator(target.prepareClick).first();
                await trigger.waitFor({ state: 'visible', timeout: 15_000 });
                await trigger.click();
                await page.waitForLoadState('networkidle');
            }

            const locator = page.locator(target.selector).first();
            await locator.waitFor({ state: 'visible', timeout: 30_000 });

            const localeDir = join(outputDir, locale);
            mkdirSync(localeDir, { recursive: true });
            const outputPath = join(localeDir, `${target.id}.png`);
            await locator.screenshot({ path: outputPath });
            console.log(`Captured ${locale}/${target.id}.png`);
            captured++;
        }
    }

    console.log(`Done. ${captured} screenshot(s), ${skipped} skipped. Output: ${outputDir}`);
} finally {
    await browser.close();
}

/**
 * @param {string} template
 */
function resolvePath(template) {
    let path = template;
    const placeholders = path.match(/\{[a-z_]+\}/g) ?? [];

    for (const placeholder of placeholders) {
        const key = placeholder.slice(1, -1);
        const value = pathVars[key];
        if (!value) {
            return null;
        }
        path = path.replace(placeholder, value);
    }

    return path;
}

/**
 * @param {import('playwright').Page} page
 */
async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('form.wp-auth-form button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
}

/**
 * @param {import('playwright').Page} page
 */
async function workerSignIn(page) {
    const first = process.env.MANUAL_CAPTURE_WORKER_FIRST_NAME ?? '';
    const last = process.env.MANUAL_CAPTURE_WORKER_LAST_NAME ?? '';
    const icon = process.env.MANUAL_CAPTURE_WORKER_ICON ?? '';

    if (!first || !last || !icon) {
        return false;
    }

    const firstInput = page.locator('#first_name');
    if (await firstInput.isVisible().catch(() => false)) {
        await firstInput.fill(first);
        await page.locator('#last_name').fill(last);
        await page.locator('button[type="submit"]').filter({ hasText: /.+/ }).first().click();
        await page.waitForLoadState('networkidle');
    }

    const iconButton = page.locator(`button[wire\\:click*="'${icon}'"]`).first();
    if (await iconButton.isVisible().catch(() => false)) {
        await iconButton.click();
    }

    const confirm = page.locator('button[wire\\:click="signInWithIcon"]');
    if (await confirm.isVisible().catch(() => false)) {
        await confirm.click();
        await page.waitForLoadState('networkidle');
    }

    return !(await firstInput.isVisible().catch(() => false));
}

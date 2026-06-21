import { existsSync, mkdirSync, readdirSync, readFileSync } from 'node:fs';
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
const locales = ['nl', 'en', 'fr', 'de', 'es'];

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

const browser = await chromium.launch(resolveChromiumLaunchOptions());
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
        await switchAuthenticatedLocale(adminPage, locale);

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

            if (! useAuth) {
                // Unit-portaal zet winprox_device_token; team-identify vereist schone browserstaat.
                await resetPublicPortalSession(context);
            }

            const viewport = target.viewport ?? { width: 1280, height: 800 };
            await page.setViewportSize(viewport);

            let navigationPath = resolvedPath;
            if (target.workerSignIn) {
                const signInPathPreview = target.workerSignInPath
                    ? resolvePath(target.workerSignInPath)
                    : resolvedPath;
                if (signInPathPreview !== null && signInPathPreview !== resolvedPath) {
                    navigationPath = signInPathPreview;
                }
            }

            await page.goto(captureUrl(navigationPath, locale, useAuth), { waitUntil: 'networkidle' });

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
                    await page.goto(captureUrl(signInPath, locale, useAuth), { waitUntil: 'networkidle' });
                }

                const signedIn = await workerSignIn(page);
                if (!signedIn) {
                    console.warn(`Skip ${target.id}: worker sign-in failed (check MANUAL_CAPTURE_WORKER_* and team token)`);
                    skipped++;
                    continue;
                }

                if (signInPath !== resolvedPath) {
                    await page.goto(captureUrl(resolvedPath, locale, useAuth), { waitUntil: 'networkidle' });
                }

                await page.waitForLoadState('networkidle');
            }

            if (target.prepareClick) {
                const trigger = page.locator(target.prepareClick).first();
                try {
                    await trigger.waitFor({ state: 'visible', timeout: 15_000 });
                    await trigger.click();
                    await page.waitForLoadState('networkidle');
                } catch {
                    console.warn(
                        `Skip ${target.id}: prepareClick not visible (${target.prepareClick}). `
                        + 'Zie docs/MANUAL_SCREENSHOTS.md — teamleader-release vereist een geblokkeerde collega.',
                    );
                    skipped++;
                    continue;
                }
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
 * Playwright 1.49+ zoekt standaard chromium_headless_shell; op shared hosting
 * installeren we vaak alleen het volledige chromium-* pakket (handmatig).
 * headless_shell gebruikt minder threads dan volledige chrome — vereist op Plesk.
 *
 * @returns {import('playwright').LaunchOptions}
 */
function resolveChromiumLaunchOptions() {
    const browsersPath = process.env.PLAYWRIGHT_BROWSERS_PATH ?? '';
    const lowResource = process.env.MANUAL_CAPTURE_CHROME_LOW_RESOURCE === '1';
    const chromeArgs = buildChromeArgs(lowResource);

    if (browsersPath !== '' && existsSync(browsersPath)) {
        const flatHeadlessShell = join(browsersPath, 'chrome-linux/headless_shell');
        if (existsSync(flatHeadlessShell)) {
            return { headless: true, executablePath: flatHeadlessShell, args: chromeArgs };
        }

        let fullChrome = null;

        for (const dir of readdirSync(browsersPath)) {
            if (dir.startsWith('chromium_headless_shell-')) {
                const headlessShell = join(browsersPath, dir, 'chrome-linux/headless_shell');
                if (existsSync(headlessShell)) {
                    return { headless: true, executablePath: headlessShell, args: chromeArgs };
                }
                continue;
            }

            if (! dir.startsWith('chromium-') || dir.includes('headless_shell')) {
                continue;
            }

            const chrome = join(browsersPath, dir, 'chrome-linux/chrome');
            if (existsSync(chrome)) {
                fullChrome = chrome;
            }
        }

        if (fullChrome !== null) {
            // headless: true zou Playwright 1.49+ naar chromium_headless_shell sturen;
            // met eigen chrome-binary: headless via chrome-args.
            return { headless: false, executablePath: fullChrome, args: chromeArgs };
        }
    }

    return { headless: true, args: chromeArgs };
}

/**
 * @param {boolean} lowResource
 * @returns {string[]}
 */
function buildChromeArgs(lowResource) {
    const args = [
        '--headless=new',
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--renderer-process-limit=1',
        '--no-zygote',
    ];

    if (lowResource) {
        args.push('--single-process');
    }

    return args;
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
 * @param {import('playwright').BrowserContext} context
 */
async function resetPublicPortalSession(context) {
    await context.clearCookies();
}

/**
 * Beheer: sessie-locale via /locale/{locale} (cookie alleen is niet genoeg).
 *
 * @param {import('playwright').Page} page
 * @param {string} locale
 */
async function switchAuthenticatedLocale(page, locale) {
    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.goto(`${baseUrl}/locale/${locale}`, { waitUntil: 'networkidle' });
}

/**
 * QR-portaal: ?lang= zet locale in syncLocaleFromRequest (betrouwbaarder dan cookie).
 *
 * @param {string} path
 * @param {string} locale
 * @param {boolean} useAuth
 */
function captureUrl(path, locale, useAuth) {
    if (useAuth) {
        return `${baseUrl}${path}`;
    }

    const queryIndex = path.indexOf('?');
    const pathname = queryIndex === -1 ? path : path.slice(0, queryIndex);
    const params = new URLSearchParams(queryIndex === -1 ? '' : path.slice(queryIndex + 1));
    params.set('lang', locale);

    return `${baseUrl}${pathname}?${params.toString()}`;
}

/**
 * @param {import('playwright').Page} page
 */
async function login(page) {
    const loginUrl = `${baseUrl}/login`;
    await page.goto(loginUrl, { waitUntil: 'networkidle' });

    const emailInput = page.locator('#email');
    if (! await emailInput.isVisible().catch(() => false)) {
        console.error(
            `Loginpagina heeft geen #email op ${loginUrl}. `
            + 'Controleer MANUAL_CAPTURE_BASE_URL (moet exact je browser-URL zijn, zonder /public als Apache dat al afhandelt).',
        );
        process.exit(1);
    }

    await emailInput.fill(email);
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

    const signedInMarker = page.locator(
        '[data-manual-capture="portal-team-signed-in"], [data-manual-capture="portal-unit-worker-tasks"]',
    ).first();

    if (await signedInMarker.isVisible().catch(() => false)) {
        return true;
    }

    const firstInput = page.locator('#first_name');
    if (await firstInput.isVisible().catch(() => false)) {
        await firstInput.fill(first);
        await page.locator('#last_name').fill(last);

        const submit = page.locator('[data-manual-capture="portal-team-identify"] button[type="submit"]')
            .or(page.locator('form[wire\\:submit="identifyWorker"] button[type="submit"]'));
        await submit.first().click();
        await page.locator(`button.wp-icon-tile[wire\\:click*="sign_in_icon_slug"][wire\\:click*="'${icon}'"]`)
            .first()
            .waitFor({ state: 'visible', timeout: 15_000 });
    }

    const iconButton = page.locator(
        `button.wp-icon-tile[wire\\:click*="sign_in_icon_slug"][wire\\:click*="'${icon}'"]`,
    ).first();

    if (await iconButton.isVisible().catch(() => false)) {
        await iconButton.click();
        await page.locator('button[wire\\:click="signInWithIcon"]:not([disabled])')
            .waitFor({ state: 'visible', timeout: 10_000 });
    }

    const confirm = page.locator('button[wire\\:click="signInWithIcon"]:not([disabled])');
    if (await confirm.isVisible().catch(() => false)) {
        await confirm.click();
    }

    try {
        await signedInMarker.waitFor({ state: 'visible', timeout: 20_000 });

        return true;
    } catch {
        return false;
    }
}

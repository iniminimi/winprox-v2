import { existsSync, mkdirSync, readdirSync, readFileSync, statSync } from 'node:fs';
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
const checkmateEmail = process.env.MANUAL_CAPTURE_CHECKMATE_EMAIL ?? '';
const checkmatePassword = process.env.MANUAL_CAPTURE_CHECKMATE_PASSWORD ?? '';
const onlyPrefix = process.env.MANUAL_CAPTURE_ONLY ?? '';
const outputDir = process.env.MANUAL_CAPTURE_OUTPUT_DIR ?? join(process.cwd(), 'public/images/manual');
const configPath = process.env.MANUAL_CAPTURE_CONFIG_PATH ?? join(__dirname, 'manual-capture.config.json');
const locales = process.env.MANUAL_CAPTURE_LOCALES
    ? process.env.MANUAL_CAPTURE_LOCALES.split(',').map((l) => l.trim()).filter(Boolean)
    : readdirSync(join(process.cwd(), 'lang')).filter((e) => statSync(join(process.cwd(), 'lang', e)).isDirectory()).sort();

const pathVars = {
    location_id: process.env.MANUAL_CAPTURE_LOCATION_ID ?? '',
    issue_id: process.env.MANUAL_CAPTURE_ISSUE_ID ?? '',
    task_id: process.env.MANUAL_CAPTURE_TASK_ID ?? '',
    unit_token: process.env.MANUAL_CAPTURE_UNIT_QR_TOKEN ?? '',
    clock_point_token: process.env.MANUAL_CAPTURE_CLOCK_POINT_TOKEN ?? '',
    checkmate_clock_point_token: process.env.MANUAL_CAPTURE_CHECKMATE_CLOCK_POINT_TOKEN ?? '',
};

const geo = {
    latitude: parseFloat(process.env.MANUAL_CAPTURE_GEO_LATITUDE ?? '51.0289'),
    longitude: parseFloat(process.env.MANUAL_CAPTURE_GEO_LONGITUDE ?? '4.4803'),
};

if (!email || !password) {
    console.error('MANUAL_CAPTURE_EMAIL and MANUAL_CAPTURE_PASSWORD are required.');
    process.exit(1);
}

/** @type {{ targets: Array<{ id: string, path: string, selector: string, viewport?: { width: number, height: number }, auth?: boolean, checkmate?: boolean, prepareClick?: string, steps?: Array<string|{click:string, waitFor?:string}>, cleanup?: Array<string|{click:string}>, geolocation?: boolean, workerSignIn?: boolean, optional?: boolean }> }} */
const config = JSON.parse(readFileSync(configPath, 'utf8'));

if (onlyPrefix !== '') {
    config.targets = config.targets.filter((t) => t.id.startsWith(onlyPrefix));
    if (config.targets.length === 0) {
        console.error(`MANUAL_CAPTURE_ONLY='${onlyPrefix}' matcht geen targets.`);
        process.exit(1);
    }
    console.log(`Filter: ${config.targets.length} target(s) met prefix '${onlyPrefix}'.`);
}

const checkmateTargetsPresent = config.targets.some((t) => t.checkmate === true);
const checkmateAdminNeeded = config.targets.some((t) => t.checkmate === true && t.auth !== false);
const checkmateAvailable = checkmateEmail !== '' && checkmatePassword !== '';

if (checkmateTargetsPresent && ! checkmateAvailable) {
    console.warn('Checkmate-targets aanwezig maar MANUAL_CAPTURE_CHECKMATE_EMAIL/_PASSWORD ontbreken — die shots worden overgeslagen.');
}

const browser = await chromium.launch(resolveChromiumLaunchOptions());
const contextOptions = hostHeader !== '' ? { extraHTTPHeaders: { Host: hostHeader } } : {};

try {
    const adminContext = await browser.newContext(contextOptions);
    const adminPage = await adminContext.newPage();
    await login(adminPage, email, password);

    const publicContext = await browser.newContext(contextOptions);
    const publicPage = await publicContext.newPage();

    let checkmateContext = null;
    let checkmatePage = null;
    const authPages = [adminPage];

    if (checkmateAdminNeeded && checkmateAvailable) {
        checkmateContext = await browser.newContext(contextOptions);
        checkmatePage = await checkmateContext.newPage();
        await login(checkmatePage, checkmateEmail, checkmatePassword);
        authPages.push(checkmatePage);
    }

    let captured = 0;
    let skipped = 0;

    for (const locale of locales) {
        for (const authPage of authPages) {
            await switchAuthenticatedLocale(authPage, locale);
        }

        for (const target of config.targets) {
            const resolvedPath = resolvePath(target.path);
            if (resolvedPath === null) {
                console.warn(`Skip ${target.id}: unresolved path ${target.path}`);
                skipped++;
                continue;
            }

            const useAuth = target.auth !== false;
            const useCheckmate = target.checkmate === true;

            if (useCheckmate && ! checkmateAvailable) {
                skipped++;
                continue;
            }

            if (useCheckmate && useAuth && checkmatePage === null) {
                console.warn(`Skip ${target.id}: checkmate-login niet beschikbaar.`);
                skipped++;
                continue;
            }

            const page = useCheckmate && useAuth ? checkmatePage : (useAuth ? adminPage : publicPage);
            const context = useCheckmate && useAuth ? checkmateContext : (useAuth ? adminContext : publicContext);

            if (! useAuth) {
                // Unit-portaal zet winprox_device_token; team-identify vereist schone browserstaat.
                await resetPublicPortalSession(context);
            }

            if (target.geolocation === true) {
                await context.grantPermissions(['geolocation'], { origin: baseUrl });
                await context.setGeolocation(geo);
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

                const signedIn = await workerSignIn(page, useCheckmate);
                if (!signedIn) {
                    console.warn(`Skip ${target.id}: worker sign-in failed (check MANUAL_CAPTURE_${useCheckmate ? 'CHECKMATE_' : ''}WORKER_* and team token)`);
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
                        + 'Controleer of de teamleader is ingelogd en of worker-beheer op het Clock Point-portaal beschikbaar is.',
                    );
                    skipped++;
                    continue;
                }
            }

            if (Array.isArray(target.steps)) {
                const stepsOk = await runSteps(page, target.steps);
                if (! stepsOk) {
                    console.warn(`Skip ${target.id}: een step faalde.`);
                    if (Array.isArray(target.cleanup)) {
                        await runSteps(page, target.cleanup, { ignoreErrors: true });
                    }
                    skipped++;
                    continue;
                }
            }

            const locator = page.locator(target.selector).first();
            try {
                await locator.waitFor({ state: 'visible', timeout: 30_000 });
            } catch (error) {
                if (Array.isArray(target.cleanup)) {
                    await runSteps(page, target.cleanup, { ignoreErrors: true });
                }

                if (target.optional === true) {
                    console.warn(
                        `Skip ${target.id}: selector not visible (${target.selector}). `
                        + 'Zie docs/MANUAL_SCREENSHOTS.md — ESG/IoT/Time vereisen modules + trial/abonnement op de capture-tenant.',
                    );
                    skipped++;
                    continue;
                }

                const currentPath = new URL(page.url()).pathname;
                if (currentPath.includes('/subscription')) {
                    throw new Error(
                        `Capture ${target.id}: redirect naar /subscription (trial/abonnement verlopen). `
                        + 'Draai `php artisan winprox:prepare-manual-capture` — die vernieuwt de trial van MANUAL_CAPTURE_EMAIL. '
                        + `Selector: ${target.selector}`,
                    );
                }

                throw error;
            }

            const localeDir = join(outputDir, locale);
            mkdirSync(localeDir, { recursive: true });
            const outputPath = join(localeDir, `${target.id}.png`);
            await locator.screenshot({ path: outputPath });
            console.log(`Captured ${locale}/${target.id}.png`);
            captured++;

            if (Array.isArray(target.cleanup)) {
                // Best-effort: cleanup-falen mag de capture niet breken.
                await runSteps(page, target.cleanup, { ignoreErrors: true });
            }
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
 * Sequentiële UI-stappen vóór (steps) of na (cleanup) de screenshot.
 * Elke stap is een CSS-selector of { click, waitFor }.
 *
 * @param {import('playwright').Page} page
 * @param {Array<string|{click: string, waitFor?: string}>} steps
 * @param {{ ignoreErrors?: boolean }} [options]
 */
async function runSteps(page, steps, options = {}) {
    for (const step of steps) {
        const click = typeof step === 'string' ? step : step.click;
        const waitFor = typeof step === 'string' ? null : (step.waitFor ?? null);

        try {
            const trigger = page.locator(click).first();
            await trigger.waitFor({ state: 'visible', timeout: 15_000 });
            await trigger.click();
            await page.waitForLoadState('networkidle');

            if (waitFor !== null) {
                await page.locator(waitFor).first().waitFor({ state: 'visible', timeout: 15_000 });
            }
        } catch {
            if (options.ignoreErrors === true) {
                continue;
            }

            return false;
        }
    }

    return true;
}

/**
 * @param {import('playwright').Page} page
 * @param {string} loginEmail
 * @param {string} loginPassword
 */
async function login(page, loginEmail, loginPassword) {
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

    await emailInput.fill(loginEmail);
    await page.locator('#password').fill(loginPassword);
    await page.locator('form.wp-auth-form button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
}

/**
 * @param {import('playwright').Page} page
 * @param {boolean} checkmate
 */
async function workerSignIn(page, checkmate = false) {
    const prefix = checkmate ? 'MANUAL_CAPTURE_CHECKMATE_WORKER' : 'MANUAL_CAPTURE_WORKER';
    const first = process.env[`${prefix}_FIRST_NAME`] ?? '';
    const last = process.env[`${prefix}_LAST_NAME`] ?? '';
    const icon = process.env[`${prefix}_ICON`] ?? '';

    if (!first || !last || !icon) {
        return false;
    }

    const signedInMarker = page.locator(
        '[data-manual-capture="portal-team-signed-in"], [data-manual-capture="portal-unit-worker-tasks"]',
    ).first();

    if (await signedInMarker.isVisible().catch(() => false)) {
        return true;
    }

    const iconTiles = page.locator('button.wp-icon-tile');
    const firstInput = page.locator('#first_name');
    if (await firstInput.isVisible().catch(() => false)) {
        await firstInput.fill(first);
        await page.locator('#last_name').fill(last);

        const submit = page.locator('[data-manual-capture="portal-team-identify"] button[type="submit"]');
        await submit.first().click();
        await iconTiles.first().waitFor({ state: 'visible', timeout: 20_000 });
    }

    // CSS-attribuutselectors op wire:click (colon) werpen SyntaxError in
    // headless Chromium — match de icoon-slug rechtstreeks op het attribuut.
    const iconIndex = await iconTiles.evaluateAll(
        (els, wanted) => els.findIndex(
            (el) => (el.getAttribute('wire:click') ?? '').includes(`'${wanted}'`),
        ),
        icon,
    );

    if (iconIndex >= 0) {
        await iconTiles.nth(iconIndex).click();
    }

    try {
        await page.waitForFunction(
            () => [...document.querySelectorAll('button')].some(
                (b) => (b.getAttribute('wire:click') ?? '') === 'signInWithIcon' && ! b.disabled,
            ),
            undefined,
            { timeout: 15_000 },
        );
    } catch {
        // Confirm-knop nooit enabled (icoon niet gevonden / geen verify-scherm).
    }

    await page.evaluate(() => {
        [...document.querySelectorAll('button')]
            .find((b) => (b.getAttribute('wire:click') ?? '') === 'signInWithIcon' && ! b.disabled)
            ?.click();
    });

    try {
        await signedInMarker.waitFor({ state: 'visible', timeout: 20_000 });

        return true;
    } catch {
        return false;
    }
}

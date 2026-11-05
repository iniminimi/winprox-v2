// Locale-tooling voor per-page JSON: lang/[locale]/[page].json
// Commando's: fix | check | parity  (zie npm-scripts)
// NB: lang/markets/** bevat markt-overlays (winprox.nl / winprox.be) —
// geen eigen locale; alleen tweeletterige dirs tellen als taal.
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const LANG_DIR = 'lang';
const cmd = process.argv[2] ?? 'check';

function locales() {
    return readdirSync(LANG_DIR).filter(
        (e) => statSync(join(LANG_DIR, e)).isDirectory() && /^[a-z]{2}$/.test(e),
    );
}

function jsonFiles(locale) {
    const dir = join(LANG_DIR, locale);
    return readdirSync(dir).filter((f) => f.endsWith('.json'));
}

function flatten(obj, prefix = '') {
    const keys = [];
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v && typeof v === 'object' && !Array.isArray(v)) keys.push(...flatten(v, key));
        else keys.push(key);
    }
    return keys;
}

let failed = false;
const fail = (msg) => { console.error(`✗ ${msg}`); failed = true; };

if (cmd === 'fix') {
    for (const loc of locales()) {
        for (const file of jsonFiles(loc)) {
            const path = join(LANG_DIR, loc, file);
            let raw = readFileSync(path, 'utf8');
            const stripped = raw.replace(/^\uFEFF/, '');
            if (stripped !== raw) {
                writeFileSync(path, stripped, 'utf8');
                console.log(`fixed BOM: ${path}`);
            }
        }
    }
    console.log('✓ fix:locales klaar');
}

function isPlain(value) {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function typeName(value) {
    if (Array.isArray(value)) return 'lijst';
    if (value === null) return 'null';
    return typeof value;
}

function isCardList(value) {
    return Array.isArray(value) && value.some(
        (item) => isPlain(item) && typeof item.key === 'string' && item.key !== '',
    );
}

function checkOverlayNode(base, overlay, path) {
    if (Array.isArray(overlay)) {
        if (!Array.isArray(base)) {
            fail(`${path}: overlay is een lijst, basis is ${typeName(base)}`);
            return;
        }
        overlay.forEach((value, index) => {
            if (base[index] === undefined) fail(`${path}.${index}: nummer ontbreekt in de basis`);
            else checkOverlayNode(base[index], value, `${path}.${index}`);
        });
        return;
    }

    if (isPlain(overlay)) {
        if (isCardList(base)) {
            for (const [key, value] of Object.entries(overlay)) {
                if (/^[0-9]+$/.test(key)) {
                    const index = Number(key);
                    if (base[index] === undefined) fail(`${path}.${key}: nummer ontbreekt in de basis`);
                    else checkOverlayNode(base[index], value, `${path}.${key}`);
                    continue;
                }
                const card = base.find((item) => isPlain(item) && item.key === key);
                if (!card) fail(`${path}.${key}: geen kaart met key "${key}" in de basis`);
                else checkOverlayNode(card, value, `${path}.${key}`);
            }
            return;
        }

        if (!isPlain(base) && !Array.isArray(base)) {
            fail(`${path}: overlay is een object, basis is ${typeName(base)}`);
            return;
        }

        for (const [key, value] of Object.entries(overlay)) {
            if (base[key] === undefined) fail(`${path}.${key}: nummer of sleutel ontbreekt in de basis`);
            else checkOverlayNode(base[key], value, `${path}.${key}`);
        }
        return;
    }

    if (typeof base !== typeof overlay || Array.isArray(base)) {
        fail(`${path}: overlay is ${typeName(overlay)}, basis is ${typeName(base)}`);
    }
}

function collectCardKeys(node, path, out) {
    if (Array.isArray(node)) {
        node.forEach((item, index) => {
            if (isPlain(item) && typeof item.key === 'string') out.push([`${path}[${index}]`, item.key]);
            collectCardKeys(item, `${path}[${index}]`, out);
        });
        return;
    }
    if (isPlain(node)) {
        for (const [key, value] of Object.entries(node)) {
            collectCardKeys(value, path ? `${path}.${key}` : key, out);
        }
    }
}

function checkMarketOverlays() {
    const markets = join(LANG_DIR, 'markets');
    if (!statSync(markets, { throwIfNoEntry: false })) return;

    for (const market of readdirSync(markets)) {
        const marketDir = join(markets, market);
        if (!statSync(marketDir).isDirectory()) continue;
        for (const locale of readdirSync(marketDir)) {
            const localeDir = join(marketDir, locale);
            if (!statSync(localeDir).isDirectory() || !/^[a-z]{2}$/.test(locale)) continue;
            for (const file of readdirSync(localeDir).filter((name) => name.endsWith('.json'))) {
                const overlayPath = join(localeDir, file);
                const basePath = join(LANG_DIR, locale, file);
                let overlay;
                let base;
                try {
                    overlay = JSON.parse(readFileSync(overlayPath, 'utf8'));
                    base = JSON.parse(readFileSync(basePath, 'utf8'));
                } catch (e) {
                    fail(`overlay ${overlayPath}: ${e.message}`);
                    continue;
                }
                checkOverlayNode(base, overlay, `${market}/${locale}/${file}`);
            }
        }
    }

    const page = 'product_docs.json';
    if (!locales().includes('nl')) return;
    const cardKeys = (locale) => {
        const out = [];
        collectCardKeys(JSON.parse(readFileSync(join(LANG_DIR, locale, page), 'utf8')), page, out);
        return new Map(out);
    };
    const reference = cardKeys('nl');
    for (const locale of locales()) {
        if (locale === 'nl') continue;
        const other = cardKeys(locale);
        for (const [path, key] of reference) {
            if (!other.has(path)) fail(`${page}: kaart-key ontbreekt in ${locale} op ${path}`);
            else if (other.get(path) !== key) fail(`${page}: key op ${path} is "${other.get(path)}" in ${locale}, "${key}" in nl`);
        }
        for (const path of other.keys()) {
            if (!reference.has(path)) fail(`${page}: extra kaart-key in ${locale} op ${path}`);
        }
    }
}

if (cmd === 'check' || cmd === 'parity') {
    for (const loc of locales()) {
        for (const file of jsonFiles(loc)) {
            const path = join(LANG_DIR, loc, file);
            const raw = readFileSync(path, 'utf8');
            if (raw.charCodeAt(0) === 0xfeff) fail(`UTF-8 BOM in ${path} (run npm run fix:locales)`);
            try { JSON.parse(raw); } catch (e) { fail(`ongeldige JSON in ${path}: ${e.message}`); }
        }
    }
    if (!failed) checkMarketOverlays();
    if (!failed && cmd === 'check') console.log('✓ check:locales: alle JSON geldig');
}

if (cmd === 'parity' && !failed) {
    const locs = locales();
    const pages = new Set(locs.flatMap((l) => jsonFiles(l)));
    for (const page of pages) {
        const keysPerLocale = {};
        for (const loc of locs) {
            const path = join(LANG_DIR, loc, page);
            try {
                keysPerLocale[loc] = new Set(flatten(JSON.parse(readFileSync(path, 'utf8'))));
            } catch {
                fail(`ontbrekend/ongeldig: ${path}`);
                keysPerLocale[loc] = new Set();
            }
        }
        const reference = keysPerLocale[locs[0]];
        for (const loc of locs.slice(1)) {
            for (const k of reference) if (!keysPerLocale[loc].has(k)) fail(`${page}: sleutel "${k}" ontbreekt in ${loc}`);
            for (const k of keysPerLocale[loc]) if (!reference.has(k)) fail(`${page}: extra sleutel "${k}" in ${loc} (niet in ${locs[0]})`);
        }
    }
    if (!failed) console.log('✓ check:locales:parity: alle talen hebben identieke sleutels');
}

process.exit(failed ? 1 : 0);

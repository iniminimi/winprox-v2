// Locale-tooling voor per-page JSON: lang/[locale]/[page].json
// Commando's: fix | check | parity  (zie npm-scripts)
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const LANG_DIR = 'lang';
const cmd = process.argv[2] ?? 'check';

function locales() {
    return readdirSync(LANG_DIR).filter((e) => statSync(join(LANG_DIR, e)).isDirectory());
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

if (cmd === 'check' || cmd === 'parity') {
    for (const loc of locales()) {
        for (const file of jsonFiles(loc)) {
            const path = join(LANG_DIR, loc, file);
            const raw = readFileSync(path, 'utf8');
            if (raw.charCodeAt(0) === 0xfeff) fail(`UTF-8 BOM in ${path} (run npm run fix:locales)`);
            try { JSON.parse(raw); } catch (e) { fail(`ongeldige JSON in ${path}: ${e.message}`); }
        }
    }
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

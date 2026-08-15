// UTF-8 BOM check — breekt Livewire/Alpine/login lokaal. Zie .cursor/rules/no-utf8-bom.mdc
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOT = process.cwd();
const BOM = Buffer.from([0xef, 0xbb, 0xbf]);
const SCAN_ROOTS = ['app', 'bootstrap', 'config', 'routes', 'resources', 'lang', 'public', 'scripts'];
const EXTENSIONS = new Set(['.php', '.blade.php', '.js', '.css', '.json', '.md', '.mjs', '.cjs']);
const SKIP_DIRS = new Set(['vendor', 'node_modules', 'build', 'capture-pkg']);

function rel(path) {
    return relative(ROOT, path).replaceAll('\\', '/');
}

function walkTextSources(dir, files = []) {
    let entries;
    try {
        entries = readdirSync(dir, { withFileTypes: true });
    } catch {
        return files;
    }

    for (const entry of entries) {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            if (SKIP_DIRS.has(entry.name)) {
                continue;
            }
            walkTextSources(path, files);
            continue;
        }
        if (!entry.isFile()) {
            continue;
        }
        const lower = entry.name.toLowerCase();
        const ext = lower.endsWith('.blade.php')
            ? '.blade.php'
            : lower.includes('.')
                ? `.${lower.split('.').pop()}`
                : '';
        if (EXTENSIONS.has(ext) || lower === '.env' || lower.startsWith('.env.')) {
            files.push(path);
        }
    }

    return files;
}

/**
 * @returns {string[]} Relatieve paden met UTF-8 BOM.
 */
export function findUtf8BomFiles() {
    const hits = [];

    for (const root of SCAN_ROOTS) {
        for (const file of walkTextSources(join(ROOT, root))) {
            const buf = readFileSync(file);
            if (buf.length >= 3 && buf.subarray(0, 3).equals(BOM)) {
                hits.push(rel(file));
            }
        }
    }

    for (const envName of ['.env', '.env.example', '.env.testing']) {
        try {
            const buf = readFileSync(join(ROOT, envName));
            if (buf.length >= 3 && buf.subarray(0, 3).equals(BOM)) {
                hits.push(envName);
            }
        } catch {
            // bestand bestaat niet
        }
    }

    return hits;
}

const isMain = process.argv[1] && process.argv[1].replaceAll('\\', '/').endsWith('/scripts/check-bom.mjs');

if (isMain) {
    const hits = findUtf8BomFiles();
    if (hits.length > 0) {
        console.error(`✗ UTF-8 BOM verboden (${hits.length}): ${hits.slice(0, 12).join(', ')}${hits.length > 12 ? '…' : ''}`);
        console.error('Schrijf UTF-8 zonder BOM (Python: encoding="utf-8", niet utf-8-sig). Zie .cursor/rules/no-utf8-bom.mdc');
        process.exit(1);
    }
    console.log('✓ Geen UTF-8 BOM in bronbestanden');
    process.exit(0);
}

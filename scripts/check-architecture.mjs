// Architectuur-compliance (Integration First). Zie WINPROX_RULES.md §3 + §13.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOT = process.cwd();
let failed = false;

const fail = (msg) => {
    console.error(`✗ ${msg}`);
    failed = true;
};

const pass = (msg) => console.log(`✓ ${msg}`);

function walk(dir, files = []) {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            if (entry.name === 'vendor' || entry.name === 'node_modules') {
                continue;
            }
            walk(path, files);
        } else if (entry.isFile() && path.endsWith('.php')) {
            files.push(path);
        }
    }
    return files;
}

function rel(path) {
    return relative(ROOT, path).replaceAll('\\', '/');
}

function scanPatterns(files, patterns, label) {
    const hits = [];
    for (const file of files) {
        const content = readFileSync(file, 'utf8');
        const lines = content.split('\n');
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (line.trimStart().startsWith('//') || line.trimStart().startsWith('*')) {
                continue;
            }
            for (const pattern of patterns) {
                if (pattern.test(line)) {
                    hits.push(`${rel(file)}:${i + 1}`);
                    break;
                }
            }
        }
    }
    if (hits.length > 0) {
        fail(`${label} (${hits.length}): ${hits.slice(0, 8).join(', ')}${hits.length > 8 ? '…' : ''}`);
    } else {
        pass(label);
    }
}

const livewireFiles = walk(join(ROOT, 'app/Livewire'));
const viewFiles = walk(join(ROOT, 'resources/views')).filter(
    (f) => !f.includes(`${join('resources', 'views', 'emails')}`)
        && !f.includes(`${join('resources', 'views', 'mail')}`),
);
const nonActionAppFiles = walk(join(ROOT, 'app')).filter(
    (f) => !f.includes(`${join('app', 'Actions')}`)
        && !f.includes(`${join('app', 'Providers')}`)
        && !f.includes(`${join('app', 'Console', 'Kernel')}`),
);

scanPatterns(livewireFiles, [
    /::create\s*\(/,
    /->update\s*\(/,
    /->delete\s*\(/,
    /->save\s*\(/,
    /DB::/,
], 'Livewire: geen Model-mutaties of DB::');

scanPatterns(nonActionAppFiles, [
    /DB::/,
], 'app/* (buiten Actions): geen DB::');

scanPatterns(viewFiles, [
    /\bisAdmin\s*\(/,
], 'Blade (beheer): geen isAdmin() — gebruik @can/authorize');

// UTF-8 BOM breekt Livewire JS / login (vóór elke response) — zie WINPROX_RULES.md §2 / no-utf8-bom.mdc
const BOM = Buffer.from([0xef, 0xbb, 0xbf]);
const bomScanRoots = ['app', 'bootstrap', 'config', 'routes', 'resources', 'lang', 'public', 'scripts'];
const bomExtensions = new Set(['.php', '.blade.php', '.js', '.css', '.json', '.md', '.mjs', '.cjs']);
const bomHits = [];

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
            if (entry.name === 'vendor' || entry.name === 'node_modules' || entry.name === 'build' || entry.name === 'capture-pkg') {
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
        if (bomExtensions.has(ext) || lower === '.env' || lower.startsWith('.env.')) {
            files.push(path);
        }
    }
    return files;
}

for (const root of bomScanRoots) {
    for (const file of walkTextSources(join(ROOT, root))) {
        const buf = readFileSync(file);
        if (buf.length >= 3 && buf.subarray(0, 3).equals(BOM)) {
            bomHits.push(rel(file));
        }
    }
}

// Root .env / .env.example (niet onder een scan-root) — BOM hier breekt ook login lokaal
for (const envName of ['.env', '.env.example', '.env.testing']) {
    const envPath = join(ROOT, envName);
    try {
        const buf = readFileSync(envPath);
        if (buf.length >= 3 && buf.subarray(0, 3).equals(BOM)) {
            bomHits.push(envName);
        }
    } catch {
        // bestand bestaat niet
    }
}

if (bomHits.length > 0) {
    fail(`UTF-8 BOM verboden (${bomHits.length}): ${bomHits.slice(0, 8).join(', ')}${bomHits.length > 8 ? '…' : ''}`);
} else {
    pass('Geen UTF-8 BOM in bronbestanden');
}

if (!failed) {
    pass('check:architecture — alle harde regels OK');
    process.exit(0);
}

console.error('\nZie WINPROX_RULES.md §3 en §13. Fix alleen wat je aanraakt of wat deze check blokkeert.');
process.exit(1);

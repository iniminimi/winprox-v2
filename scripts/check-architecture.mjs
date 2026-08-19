// Architectuur-compliance (Integration First). Zie WINPROX_RULES.md §3 + §13.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { findUtf8BomFiles } from './check-bom.mjs';

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

// Tailwind scant ook storage/framework/views (@source in resources/css/app.css). Zonder warme
// viewcache mist de CSS-bundel klassen, dus de build moet view:cache blijven doen (§6.6).
const buildScript = JSON.parse(readFileSync(join(ROOT, 'package.json'), 'utf8')).scripts?.build ?? '';
if (buildScript.includes('view:cache')) {
    pass('Build warmt de viewcache vóór vite build');
} else {
    fail('package.json "build" mist `php artisan view:cache` — CSS-bundel raakt klassen kwijt (§6.6)');
}

const bomHits = findUtf8BomFiles();
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

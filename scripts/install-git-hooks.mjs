#!/usr/bin/env node
/**
 * Installeert repo git-hooks (core.hooksPath = .githooks).
 * Draait via npm prepare, zodat BOM-check bij elke commit actief is.
 */
import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const hooksDir = join(process.cwd(), '.githooks');
if (!existsSync(hooksDir)) {
    process.exit(0);
}

try {
    execSync('git rev-parse --is-inside-work-tree', { stdio: 'ignore' });
} catch {
    process.exit(0);
}

try {
    execSync('git config core.hooksPath .githooks', { stdio: 'ignore' });
    console.log('✓ git hooks: core.hooksPath=.githooks');
} catch (error) {
    console.warn('⚠ kon core.hooksPath niet zetten:', error?.message ?? error);
}

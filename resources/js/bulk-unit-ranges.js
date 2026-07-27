/**
 * Alpine UI for one bulk unit range: live preview without Livewire roundtrips.
 * Submit still goes through Livewire → Form Request → BulkCreateUnitsAction.
 */

const MAX_UNITS = 500;
const PREVIEW_LIMIT = 16;

function emptyRange() {
    return {
        start: '',
        count: 1,
        padding: '',
        prefix: '',
        suffix: '',
    };
}

/**
 * @param {Record<string, unknown>} range
 * @returns {string[]}
 */
export function namesFromRange(range) {
    const startStr = String(range?.start ?? '').trim();
    if (!/^\d+$/.test(startStr)) {
        return [];
    }

    const count = Number.parseInt(String(range?.count ?? ''), 10);
    if (!Number.isFinite(count) || count < 1) {
        return [];
    }

    const paddingRaw = range?.padding;
    let padding;
    if (paddingRaw === '' || paddingRaw === null || paddingRaw === undefined) {
        padding = startStr.length;
    } else {
        padding = Number.parseInt(String(paddingRaw), 10);
        if (!Number.isFinite(padding) || padding < startStr.length) {
            return [];
        }
    }

    const prefix = String(range?.prefix ?? '');
    const suffix = String(range?.suffix ?? '');
    const start = Number.parseInt(startStr, 10);
    const names = [];

    for (let i = 0; i < count; i++) {
        names.push(prefix + String(start + i).padStart(padding, '0') + suffix);
    }

    return names;
}

/**
 * @param {string[]} names
 * @returns {string[]}
 */
export function duplicateNames(names) {
    const counts = new Map();
    for (const name of names) {
        counts.set(name, (counts.get(name) ?? 0) + 1);
    }

    return [...counts.entries()].filter(([, n]) => n > 1).map(([name]) => name);
}

/**
 * @param {string[]} names
 */
export function previewFromNames(names) {
    const duplicates = duplicateNames(names);
    const total = names.length;
    const truncated = total > PREVIEW_LIMIT;
    const previewNames = truncated
        ? [...names.slice(0, PREVIEW_LIMIT - 1), names[total - 1]]
        : [...names];

    return {
        names,
        duplicates,
        total,
        truncated,
        previewNames,
        hasDuplicates: duplicates.length > 0,
    };
}

function registerAlpineComponent() {
    if (!window.Alpine || window.__wpBulkUnitRangesRegistered) {
        return;
    }

    window.__wpBulkUnitRangesRegistered = true;

    window.Alpine.data('wpBulkUnitRanges', (config = {}) => ({
        range: {
            ...emptyRange(),
            ...(Array.isArray(config.initialRanges) && config.initialRanges[0]
                ? config.initialRanges[0]
                : (config.initialRange ?? {})),
        },
        categoryId: config.categoryId ?? '',
        i18n: config.i18n ?? {},
        maxUnits: config.maxUnits ?? MAX_UNITS,
        submitting: false,

        get preview() {
            const names = namesFromRange(this.range);
            if (names.length > this.maxUnits) {
                return {
                    names: [],
                    duplicates: [],
                    total: 0,
                    truncated: false,
                    previewNames: [],
                    hasDuplicates: false,
                };
            }

            return previewFromNames(names);
        },

        get canSubmit() {
            return this.preview.total > 0 && !this.preview.hasDuplicates && !this.submitting;
        },

        batchCountLabel(count) {
            return String(this.i18n.batchCount ?? ':count').replaceAll(':count', String(count));
        },

        submitLabel(count) {
            return String(this.i18n.submitCount ?? ':count').replaceAll(':count', String(count));
        },

        duplicatesLabel(count) {
            return String(this.i18n.duplicatesCount ?? ':count').replaceAll(':count', String(count));
        },

        rangeForSubmit() {
            const padding = this.range.padding;

            return {
                start: String(this.range.start ?? '').trim(),
                count: Number.parseInt(String(this.range.count ?? '0'), 10) || 0,
                padding: padding === '' || padding === null || padding === undefined
                    ? null
                    : Number.parseInt(String(padding), 10),
                prefix: String(this.range.prefix ?? ''),
                suffix: String(this.range.suffix ?? ''),
            };
        },

        async submit(wire) {
            if (!this.canSubmit || !wire) {
                return;
            }

            this.submitting = true;
            try {
                const categoryId = this.categoryId === '' || this.categoryId === null
                    ? null
                    : Number.parseInt(String(this.categoryId), 10);

                await wire.set('bulkRanges', [this.rangeForSubmit()]);
                await wire.set('bulkCategoryId', Number.isFinite(categoryId) ? categoryId : null);
                await wire.createBulk();
            } finally {
                this.submitting = false;
            }
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

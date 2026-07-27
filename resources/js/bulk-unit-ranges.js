/**
 * Alpine UI for bulk unit ranges: live preview without Livewire roundtrips.
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
 * @param {Array<Record<string, unknown>>} ranges
 * @returns {string[]}
 */
export function namesFromRanges(ranges) {
    const names = [];

    for (const range of ranges) {
        const startStr = String(range?.start ?? '').trim();
        if (!/^\d+$/.test(startStr)) {
            continue;
        }

        const count = Number.parseInt(String(range?.count ?? ''), 10);
        if (!Number.isFinite(count) || count < 1) {
            continue;
        }

        const paddingRaw = range?.padding;
        let padding;
        if (paddingRaw === '' || paddingRaw === null || paddingRaw === undefined) {
            padding = startStr.length;
        } else {
            padding = Number.parseInt(String(paddingRaw), 10);
            if (!Number.isFinite(padding) || padding < startStr.length) {
                continue;
            }
        }

        const prefix = String(range?.prefix ?? '');
        const suffix = String(range?.suffix ?? '');
        const start = Number.parseInt(startStr, 10);

        for (let i = 0; i < count; i++) {
            names.push(prefix + String(start + i).padStart(padding, '0') + suffix);
        }
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
        ranges: Array.isArray(config.initialRanges) && config.initialRanges.length > 0
            ? config.initialRanges.map((range) => ({ ...emptyRange(), ...range }))
            : [emptyRange()],
        categoryId: config.categoryId ?? '',
        i18n: config.i18n ?? {},
        maxUnits: config.maxUnits ?? MAX_UNITS,
        submitting: false,

        get preview() {
            const names = namesFromRanges(this.ranges);
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

        rangeLabel(index) {
            return String(this.i18n.rangeLabel ?? 'Range :n').replaceAll(':n', String(index + 1));
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

        addRange() {
            this.ranges.push(emptyRange());
        },

        removeRange(index) {
            if (this.ranges.length <= 1) {
                return;
            }
            this.ranges.splice(index, 1);
        },

        rangesForSubmit() {
            return this.ranges.map((range) => {
                const padding = range.padding;
                return {
                    start: String(range.start ?? '').trim(),
                    count: Number.parseInt(String(range.count ?? '0'), 10) || 0,
                    padding: padding === '' || padding === null || padding === undefined
                        ? null
                        : Number.parseInt(String(padding), 10),
                    prefix: String(range.prefix ?? ''),
                    suffix: String(range.suffix ?? ''),
                };
            });
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

                await wire.set('bulkRanges', this.rangesForSubmit());
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

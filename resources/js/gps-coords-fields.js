/**
 * Paste Google Maps "lat, lng" into location/unit GPS fields.
 * Maps itself stays a free maps.google.com tab — no Maps API.
 */

const PAIR_PATTERN = /^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/;

function looksLikeGpsPair(text) {
    const normalized = String(text ?? '').trim().replace(/\s+/g, ' ');
    if (!PAIR_PATTERN.test(normalized)) {
        return false;
    }
    const match = normalized.match(PAIR_PATTERN);
    const lat = Number.parseFloat(match[1]);
    const lng = Number.parseFloat(match[2]);

    return Number.isFinite(lat) && Number.isFinite(lng)
        && lat >= -90 && lat <= 90
        && lng >= -180 && lng <= 180;
}

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpGpsCoordsFields) {
        return;
    }
    window.Alpine.__wpGpsCoordsFields = true;
    window.Alpine.data('wpGpsCoordsFields', (config = {}) => ({
        latProperty: String(config.latProperty ?? ''),
        lngProperty: String(config.lngProperty ?? ''),
        applyMethod: String(config.applyMethod ?? ''),
        searchProperties: Array.isArray(config.searchProperties) ? config.searchProperties : [],
        searchQuery: String(config.searchQuery ?? ''),
        pair: '',
        mapsOpened: false,
        _onVisible: null,

        init() {
            this.syncPairFromWire();
            this._onVisible = () => {
                if (document.visibilityState && document.visibilityState !== 'visible') {
                    return;
                }
                this.readClipboardAfterMaps();
            };
            document.addEventListener('visibilitychange', this._onVisible);
            window.addEventListener('focus', this._onVisible);
        },

        destroy() {
            if (this._onVisible) {
                document.removeEventListener('visibilitychange', this._onVisible);
                window.removeEventListener('focus', this._onVisible);
            }
        },

        syncPairFromWire() {
            const lat = String(this.$wire.get(this.latProperty) ?? '').trim();
            const lng = String(this.$wire.get(this.lngProperty) ?? '').trim();
            this.pair = lat !== '' && lng !== '' ? `${lat}, ${lng}` : '';
        },

        mapsUrl() {
            const lat = String(this.$wire.get(this.latProperty) ?? '').trim();
            const lng = String(this.$wire.get(this.lngProperty) ?? '').trim();
            if (lat !== '' && lng !== '') {
                return 'https://www.google.com/maps/search/?api=1&query='
                    + encodeURIComponent(`${lat},${lng}`);
            }

            const parts = this.searchProperties
                .map((name) => String(this.$wire.get(name) ?? '').trim())
                .filter((value) => value !== '');
            if (this.searchQuery !== '') {
                parts.unshift(this.searchQuery);
            }
            const query = parts.join(' ').trim();
            if (query !== '') {
                return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query);
            }

            return 'https://www.google.com/maps';
        },

        markMapsOpened() {
            this.mapsOpened = true;
        },

        async applyText(text, { allowEmpty = false } = {}) {
            if (this.applyMethod === '') {
                return false;
            }
            const trimmed = String(text ?? '').trim().replace(/\s+/g, ' ');
            if (trimmed === '') {
                if (!allowEmpty) {
                    return false;
                }
            } else if (!looksLikeGpsPair(trimmed)) {
                return false;
            }
            const ok = await this.$wire[this.applyMethod](trimmed);
            if (ok) {
                this.syncPairFromWire();
            }

            return Boolean(ok);
        },

        async onPaste(event) {
            const text = event.clipboardData?.getData('text') ?? '';
            if (!looksLikeGpsPair(text)) {
                return;
            }
            event.preventDefault();
            await this.applyText(text);
        },

        async onBlur() {
            await this.applyText(this.pair, { allowEmpty: true });
        },

        async readClipboardAfterMaps() {
            if (!this.mapsOpened || !navigator.clipboard?.readText) {
                return;
            }
            this.mapsOpened = false;
            try {
                const text = await navigator.clipboard.readText();
                await this.applyText(text);
            } catch {
                // Clipboard permission is optional; paste still works.
            }
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

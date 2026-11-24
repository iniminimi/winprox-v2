/**
 * WinProx OPS: camera-barcode voor sticker-S/N (1D/QR) op de gsm.
 * Zelfde focus-pad als wp-portal-qr-scanner (continuous autofocus op Android).
 * BarcodeDetector waar beschikbaar; anders handmatige invoer.
 */

const DECODE_EVERY_NTH_FRAME = 2;
const CONFIRM_READS = 2;

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpOpsBarcodeScanner) {
        return;
    }
    window.Alpine.__wpOpsBarcodeScanner = true;

    window.Alpine.data('wpOpsBarcodeScanner', (config = {}) => ({
        i18n: config.i18n ?? {},
        scannerOpen: false,
        errorKey: null,
        slowHint: false,
        torchOn: false,
        torchAvailable: false,
        _stream: null,
        _track: null,
        _detector: null,
        _detectBusy: false,
        _frame: null,
        _frameNo: 0,
        _openAt: 0,
        _lastValue: null,
        _matchCount: 0,
        _focusTimer: null,

        get supported() {
            return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
        },

        get canDetect() {
            return typeof window.BarcodeDetector === 'function';
        },

        get errorText() {
            return this.errorKey !== null ? (this.i18n[this.errorKey] ?? '') : '';
        },

        openScanner() {
            this.errorKey = null;
            this.slowHint = false;
            this.torchOn = false;
            this.torchAvailable = false;
            this._lastValue = null;
            this._matchCount = 0;
            this._frameNo = 0;
            this._openAt = Date.now();
            this.scannerOpen = true;
            if (!this.supported) {
                this.errorKey = 'unsupported';
                return;
            }
            if (!this.canDetect) {
                this.errorKey = 'unsupported';
                return;
            }
            this.$nextTick(() => this.startCamera());
        },

        async startCamera() {
            try {
                const formats = await window.BarcodeDetector.getSupportedFormats();
                const wanted = ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'data_matrix', 'upc_a', 'upc_e', 'itf', 'codabar'];
                const use = wanted.filter((f) => Array.isArray(formats) && formats.includes(f));
                if (use.length === 0) {
                    this.errorKey = 'unsupported';
                    return;
                }
                this._detector = new window.BarcodeDetector({ formats: use });
            } catch (e) {
                this.errorKey = 'unsupported';
                return;
            }

            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                });
            } catch (e) {
                try {
                    this._stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                } catch (e2) {
                    this.errorKey = 'denied';
                    return;
                }
            }

            this._track = this._stream.getVideoTracks()[0] ?? null;
            await this.applyFocusAndZoom();
            // Sommige Android-toestellen negeren focus tot de stream echt loopt.
            this._focusTimer = window.setTimeout(() => {
                this.applyFocusAndZoom();
            }, 800);

            const video = this.$refs.scanVideo;
            if (!video) {
                this.stopStream();
                return;
            }
            video.srcObject = this._stream;
            try {
                await video.play();
            } catch (e) {}
            this.decodeLoop(video);
        },

        async applyFocusAndZoom() {
            const track = this._track;
            if (!track || typeof track.getCapabilities !== 'function') {
                return;
            }
            try {
                const caps = track.getCapabilities() || {};
                const advanced = [];
                if (Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
                    advanced.push({ focusMode: 'continuous' });
                }
                // Sticker dichtbij: lichte zoom als de lens het toelaat.
                if (caps.zoom && typeof caps.zoom.min === 'number' && typeof caps.zoom.max === 'number') {
                    const target = Math.min(caps.zoom.max, Math.max(caps.zoom.min, (caps.zoom.min + 2)));
                    if (target > caps.zoom.min) {
                        advanced.push({ zoom: target });
                    }
                }
                if (advanced.length > 0) {
                    await track.applyConstraints({ advanced });
                }
                this.torchAvailable = caps.torch === true;
            } catch (e) {}
        },

        async tapToFocus(event) {
            const track = this._track;
            if (!track || typeof track.getCapabilities !== 'function') {
                return;
            }
            try {
                const caps = track.getCapabilities() || {};
                const advanced = [];
                if (Array.isArray(caps.focusMode)) {
                    if (caps.focusMode.includes('single-shot')) {
                        advanced.push({ focusMode: 'single-shot' });
                    } else if (caps.focusMode.includes('continuous')) {
                        advanced.push({ focusMode: 'continuous' });
                    }
                }
                // Optioneel: focuspunt via Points of Interest (Chrome Android).
                if (Array.isArray(caps.pointsOfInterest) || caps.pointsOfInterest === true) {
                    const video = this.$refs.scanVideo;
                    if (video && event?.currentTarget) {
                        const rect = event.currentTarget.getBoundingClientRect();
                        const x = (event.clientX - rect.left) / Math.max(1, rect.width);
                        const y = (event.clientY - rect.top) / Math.max(1, rect.height);
                        advanced.push({ pointsOfInterest: [{ x, y }] });
                    }
                }
                if (advanced.length > 0) {
                    await track.applyConstraints({ advanced });
                }
            } catch (e) {}
        },

        async toggleTorch() {
            const track = this._track;
            if (!track || !this.torchAvailable) {
                return;
            }
            try {
                this.torchOn = !this.torchOn;
                await track.applyConstraints({ advanced: [{ torch: this.torchOn }] });
            } catch (e) {
                this.torchOn = false;
            }
        },

        decodeLoop(video) {
            if (!this.scannerOpen || this._stream === null || !this._detector) {
                return;
            }
            this._frameNo += 1;
            if (!this.slowHint && Date.now() - this._openAt > 12000) {
                this.slowHint = true;
            }
            if (
                !this._detectBusy
                && video.readyState >= 2
                && video.videoWidth > 0
                && this._frameNo % DECODE_EVERY_NTH_FRAME === 0
            ) {
                this._detectBusy = true;
                this._detector.detect(video).then((hits) => {
                    this._detectBusy = false;
                    const hit = Array.isArray(hits) ? hits.find((h) => h && h.rawValue) : null;
                    if (hit && this.scannerOpen) {
                        this.handleDecoded(String(hit.rawValue));
                    } else {
                        this._lastValue = null;
                        this._matchCount = 0;
                    }
                }).catch(() => {
                    this._detectBusy = false;
                });
            }
            if (this.scannerOpen) {
                this._frame = window.requestAnimationFrame(() => this.decodeLoop(video));
            }
        },

        handleDecoded(value) {
            const trimmed = value.trim();
            if (!trimmed) {
                return;
            }
            // Twee gelijke frames op rij — 1D-barcodes geven soms een vals positief.
            if (trimmed === this._lastValue) {
                this._matchCount += 1;
            } else {
                this._lastValue = trimmed;
                this._matchCount = 1;
            }
            if (this._matchCount < CONFIRM_READS) {
                return;
            }
            this.closeScanner();
            if (typeof this.$wire?.onBarcodeScanned === 'function') {
                this.$wire.onBarcodeScanned(trimmed);
            }
        },

        closeScanner() {
            this.scannerOpen = false;
            this.errorKey = null;
            this.slowHint = false;
            this.torchOn = false;
            if (this._frame !== null) {
                window.cancelAnimationFrame(this._frame);
                this._frame = null;
            }
            if (this._focusTimer !== null) {
                window.clearTimeout(this._focusTimer);
                this._focusTimer = null;
            }
            this.stopStream();
        },

        stopStream() {
            if (this._stream !== null) {
                this._stream.getTracks().forEach((track) => track.stop());
                this._stream = null;
            }
            this._track = null;
            const video = this.$refs.scanVideo;
            if (video) {
                video.srcObject = null;
            }
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);
if (window.Alpine) {
    registerAlpineComponent();
}

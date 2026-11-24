/**
 * WinProx OPS: camera-barcode voor sticker-S/N (1D/QR) op de gsm.
 * BarcodeDetector waar beschikbaar; anders handmatige invoer in de UI.
 */

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpOpsBarcodeScanner) {
        return;
    }
    window.Alpine.__wpOpsBarcodeScanner = true;

    window.Alpine.data('wpOpsBarcodeScanner', (config = {}) => ({
        i18n: config.i18n ?? {},
        scannerOpen: false,
        errorKey: null,
        _stream: null,
        _detector: null,
        _detectBusy: false,
        _frame: null,

        get supported() {
            return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function'
                && typeof window.BarcodeDetector === 'function');
        },

        get errorText() {
            return this.errorKey !== null ? (this.i18n[this.errorKey] ?? '') : '';
        },

        openScanner() {
            this.errorKey = null;
            this.scannerOpen = true;
            if (!this.supported) {
                this.errorKey = 'unsupported';
                return;
            }
            this.$nextTick(() => this.startCamera());
        },

        async startCamera() {
            try {
                const formats = await window.BarcodeDetector.getSupportedFormats();
                const wanted = ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'data_matrix', 'upc_a', 'upc_e'];
                const use = wanted.filter((f) => Array.isArray(formats) && formats.includes(f));
                this._detector = new window.BarcodeDetector({
                    formats: use.length > 0 ? use : ['qr_code', 'code_128'],
                });
            } catch (e) {
                this.errorKey = 'unsupported';
                return;
            }

            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
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

        decodeLoop(video) {
            if (!this.scannerOpen || this._stream === null || !this._detector) {
                return;
            }
            if (!this._detectBusy && video.readyState >= 2) {
                this._detectBusy = true;
                this._detector.detect(video).then((hits) => {
                    this._detectBusy = false;
                    const hit = Array.isArray(hits) ? hits.find((h) => h && h.rawValue) : null;
                    if (hit && this.scannerOpen) {
                        this.handleDecoded(String(hit.rawValue));
                    }
                }).catch(() => {
                    this._detectBusy = false;
                });
            }
            this._frame = requestAnimationFrame(() => this.decodeLoop(video));
        },

        handleDecoded(value) {
            const trimmed = value.trim();
            if (!trimmed) {
                return;
            }
            this.closeScanner();
            if (typeof this.$wire?.onBarcodeScanned === 'function') {
                this.$wire.onBarcodeScanned(trimmed);
            }
        },

        closeScanner() {
            this.scannerOpen = false;
            this.stopStream();
        },

        stopStream() {
            if (this._frame) {
                cancelAnimationFrame(this._frame);
                this._frame = null;
            }
            if (this._stream) {
                this._stream.getTracks().forEach((t) => t.stop());
                this._stream = null;
            }
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

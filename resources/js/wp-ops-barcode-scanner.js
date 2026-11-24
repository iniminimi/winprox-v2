/**
 * WinProx OPS: camera-barcode voor kleine sticker-S/N (~2 cm × 5 mm).
 * QR: volle frame (werkt al). 1D: crop van de smalle leeszone + opschalen
 * + sterke zoom/dichtbij-focus — anders blijven de streepjes te klein.
 */

const DECODE_EVERY_NTH_FRAME = 2;
const CONFIRM_READS = 2;
/** Zelfde verhouding als .wp-scan-frame--barcode (CSS inset). */
const CROP = { left: 0.06, top: 0.40, width: 0.88, height: 0.20 };
const UPSCALE_PASSES = [2, 3, 4];

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
        photoBusy: false,
        photoError: null,
        _stream: null,
        _track: null,
        _detector1d: null,
        _detectorQr: null,
        _detectBusy: false,
        _frame: null,
        _frameNo: 0,
        _openAt: 0,
        _lastValue: null,
        _matchCount: 0,
        _focusTimer: null,
        _canvas: null,
        _ctx: null,

        get supported() {
            return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
        },

        get canDetect() {
            return typeof window.BarcodeDetector === 'function';
        },

        get errorText() {
            return this.errorKey !== null ? (this.i18n[this.errorKey] ?? '') : '';
        },

        get photoErrorText() {
            return this.photoError !== null ? (this.i18n[this.photoError] ?? '') : '';
        },

        async ensureDetectors() {
            if (this._detector1d || this._detectorQr) {
                return true;
            }
            if (!this.canDetect) {
                return false;
            }
            try {
                const formats = await window.BarcodeDetector.getSupportedFormats();
                const oneD = ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar', 'data_matrix']
                    .filter((f) => Array.isArray(formats) && formats.includes(f));
                const qr = ['qr_code'].filter((f) => Array.isArray(formats) && formats.includes(f));
                if (oneD.length === 0 && qr.length === 0) {
                    return false;
                }
                if (oneD.length > 0) {
                    this._detector1d = new window.BarcodeDetector({ formats: oneD });
                }
                if (qr.length > 0) {
                    this._detectorQr = new window.BarcodeDetector({ formats: qr });
                }
                return true;
            } catch (e) {
                return false;
            }
        },

        ensureCanvas() {
            if (!this._canvas) {
                this._canvas = document.createElement('canvas');
                this._ctx = this._canvas.getContext('2d', { willReadFrequently: true, alpha: false });
            }
        },

        openPhotoPicker(useCapture) {
            this.photoError = null;
            const input = useCapture ? this.$refs.photoCapture : this.$refs.photoGallery;
            if (input) {
                input.value = '';
                input.click();
            }
        },

        async onPhotoSelected(event) {
            const file = event?.target?.files?.[0];
            if (!file) {
                return;
            }
            this.photoBusy = true;
            this.photoError = null;
            try {
                if (! await this.ensureDetectors()) {
                    this.photoError = 'unsupported';
                    return;
                }
                this.ensureCanvas();
                const bitmap = await this.loadImageBitmap(file);
                if (!bitmap) {
                    this.photoError = 'photo_failed';
                    return;
                }
                const value = await this.detectFromStill(bitmap);
                if (typeof bitmap.close === 'function') {
                    bitmap.close();
                }
                if (!value) {
                    this.photoError = 'photo_no_code';
                    return;
                }
                this.applyScanResult(value);
            } catch (e) {
                this.photoError = 'photo_failed';
            } finally {
                this.photoBusy = false;
                if (event?.target) {
                    event.target.value = '';
                }
            }
        },

        async loadImageBitmap(file) {
            try {
                if (typeof createImageBitmap === 'function') {
                    return await createImageBitmap(file);
                }
            } catch (e) {}
            return await new Promise((resolve, reject) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    URL.revokeObjectURL(url);
                    resolve(img);
                };
                img.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('image_load'));
                };
                img.src = url;
            });
        },

        /**
         * Stilstaande foto: volle beeld + horizontale stroken × opschalen.
         * Native camera-app focust wél; live getUserMedia vaak niet.
         */
        async detectFromStill(source) {
            const w = source.width || source.naturalWidth || 0;
            const h = source.height || source.naturalHeight || 0;
            if (w < 8 || h < 8) {
                return null;
            }

            if (this._detectorQr) {
                try {
                    const qrHits = await this._detectorQr.detect(source);
                    const qr = Array.isArray(qrHits) ? qrHits.find((hit) => hit && hit.rawValue) : null;
                    if (qr) {
                        return String(qr.rawValue);
                    }
                } catch (e) {}
            }

            if (this._detector1d) {
                try {
                    const fullHits = await this._detector1d.detect(source);
                    const full = Array.isArray(fullHits) ? fullHits.find((hit) => hit && hit.rawValue) : null;
                    if (full) {
                        return String(full.rawValue);
                    }
                } catch (e) {}

                const bands = [
                    { left: 0.02, top: 0.30, width: 0.96, height: 0.40 },
                    { left: 0.05, top: 0.40, width: 0.90, height: 0.20 },
                    { left: 0.08, top: 0.45, width: 0.84, height: 0.12 },
                    { left: 0.00, top: 0.20, width: 1.00, height: 0.60 },
                ];
                for (const band of bands) {
                    for (const scale of [1, 2, 3, 4]) {
                        const canvas = this.drawBand(source, w, h, band, scale);
                        if (!canvas) {
                            continue;
                        }
                        try {
                            const hits = await this._detector1d.detect(canvas);
                            const hit = Array.isArray(hits) ? hits.find((item) => item && item.rawValue) : null;
                            if (hit) {
                                return String(hit.rawValue);
                            }
                        } catch (e) {}
                    }
                }
            }

            return null;
        },

        drawBand(source, w, h, band, scale) {
            if (!this._ctx) {
                return null;
            }
            const sx = Math.floor(w * band.left);
            const sy = Math.floor(h * band.top);
            const sw = Math.max(8, Math.floor(w * band.width));
            const sh = Math.max(8, Math.floor(h * band.height));
            this._canvas.width = Math.round(sw * scale);
            this._canvas.height = Math.round(sh * scale);
            this._ctx.imageSmoothingEnabled = scale > 1 ? false : true;
            this._ctx.drawImage(source, sx, sy, sw, sh, 0, 0, this._canvas.width, this._canvas.height);
            return this._canvas;
        },

        applyScanResult(value) {
            const trimmed = String(value).trim();
            if (!trimmed) {
                return;
            }
            this.closeScanner();
            this.photoError = null;
            if (typeof this.$wire?.onBarcodeScanned === 'function') {
                this.$wire.onBarcodeScanned(trimmed);
            }
        },

        openScanner() {
            this.errorKey = null;
            this.photoError = null;
            this.slowHint = false;
            this.torchOn = false;
            this.torchAvailable = false;
            this._lastValue = null;
            this._matchCount = 0;
            this._frameNo = 0;
            this._openAt = Date.now();
            this.scannerOpen = true;
            if (!this.supported || !this.canDetect) {
                this.errorKey = 'unsupported';
                return;
            }
            this.$nextTick(() => this.startCamera());
        },

        async startCamera() {
            if (! await this.ensureDetectors()) {
                this.errorKey = 'unsupported';
                return;
            }
            this.ensureCanvas();

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
            await this.applyFocusAndZoom(true);
            this._focusTimer = window.setTimeout(() => {
                this.applyFocusAndZoom(true);
            }, 700);

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

        async applyFocusAndZoom(aggressive = false) {
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
                // Kleine sticker dichtbij: focusDistance naar dichtbij als de lens dat kan.
                if (caps.focusDistance && typeof caps.focusDistance.min === 'number') {
                    const near = caps.focusDistance.min;
                    const far = typeof caps.focusDistance.max === 'number' ? caps.focusDistance.max : near;
                    const target = near + (far - near) * 0.08;
                    advanced.push({ focusDistance: target });
                }
                // Sterke digitale zoom: streepjes van ~2 cm moeten het kader vullen.
                if (caps.zoom && typeof caps.zoom.min === 'number' && typeof caps.zoom.max === 'number') {
                    const span = caps.zoom.max - caps.zoom.min;
                    const factor = aggressive ? 0.55 : 0.35;
                    const target = Math.min(
                        caps.zoom.max,
                        Math.max(caps.zoom.min, caps.zoom.min + Math.max(1.5, span * factor)),
                    );
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
                if (caps.focusDistance && typeof caps.focusDistance.min === 'number') {
                    advanced.push({ focusDistance: caps.focusDistance.min });
                }
                if (Array.isArray(caps.pointsOfInterest) || caps.pointsOfInterest === true) {
                    const el = event?.currentTarget;
                    if (el) {
                        const rect = el.getBoundingClientRect();
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

        /**
         * Knip de smalle leeszone uit en schaal ×2–4 zodat 5 mm streepjes
         * genoeg pixels hebben voor Code 128.
         */
        buildCropCanvas(video, scale) {
            const vw = video.videoWidth;
            const vh = video.videoHeight;
            if (vw < 16 || vh < 16 || !this._ctx) {
                return null;
            }
            const sx = Math.floor(vw * CROP.left);
            const sy = Math.floor(vh * CROP.top);
            const sw = Math.max(8, Math.floor(vw * CROP.width));
            const sh = Math.max(8, Math.floor(vh * CROP.height));
            this._canvas.width = Math.round(sw * scale);
            this._canvas.height = Math.round(sh * scale);
            // Nearest-neighbor: houdt zwarte/witte strepen scherp bij opschalen.
            this._ctx.imageSmoothingEnabled = false;
            this._ctx.drawImage(
                video,
                sx, sy, sw, sh,
                0, 0, this._canvas.width, this._canvas.height,
            );
            return this._canvas;
        },

        async detectFrame(video) {
            // 1) QR: volle frame (groot genoeg, werkt al).
            if (this._detectorQr) {
                try {
                    const qrHits = await this._detectorQr.detect(video);
                    const qr = Array.isArray(qrHits) ? qrHits.find((h) => h && h.rawValue) : null;
                    if (qr) {
                        return String(qr.rawValue);
                    }
                } catch (e) {}
            }

            // 2) 1D: crop + multi-scale upscale van de smalle zone.
            if (this._detector1d) {
                const scale = UPSCALE_PASSES[Math.floor(this._frameNo / DECODE_EVERY_NTH_FRAME) % UPSCALE_PASSES.length];
                const canvas = this.buildCropCanvas(video, scale);
                if (canvas) {
                    try {
                        const hits = await this._detector1d.detect(canvas);
                        const hit = Array.isArray(hits) ? hits.find((h) => h && h.rawValue) : null;
                        if (hit) {
                            return String(hit.rawValue);
                        }
                    } catch (e) {}
                }
            }

            return null;
        },

        decodeLoop(video) {
            if (!this.scannerOpen || this._stream === null) {
                return;
            }
            this._frameNo += 1;
            if (!this.slowHint && Date.now() - this._openAt > 10000) {
                this.slowHint = true;
            }
            if (
                !this._detectBusy
                && video.readyState >= 2
                && video.videoWidth > 0
                && this._frameNo % DECODE_EVERY_NTH_FRAME === 0
            ) {
                this._detectBusy = true;
                this.detectFrame(video).then((value) => {
                    this._detectBusy = false;
                    if (value && this.scannerOpen) {
                        this.handleDecoded(value);
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
            if (trimmed === this._lastValue) {
                this._matchCount += 1;
            } else {
                this._lastValue = trimmed;
                this._matchCount = 1;
            }
            if (this._matchCount < CONFIRM_READS) {
                return;
            }
            this.applyScanResult(trimmed);
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

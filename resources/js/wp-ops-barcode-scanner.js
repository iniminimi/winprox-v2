/**
 * WinProx OPS: sticker-S/N via systeemcamera-foto.
 * Eerst barcode op de stilstaande foto, anders OCR op de cijfers eronder.
 */

/**
 * Haalt een serienummer-achtige digit-run uit OCR/barcode-tekst.
 * Seeed-stickers: typisch 10–24 cijfers onder de barcode.
 */
function extractSerialCandidate(text) {
    if (!text) {
        return null;
    }
    const raw = String(text);
    let best = null;
    const spaced = raw.match(/\d(?:[\d\s\-_.]{8,30})\d/g) || [];
    for (const run of spaced) {
        const digits = run.replace(/\D/g, '');
        if (digits.length >= 10 && digits.length <= 24 && (!best || digits.length > best.length)) {
            best = digits;
        }
    }
    const compact = raw.replace(/[\s\-_.]/g, '');
    const blocks = compact.match(/\d{10,24}/g) || [];
    for (const digits of blocks) {
        if (!best || digits.length > best.length) {
            best = digits;
        }
    }
    return best;
}

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpOpsBarcodeScanner) {
        return;
    }
    window.Alpine.__wpOpsBarcodeScanner = true;

    window.Alpine.data('wpOpsBarcodeScanner', (config = {}) => ({
        i18n: config.i18n ?? {},
        photoBusy: false,
        photoError: null,
        _detector1d: null,
        _detectorQr: null,
        _canvas: null,
        _ctx: null,

        get photoErrorText() {
            return this.photoError !== null ? (this.i18n[this.photoError] ?? '') : '';
        },

        async ensureDetectors() {
            if (this._detector1d || this._detectorQr) {
                return true;
            }
            if (typeof window.BarcodeDetector !== 'function') {
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

        openPhotoCapture() {
            this.photoError = null;
            const input = this.$refs.photoCapture;
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
                await this.ensureDetectors();
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

        async detectFromStill(source) {
            const w = source.width || source.naturalWidth || 0;
            const h = source.height || source.naturalHeight || 0;
            if (w < 8 || h < 8) {
                return null;
            }

            const fromBarcode = await this.detectBarcodeStill(source, w, h);
            if (fromBarcode) {
                return fromBarcode;
            }

            return await this.detectSerialText(source, w, h);
        },

        async detectBarcodeStill(source, w, h) {
            if (this._detectorQr) {
                try {
                    const qrHits = await this._detectorQr.detect(source);
                    const qr = Array.isArray(qrHits) ? qrHits.find((hit) => hit && hit.rawValue) : null;
                    if (qr) {
                        const serial = extractSerialCandidate(qr.rawValue) || String(qr.rawValue).trim();
                        if (serial) {
                            return serial;
                        }
                    }
                } catch (e) {}
            }

            if (!this._detector1d) {
                return null;
            }

            try {
                const fullHits = await this._detector1d.detect(source);
                const full = Array.isArray(fullHits) ? fullHits.find((hit) => hit && hit.rawValue) : null;
                if (full) {
                    return extractSerialCandidate(full.rawValue) || String(full.rawValue).trim();
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
                            return extractSerialCandidate(hit.rawValue) || String(hit.rawValue).trim();
                        }
                    } catch (e) {}
                }
            }

            return null;
        },

        async detectSerialText(source, w, h) {
            const textBands = [
                { left: 0.02, top: 0.45, width: 0.96, height: 0.50 },
                { left: 0.05, top: 0.55, width: 0.90, height: 0.35 },
                { left: 0.00, top: 0.00, width: 1.00, height: 1.00 },
            ];

            if (typeof window.TextDetector === 'function') {
                try {
                    const textDetector = new window.TextDetector();
                    for (const band of textBands) {
                        const canvas = this.drawBand(source, w, h, band, 2);
                        const target = canvas || source;
                        const hits = await textDetector.detect(target);
                        const joined = Array.isArray(hits)
                            ? hits.map((hit) => hit?.rawValue || '').join(' ')
                            : '';
                        const serial = extractSerialCandidate(joined);
                        if (serial) {
                            return serial;
                        }
                    }
                } catch (e) {}
            }

            try {
                const { createWorker } = await import('tesseract.js');
                const worker = await createWorker('eng', 1, {
                    workerPath: 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/worker.min.js',
                    corePath: 'https://cdn.jsdelivr.net/npm/tesseract.js-core@5/tesseract-core.wasm.js',
                    langPath: 'https://tessdata.projectnaptha.com/4.0.0',
                });
                await worker.setParameters({
                    tessedit_char_whitelist: '0123456789',
                    tessedit_pageseg_mode: '6',
                });
                try {
                    for (const band of textBands) {
                        const canvas = this.drawBand(source, w, h, band, 2);
                        if (!canvas) {
                            continue;
                        }
                        const { data } = await worker.recognize(canvas);
                        const serial = extractSerialCandidate(data?.text || '');
                        if (serial) {
                            return serial;
                        }
                    }
                } finally {
                    await worker.terminate();
                }
            } catch (e) {}

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
            this.photoError = null;
            if (typeof this.$wire?.onBarcodeScanned === 'function') {
                this.$wire.onBarcodeScanned(trimmed);
            }
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);
if (window.Alpine) {
    registerAlpineComponent();
}

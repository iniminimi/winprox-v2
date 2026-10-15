/**
 * In-portal QR-scanner (ItsMe-stijl) voor het gsm-portaal.
 * Opent een camera-overlay, leest de Clock Point-QR (/time/{token}) en
 * navigeert ernaartoe. De bestaande scan-grant op die route doet de rest —
 * er is dus geen aparte backend-flow nodig. Kan de browser geen camera
 * gebruiken, dan verwijst de melding naar de gewone camera-app (de huidige
 * omweg blijft gewoon werken).
 */

const CLOCK_POINT_TOKEN_PATTERN = /\/time\/([a-z0-9]{20,})/i;
// Multi-scale: volle resolutie voor kleine/dichte codes, lagere resoluties
// smeren LCD-moiré en dot-rendering uit tot massieve modules.
const DECODE_WIDTHS = [1280, 768, 512];
const DECODE_EVERY_NTH_FRAME = 3;

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpPortalQrScanner) {
        return;
    }
    window.Alpine.__wpPortalQrScanner = true;

    window.Alpine.data('wpPortalQrScanner', (config = {}) => ({
        i18n: config.i18n ?? {},
        scannerOpen: false,
        errorKey: null,
        slowHint: false,
        _stream: null,
        _frame: null,
        _jsqr: null,
        _detector: null,
        _detectBusy: false,
        _canvas: null,
        _ctx: null,
        _frameNo: 0,
        _openAt: 0,

        get supported() {
            return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
        },

        get errorText() {
            return this.errorKey !== null ? (this.i18n[this.errorKey] ?? '') : '';
        },

        openScanner() {
            this.errorKey = null;
            this.slowHint = false;
            this._openAt = Date.now();
            this.scannerOpen = true;
            if (!this.supported) {
                this.errorKey = 'unsupported';
                return;
            }
            this.$nextTick(() => this.startCamera());
        },

        async startCamera() {
            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
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

            // Continue autofocus — zonder dit blijft de camera op veel
            // Android-toestellen op oneindig staan en wordt een kleine
            // scherm-QR (dot-rendering) onscherp en onleesbaar.
            const track = this._stream.getVideoTracks()[0];
            try {
                const caps = track?.getCapabilities ? track.getCapabilities() : {};
                if (Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
                    await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                }
            } catch (e) {}

            const video = this.$refs.scanVideo;
            if (!video) {
                this.stopStream();
                return;
            }
            video.srcObject = this._stream;
            try {
                await video.play();
            } catch (e) {}

            // BarcodeDetector gebruikt op Android de ML-decoder van het OS —
            // die leest scherm-QR's (dot-rendering, inversie, moiré) waar pure
            // JS-decoders zoals jsQR op vastlopen. Ontbreekt hij (o.a. iOS),
            // dan blijft jsQR het decode-pad.
            try {
                if (typeof window.BarcodeDetector === 'function') {
                    const formats = await window.BarcodeDetector.getSupportedFormats();
                    if (Array.isArray(formats) && formats.includes('qr_code')) {
                        this._detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                    }
                }
            } catch (e) {}

            try {
                const mod = await import('jsqr');
                this._jsqr = mod.default ?? mod;
            } catch (e) {
                if (!this._detector) {
                    this.errorKey = 'unsupported';
                    this.stopStream();
                    return;
                }
            }

            this._canvas = document.createElement('canvas');
            this._ctx = this._canvas.getContext('2d', { willReadFrequently: true });
            this.decodeLoop(video);
        },

        decodeLoop(video) {
            if (!this.scannerOpen || this._stream === null) {
                return;
            }
            this._frameNo += 1;
            if (!this.slowHint && Date.now() - this._openAt > 12000) {
                this.slowHint = true;
            }
            if (this._detector && !this._detectBusy && video.readyState >= 2) {
                this._detectBusy = true;
                this._detector.detect(video).then((hits) => {
                    this._detectBusy = false;
                    const hit = Array.isArray(hits) ? hits.find((h) => h && h.rawValue) : null;
                    if (hit && this.scannerOpen) {
                        this.handleDecoded(hit.rawValue);
                    }
                }).catch(() => {
                    this._detectBusy = false;
                });
            }
            if (this._jsqr && video.readyState >= 2 && video.videoWidth > 0 && this._frameNo % DECODE_EVERY_NTH_FRAME === 0) {
                const pass = Math.floor(this._frameNo / DECODE_EVERY_NTH_FRAME) % DECODE_WIDTHS.length;
                const scale = Math.min(1, DECODE_WIDTHS[pass] / video.videoWidth);
                this._canvas.width = Math.round(video.videoWidth * scale);
                this._canvas.height = Math.round(video.videoHeight * scale);
                this._ctx.drawImage(video, 0, 0, this._canvas.width, this._canvas.height);
                try {
                    const frame = this._ctx.getImageData(0, 0, this._canvas.width, this._canvas.height);
                    const hit = this._jsqr(frame.data, frame.width, frame.height, {
                        inversionAttempts: 'attemptBoth',
                    });
                    if (hit && hit.data) {
                        this.handleDecoded(hit.data);
                    }
                } catch (e) {}
            }
            if (this.scannerOpen) {
                this._frame = window.requestAnimationFrame(() => this.decodeLoop(video));
            }
        },

        handleDecoded(text) {
            const match = String(text).match(CLOCK_POINT_TOKEN_PATTERN);
            if (!match) {
                this.errorKey = 'invalid';
                return;
            }
            window.location.assign('/time/' + match[1]);
        },

        stopStream() {
            if (this._stream !== null) {
                this._stream.getTracks().forEach((track) => track.stop());
                this._stream = null;
            }
            const video = this.$refs.scanVideo;
            if (video) {
                video.srcObject = null;
            }
        },

        closeScanner() {
            this.scannerOpen = false;
            this.errorKey = null;
            this.slowHint = false;
            if (this._frame !== null) {
                window.cancelAnimationFrame(this._frame);
                this._frame = null;
            }
            this.stopStream();
        },

        destroy() {
            this.closeScanner();
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

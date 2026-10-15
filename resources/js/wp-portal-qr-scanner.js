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
        _stream: null,
        _frame: null,
        _jsqr: null,
        _canvas: null,
        _ctx: null,
        _frameNo: 0,

        get supported() {
            return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
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
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
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

            const video = this.$refs.scanVideo;
            if (!video) {
                this.stopStream();
                return;
            }
            video.srcObject = this._stream;
            try {
                await video.play();
            } catch (e) {}

            try {
                const mod = await import('jsqr');
                this._jsqr = mod.default ?? mod;
            } catch (e) {
                this.errorKey = 'unsupported';
                this.stopStream();
                return;
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
            if (video.readyState >= 2 && video.videoWidth > 0 && this._frameNo % DECODE_EVERY_NTH_FRAME === 0) {
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

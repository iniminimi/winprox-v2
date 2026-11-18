/**
 * Web Bluetooth presence-token van het klokscherm.
 * Eerste keer: browserscherm (verplicht). Daarna: één tik via getDevices
 * + opgeslagen device-id (geen kieslijst meer).
 */

const SERVICE = '8f1c2a10-7b3e-4d5a-9c11-6e5b4a3d2c01';
const TOKEN_CHAR = '8f1c2a10-7b3e-4d5a-9c11-6e5b4a3d2c02';

function storageKey(clockPointId) {
    return 'wp_ble_device_' + String(clockPointId || '0');
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForAdvertisement(device, ms = 2500) {
    if (typeof device.watchAdvertisements !== 'function') {
        return;
    }
    try {
        await Promise.race([
            new Promise((resolve) => {
                const onAdv = () => {
                    device.removeEventListener('advertisementreceived', onAdv);
                    resolve();
                };
                device.addEventListener('advertisementreceived', onAdv);
                device.watchAdvertisements().catch(() => resolve());
            }),
            new Promise((resolve) => setTimeout(resolve, ms)),
        ]);
    } catch (e) {
        // Oudere Chrome: gewoon doorverbinden.
    }
}

function bytesToHex(value) {
    const bytes = new Uint8Array(value.buffer, value.byteOffset, value.byteLength);
    if (bytes.length !== 32) {
        throw new Error('bad_len');
    }
    let nonzero = false;
    for (let i = 0; i < bytes.length; i++) {
        if (bytes[i] !== 0) {
            nonzero = true;
            break;
        }
    }
    if (!nonzero) {
        throw new Error('empty_token');
    }
    return [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
}

async function readPresenceHexOnce(device, waitMs) {
    await waitForAdvertisement(device, waitMs);
    if (!device.gatt) {
        throw new Error('no_gatt');
    }
    if (device.gatt.connected) {
        try {
            device.gatt.disconnect();
            await sleep(150);
        } catch (e) {}
    }
    await device.gatt.connect();
    await sleep(120);
    const svc = await device.gatt.getPrimaryService(SERVICE);
    const ch = await svc.getCharacteristic(TOKEN_CHAR);
    await ch.readValue();
    await sleep(40);
    const value = await ch.readValue();
    try {
        device.gatt.disconnect();
    } catch (e) {}
    return bytesToHex(value);
}

async function readPresenceHex(device, waitMs = 2500) {
    let lastErr = null;
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            return await readPresenceHexOnce(device, waitMs);
        } catch (e) {
            lastErr = e;
            try {
                if (device.gatt?.connected) {
                    device.gatt.disconnect();
                }
            } catch (err) {}
            await sleep(150 + attempt * 150);
        }
    }
    throw lastErr || new Error('read_failed');
}

async function tryKnownDevices(clockPointId) {
    if (typeof navigator.bluetooth.getDevices !== 'function') {
        return { hex: '', device: null };
    }
    const known = await navigator.bluetooth.getDevices();
    if (!Array.isArray(known) || known.length === 0) {
        return { hex: '', device: null };
    }

    const savedId = localStorage.getItem(storageKey(clockPointId));
    const ordered = savedId
        ? [...known.filter((d) => d.id === savedId), ...known.filter((d) => d.id !== savedId)]
        : known;

    for (const device of ordered) {
        try {
            // Korte advertentie-wacht: bekende devices zijn al gekoppeld.
            const hex = await readPresenceHex(device, 1200);
            if (hex.length === 64) {
                return { hex, device };
            }
        } catch (e) {}
    }

    return { hex: '', device: null };
}

async function pickNewDevice() {
    return navigator.bluetooth.requestDevice({
        filters: [{ namePrefix: 'WinProx' }],
        optionalServices: [SERVICE],
    });
}

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpPortalBle) {
        return;
    }
    window.Alpine.__wpPortalBle = true;

    window.Alpine.data('wpPortalBle', (config = {}) => ({
        clockPointId: config.clockPointId ?? 0,
        direction: config.direction ?? 'in',
        i18n: config.i18n ?? {},
        busy: false,
        error: '',
        unsupported: !('bluetooth' in navigator),
        paired: false,

        init() {
            try {
                this.paired = !!localStorage.getItem(storageKey(this.clockPointId));
            } catch (e) {
                this.paired = false;
            }
        },

        clearPairing() {
            try {
                localStorage.removeItem(storageKey(this.clockPointId));
            } catch (e) {}
            this.paired = false;
        },

        async yes() {
            if (this.busy || this.unsupported) {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                let { hex, device } = await tryKnownDevices(this.clockPointId);
                if (hex.length !== 64) {
                    const picked = await pickNewDevice();
                    hex = await readPresenceHex(picked, 2500);
                    device = picked;
                }
                if (hex.length !== 64) {
                    this.clearPairing();
                    this.error = this.i18n.not_found || '';
                    return;
                }
                // Pas na server-OK pairing bewaren — anders blijft een foute
                // sessie stil hergebruiken zonder nieuw browserscherm.
                const ok = await this.$wire.submitBlePresenceToken(hex, this.direction);
                if (!ok) {
                    this.clearPairing();
                    this.error = this.i18n.rejected || this.i18n.failed || '';
                    return;
                }
                if (device?.id) {
                    try {
                        localStorage.setItem(storageKey(this.clockPointId), device.id);
                        this.paired = true;
                    } catch (e) {}
                }
            } catch (e) {
                const name = e?.name || '';
                const msg = String(e?.message || '');
                if (name === 'NotFoundError') {
                    this.error = this.i18n.not_found || '';
                } else if (name === 'SecurityError' || name === 'NotAllowedError') {
                    this.error = this.i18n.permission || '';
                } else if (name === 'NetworkError' || name === 'InvalidStateError' || name === 'AbortError') {
                    this.clearPairing();
                    this.error = this.i18n.bt_off || '';
                } else if (msg === 'empty_token' || msg === 'bad_len' || msg === 'no_gatt') {
                    this.clearPairing();
                    this.error = this.i18n.not_found || '';
                } else {
                    this.clearPairing();
                    this.error = this.i18n.failed || '';
                }
            } finally {
                this.busy = false;
            }
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

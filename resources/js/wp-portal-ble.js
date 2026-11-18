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

async function waitForAdvertisement(device, ms = 4000) {
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

async function readPresenceHex(device) {
    await waitForAdvertisement(device);
    if (!device.gatt) {
        throw new Error('no_gatt');
    }
    if (!device.gatt.connected) {
        await device.gatt.connect();
    }
    const svc = await device.gatt.getPrimaryService(SERVICE);
    const ch = await svc.getCharacteristic(TOKEN_CHAR);
    // NimBLE levert bij de eerste read soms de oude (lege) value; onRead
    // vernieuwt pas daarna. Tweede read pakt het verse presence-token.
    await ch.readValue();
    const value = await ch.readValue();
    try {
        device.gatt.disconnect();
    } catch (e) {}
    const bytes = new Uint8Array(value.buffer, value.byteOffset, value.byteLength);
    if (bytes.length !== 32) {
        throw new Error('bad_len');
    }
    // Alles-nul = nog geen UTC op het scherm → behandelen als mislukte scan.
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
        // Geen name-filter: getDevices levert vaak een lege name tot connect.
        try {
            const hex = await readPresenceHex(device);
            if (hex.length === 64) {
                return { hex, device };
            }
        } catch (e) {}
    }

    return { hex: '', device: null };
}

async function pickNewDevice() {
    return navigator.bluetooth.requestDevice({
        filters: [{ namePrefix: 'WinProx', services: [SERVICE] }],
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
                    hex = await readPresenceHex(picked);
                    device = picked;
                }
                if (hex.length !== 64) {
                    this.error = this.i18n.not_found || '';
                    return;
                }
                if (device?.id) {
                    try {
                        localStorage.setItem(storageKey(this.clockPointId), device.id);
                        this.paired = true;
                    } catch (e) {}
                }
                await this.$wire.submitBlePresenceToken(hex, this.direction);
            } catch (e) {
                const name = e?.name || '';
                if (name === 'NotFoundError') {
                    this.error = this.i18n.not_found || '';
                } else if (name === 'SecurityError' || name === 'NotAllowedError') {
                    this.error = this.i18n.permission || '';
                } else if (name === 'NetworkError' || name === 'InvalidStateError') {
                    this.error = this.i18n.bt_off || '';
                } else {
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

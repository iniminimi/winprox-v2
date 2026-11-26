/**
 * Beginscherm-snelkoppelingen: een tik opent, vasthouden tilt de tegel
 * los en die volgt de aanwijzer. De nieuwe volgorde gaat naar Livewire → Action.
 */

const HOLD_MS = 220;
const SLOP = 8;

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpIntentTiles) {
        return;
    }
    window.Alpine.__wpIntentTiles = true;
    window.Alpine.data('wpIntentTiles', () => ({
        pending: null,
        holdTimer: null,
        armed: false,
        didMove: false,
        suppressClick: false,

        onPointerDown(event) {
            const tile = event.target.closest('[data-wp-intent-tile]');
            if (!tile || !this.$el.contains(tile)) {
                return;
            }
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            this.clearHold();
            this.pending = {
                tile,
                x: event.clientX,
                y: event.clientY,
                pointerX: event.clientX,
                pointerY: event.clientY,
                id: event.pointerId,
                placeholder: null,
                offsetX: 0,
                offsetY: 0,
            };
            this.armed = false;
            this.didMove = false;
            this.holdTimer = setTimeout(() => this.arm(), HOLD_MS);
        },

        gridEl() {
            return this.$el.querySelector('[data-wp-intent-grid]');
        },

        arm() {
            if (!this.pending) {
                return;
            }
            this.armed = true;
            this.gridEl()?.classList.add('is-sorting');
            this.lift();
            try {
                this.pending.tile.setPointerCapture(this.pending.id);
            } catch {
                // De pointer is al los.
            }
            const dx = this.pending.pointerX - this.pending.x;
            const dy = this.pending.pointerY - this.pending.y;
            if (dx * dx + dy * dy > SLOP * SLOP) {
                this.didMove = true;
            }
            this.follow(this.pending.pointerX, this.pending.pointerY);
        },

        lift() {
            const tile = this.pending.tile;
            const rect = tile.getBoundingClientRect();
            const placeholder = document.createElement('div');
            placeholder.className = 'wp-intent-placeholder';
            placeholder.style.minHeight = `${rect.height}px`;
            placeholder.setAttribute('aria-hidden', 'true');
            const fromHidden = tile.dataset.wpIntentHidden === '1';
            this.pending.home = tile.parentNode;
            this.pending.homeNext = tile.nextSibling;
            this.pending.fromHidden = fromHidden;
            this.pending.overBin = false;
            if (!fromHidden) {
                tile.before(placeholder);
            }
            this.pending.placeholder = placeholder;
            this.pending.offsetX = this.pending.x - rect.left;
            this.pending.offsetY = this.pending.y - rect.top;
            tile.classList.add('is-dragging');
            tile.style.width = `${rect.width}px`;
            tile.style.height = `${rect.height}px`;
            document.body.appendChild(tile);
            if (fromHidden) {
                this.concealHiddenModal();
            }
        },

        onPointerMove(event) {
            if (!this.pending || event.pointerId !== this.pending.id) {
                return;
            }
            this.pending.pointerX = event.clientX;
            this.pending.pointerY = event.clientY;
            if (!this.armed) {
                if (event.pointerType !== 'mouse') {
                    const dx = event.clientX - this.pending.x;
                    const dy = event.clientY - this.pending.y;
                    if (dx * dx + dy * dy > SLOP * SLOP) {
                        this.clearPending();
                    }
                }
                return;
            }
            const dx = event.clientX - this.pending.x;
            const dy = event.clientY - this.pending.y;
            if (dx * dx + dy * dy > SLOP * SLOP) {
                this.didMove = true;
            }
            if (event.cancelable) {
                event.preventDefault();
            }
            this.follow(event.clientX, event.clientY);
        },

        follow(x, y) {
            const tile = this.pending?.tile;
            if (!tile) {
                return;
            }
            tile.style.left = `${x - this.pending.offsetX}px`;
            tile.style.top = `${y - this.pending.offsetY}px`;
            const bin = this.binAt(x, y);
            this.clearBin();
            if (bin) {
                this.pending.overBin = true;
                bin.classList.add('is-bin-target');
                if (this.pending.placeholder?.parentNode) {
                    this.pending.placeholder.remove();
                }
                return;
            }
            this.pending.overBin = false;
            const grid = this.gridEl();
            const placeholder = this.pending.placeholder;
            if (!grid || !placeholder) {
                return;
            }
            if (this.pending.fromHidden && !this.pointerOverGrid(x, y)) {
                if (placeholder.parentNode) {
                    placeholder.remove();
                }
                return;
            }
            if (placeholder.parentNode !== grid) {
                grid.appendChild(placeholder);
            }
            this.place(x, y);
        },

        pointerOverGrid(x, y) {
            const rect = this.gridEl()?.getBoundingClientRect();
            if (!rect) {
                return false;
            }
            return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
        },

        binAt(x, y) {
            if (this.pending?.tile?.dataset.wpIntentHideable !== '1') {
                return null;
            }
            const hit = document.elementFromPoint(x, y);
            const bin = hit?.closest?.('[data-wp-intent-bin]');
            if (!bin || !this.gridEl()?.contains(bin)) {
                return null;
            }
            return bin;
        },

        clearBin() {
            this.gridEl()?.querySelectorAll('[data-wp-intent-bin].is-bin-target').forEach((el) => {
                el.classList.remove('is-bin-target');
            });
        },

        concealHiddenModal() {
            const modal = this.$el.querySelector('[data-wp-intent-hidden-modal]');
            if (modal) {
                modal.style.display = 'none';
            }
        },

        revealHiddenModal() {
            const modal = this.$el.querySelector('[data-wp-intent-hidden-modal]');
            if (modal) {
                modal.style.display = '';
            }
        },

        place(x, y) {
            const grid = this.gridEl();
            const placeholder = this.pending?.placeholder;
            if (!grid || !placeholder) {
                return;
            }
            const tiles = [...grid.querySelectorAll('[data-wp-intent-tile]')];
            let before = null;
            for (const other of tiles) {
                const rect = other.getBoundingClientRect();
                const sameRow = y >= rect.top - 8 && y <= rect.bottom + 8;
                const past = sameRow
                    ? x > rect.left + rect.width / 2
                    : y > rect.top + rect.height / 2;
                if (!past) {
                    before = other;
                    break;
                }
            }
            if (before) {
                if (placeholder !== before && placeholder.nextElementSibling !== before) {
                    grid.insertBefore(placeholder, before);
                }
                return;
            }
            if (placeholder.parentNode !== grid || grid.lastElementChild !== placeholder) {
                grid.appendChild(placeholder);
            }
        },

        orderKeys() {
            const grid = this.gridEl();
            const placeholder = this.pending?.placeholder;
            const key = this.pending?.tile?.dataset.wpIntentTile;
            if (!grid || !placeholder?.parentNode || !key) {
                return null;
            }
            const keys = [];
            for (const node of grid.children) {
                if (node === placeholder) {
                    keys.push(key);
                } else if (node.dataset?.wpIntentTile && node.dataset.wpIntentTile !== key) {
                    keys.push(node.dataset.wpIntentTile);
                }
            }
            return keys.length === 0 ? null : keys;
        },

        onPointerUp(event) {
            if (!this.pending || event.pointerId !== this.pending.id) {
                return;
            }
            const moved = this.didMove;
            const overBin = this.pending.overBin === true;
            const fromHidden = this.pending.fromHidden === true;
            const hideable = this.pending.tile?.dataset.wpIntentHideable === '1';
            const key = this.pending.tile?.dataset.wpIntentTile ?? '';
            const keys = moved && !overBin ? this.orderKeys() : null;
            this.clearPending();
            if (!moved) {
                this.revealHiddenModal();
                return;
            }
            this.suppressClick = true;
            if (overBin && hideable && key !== '') {
                if (fromHidden) {
                    this.$wire.closeHiddenTiles();
                } else {
                    this.$wire.hideIntentTile(key);
                }
            } else if (fromHidden && keys) {
                this.$wire.restoreIntentTile(key, keys);
            } else if (!fromHidden && keys) {
                this.$wire.reorderIntentTiles(keys);
            } else {
                this.revealHiddenModal();
            }
            setTimeout(() => {
                this.suppressClick = false;
            }, 400);
        },

        onClick(event) {
            if (!this.suppressClick) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
        },

        onContextMenu(event) {
            if (this.armed || this.didMove) {
                event.preventDefault();
            }
        },

        clearHold() {
            if (this.holdTimer !== null) {
                clearTimeout(this.holdTimer);
                this.holdTimer = null;
            }
        },

        clearPending() {
            this.clearHold();
            const tile = this.pending?.tile;
            const placeholder = this.pending?.placeholder;
            if (tile) {
                tile.classList.remove('is-dragging');
                tile.style.width = '';
                tile.style.height = '';
                tile.style.left = '';
                tile.style.top = '';
                if (this.pending?.id !== undefined && tile.hasPointerCapture?.(this.pending.id)) {
                    tile.releasePointerCapture(this.pending.id);
                }
            }
            if (placeholder?.parentNode && tile) {
                placeholder.replaceWith(tile);
            } else if (tile && this.pending?.home) {
                this.pending.home.insertBefore(tile, this.pending.homeNext);
                placeholder?.remove();
            } else {
                placeholder?.remove();
            }
            this.clearBin();
            this.gridEl()?.classList.remove('is-sorting');
            this.pending = null;
            this.armed = false;
            this.didMove = false;
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

/**
 * Beginscherm-snelkoppelingen: een tik opent, vasthouden en schuiven verplaatst.
 * De nieuwe volgorde gaat naar Livewire → Action.
 */

const HOLD_MS = 280;
const SLOP = 12;

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
                id: event.pointerId,
            };
            this.armed = false;
            this.didMove = false;
            this.holdTimer = setTimeout(() => this.arm(), HOLD_MS);
        },

        arm() {
            if (!this.pending) {
                return;
            }
            this.armed = true;
            this.$el.classList.add('is-sorting');
            try {
                this.pending.tile.setPointerCapture(this.pending.id);
            } catch {
                // De pointer is al los.
            }
        },

        onPointerMove(event) {
            if (!this.pending || event.pointerId !== this.pending.id) {
                return;
            }
            const dx = event.clientX - this.pending.x;
            const dy = event.clientY - this.pending.y;
            if (!this.armed) {
                if (dx * dx + dy * dy > SLOP * SLOP) {
                    this.clearPending();
                }
                return;
            }
            if (dx * dx + dy * dy <= SLOP * SLOP) {
                return;
            }
            this.didMove = true;
            if (event.cancelable) {
                event.preventDefault();
            }
            this.pending.tile.classList.add('is-dragging');
            this.place(event.clientX, event.clientY);
        },

        place(x, y) {
            const tile = this.pending?.tile;
            if (!tile) {
                return;
            }
            const previous = tile.style.pointerEvents;
            tile.style.pointerEvents = 'none';
            const hit = document.elementFromPoint(x, y);
            tile.style.pointerEvents = previous;
            const over = hit?.closest('[data-wp-intent-tile]');
            if (!over || over === tile || !this.$el.contains(over)) {
                return;
            }
            const rect = over.getBoundingClientRect();
            const sameRow = y >= rect.top && y <= rect.bottom;
            const after = sameRow ? x > rect.left + rect.width / 2 : y > rect.top + rect.height / 2;
            const reference = after ? over.nextElementSibling : over;
            if (reference === tile) {
                return;
            }
            this.$el.insertBefore(tile, reference);
        },

        onPointerUp(event) {
            if (!this.pending || event.pointerId !== this.pending.id) {
                return;
            }
            const moved = this.didMove;
            const keys = [...this.$el.querySelectorAll('[data-wp-intent-tile]')].map(
                (el) => el.dataset.wpIntentTile,
            );
            this.clearPending();
            if (!moved || keys.length === 0) {
                return;
            }
            this.suppressClick = true;
            this.$wire.reorderIntentTiles(keys);
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
            if (tile) {
                tile.classList.remove('is-dragging');
                tile.style.pointerEvents = '';
                if (this.pending?.id !== undefined && tile.hasPointerCapture?.(this.pending.id)) {
                    tile.releasePointerCapture(this.pending.id);
                }
            }
            this.$el.classList.remove('is-sorting');
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

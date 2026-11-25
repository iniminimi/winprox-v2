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

        arm() {
            if (!this.pending) {
                return;
            }
            this.armed = true;
            this.$el.classList.add('is-sorting');
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
            tile.before(placeholder);
            this.pending.placeholder = placeholder;
            this.pending.offsetX = this.pending.x - rect.left;
            this.pending.offsetY = this.pending.y - rect.top;
            tile.classList.add('is-dragging');
            tile.style.width = `${rect.width}px`;
            tile.style.height = `${rect.height}px`;
            document.body.appendChild(tile);
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
            this.place(x, y);
        },

        place(x, y) {
            const placeholder = this.pending?.placeholder;
            if (!placeholder) {
                return;
            }
            const tiles = [...this.$el.querySelectorAll('[data-wp-intent-tile]')];
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
                    this.$el.insertBefore(placeholder, before);
                }
                return;
            }
            const last = tiles[tiles.length - 1];
            if (last && last.nextElementSibling !== placeholder) {
                last.after(placeholder);
            }
        },

        onPointerUp(event) {
            if (!this.pending || event.pointerId !== this.pending.id) {
                return;
            }
            const moved = this.didMove;
            this.clearPending();
            if (!moved) {
                return;
            }
            const keys = [...this.$el.querySelectorAll('[data-wp-intent-tile]')].map(
                (el) => el.dataset.wpIntentTile,
            );
            if (keys.length === 0) {
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
            if (placeholder?.parentNode) {
                if (tile) {
                    placeholder.replaceWith(tile);
                } else {
                    placeholder.remove();
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

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
            if (!tile || tile.hasAttribute('data-wp-intent-bin') || !this.$el.contains(tile)) {
                return;
            }
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            const root = tile.closest('[data-wp-intent-root]');
            const grid = tile.closest('[data-wp-intent-grid]')
                ?? root?.querySelector('[data-wp-intent-grid]')
                ?? null;
            this.clearHold();
            this.pending = {
                tile,
                grid,
                root,
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
            const grid = this.pending?.grid;
            if (grid?.isConnected) {
                return grid;
            }

            return this.$el.querySelector('[data-wp-intent-grid]');
        },

        dragRoot() {
            const root = this.pending?.root;
            if (root?.isConnected) {
                return root;
            }

            return this.gridEl()?.closest('[data-wp-intent-root]') ?? this.$el;
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
                this.parkPlaceholder();
                return;
            }
            this.pending.overBin = false;
            if (this.hideOnly()) {
                this.parkPlaceholder();
                return;
            }
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

        tileIsHideable(tile) {
            return tile?.getAttribute('data-wp-intent-hideable') === '1';
        },

        hideOnly() {
            return this.dragRoot().dataset.wpTileMode === 'hide';
        },

        binAt(x, y) {
            const tile = this.pending?.tile;
            if (!this.tileIsHideable(tile)) {
                return null;
            }
            const bin = this.gridEl()?.querySelector('[data-wp-intent-bin]');
            if (!bin) {
                return null;
            }
            const under = document.elementsFromPoint(x, y);
            if (under.some((el) => el === bin || bin.contains(el))) {
                return bin;
            }
            const binRect = bin.getBoundingClientRect();
            const pad = 16;
            const pointerOnBin = x >= binRect.left - pad && x <= binRect.right + pad
                && y >= binRect.top - pad && y <= binRect.bottom + pad;
            if (pointerOnBin) {
                return bin;
            }
            const tileRect = tile.getBoundingClientRect();
            const centerX = tileRect.left + tileRect.width / 2;
            const centerY = tileRect.top + tileRect.height / 2;
            const centerOnBin = centerX >= binRect.left && centerX <= binRect.right
                && centerY >= binRect.top && centerY <= binRect.bottom;
            if (centerOnBin) {
                return bin;
            }
            const overlapW = Math.min(tileRect.right, binRect.right) - Math.max(tileRect.left, binRect.left);
            const overlapH = Math.min(tileRect.bottom, binRect.bottom) - Math.max(tileRect.top, binRect.top);
            if (overlapW <= 0 || overlapH <= 0 || binRect.width <= 0 || binRect.height <= 0) {
                return null;
            }
            const covered = (overlapW * overlapH) / (binRect.width * binRect.height);
            return covered >= 0.3 ? bin : null;
        },

        parkPlaceholder() {
            const placeholder = this.pending?.placeholder;
            const home = this.pending?.home;
            if (!placeholder || !home) {
                return;
            }
            const next = this.pending.homeNext;
            if (next?.parentNode === home) {
                if (placeholder.nextSibling !== next) {
                    home.insertBefore(placeholder, next);
                }
                return;
            }
            if (placeholder.parentNode !== home) {
                home.appendChild(placeholder);
            }
        },

        clearBin() {
            this.gridEl()?.querySelectorAll('[data-wp-intent-bin].is-bin-target').forEach((el) => {
                el.classList.remove('is-bin-target');
            });
        },

        concealHiddenModal() {
            const modal = this.dragRoot().querySelector('[data-wp-intent-hidden-modal]');
            if (modal) {
                modal.style.display = 'none';
            }
        },

        place(x, y) {
            const grid = this.gridEl();
            const placeholder = this.pending?.placeholder;
            if (!grid || !placeholder) {
                return;
            }
            const bin = grid.querySelector('[data-wp-intent-bin]');
            const tiles = [...grid.querySelectorAll('[data-wp-intent-tile]')]
                .filter((el) => !el.hasAttribute('data-wp-intent-bin'));
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
            if (!before && bin) {
                before = bin;
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
            if (!grid || placeholder?.parentNode !== grid || !key) {
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
            const fromHidden = this.pending.fromHidden === true;
            const hideable = this.tileIsHideable(this.pending.tile);
            const key = this.pending.tile?.dataset.wpIntentTile ?? '';
            const root = this.dragRoot();
            const onlyHide = this.hideOnly();
            const overBin = hideable && (
                this.pending.overBin === true || this.binAt(event.clientX, event.clientY) !== null
            );
            this.parkPlaceholder();
            const overGrid = this.pointerOverGrid(event.clientX, event.clientY);
            if (moved && !overBin && !onlyHide && (!fromHidden || overGrid)) {
                this.place(event.clientX, event.clientY);
            }
            const keys = moved && !overBin && !onlyHide ? this.orderKeys() : null;
            const hideMethod = root.dataset.wpHideMethod || 'hideIntentTile';
            const restoreMethod = root.dataset.wpRestoreMethod || 'restoreIntentTile';
            const reorderMethod = root.dataset.wpReorderMethod || 'reorderIntentTiles';
            const closeMethod = root.dataset.wpCloseMethod || 'closeHiddenTiles';
            this.clearPending();
            if (!moved) {
                this.revealModal(root);
                return;
            }
            this.suppressClick = true;
            if (overBin && hideable && key !== '') {
                if (fromHidden) {
                    this.$wire[closeMethod]();
                } else {
                    this.$wire[hideMethod](key);
                }
            } else if (fromHidden && onlyHide && overGrid) {
                this.$wire[restoreMethod](key);
            } else if (fromHidden && keys) {
                this.$wire[restoreMethod](key, keys);
            } else if (!fromHidden && keys) {
                this.$wire[reorderMethod](keys);
            } else {
                this.revealModal(root);
            }
            setTimeout(() => {
                this.suppressClick = false;
            }, 400);
        },

        revealModal(root) {
            const modal = root?.querySelector('[data-wp-intent-hidden-modal]');
            if (modal) {
                modal.style.display = '';
            }
        },

        onDragStart(event) {
            if (event.target.closest('[data-wp-intent-grid], .wp-kpi')) {
                event.preventDefault();
            }
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

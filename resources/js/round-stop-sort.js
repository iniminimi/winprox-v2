/**
 * Drag inspection-round stops within a location, or whole location blocks.
 * Persist via Livewire → Action. Arrow buttons stay for keyboard moves.
 */

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpRoundStopSort) {
        return;
    }
    window.Alpine.__wpRoundStopSort = true;
    window.Alpine.data('wpRoundStopSort', () => ({
        kind: null,
        from: null,
        over: null,
        locationKey: null,

        onPointerDown(event, index, locationKey) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            event.preventDefault();
            this.kind = 'unit';
            this.from = index;
            this.over = index;
            this.locationKey = String(locationKey);
            event.currentTarget.setPointerCapture(event.pointerId);
        },

        onLocationPointerDown(event, locationKey) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            event.preventDefault();
            this.kind = 'location';
            this.from = String(locationKey);
            this.over = String(locationKey);
            this.locationKey = String(locationKey);
            event.currentTarget.setPointerCapture(event.pointerId);
        },

        onPointerMove(event) {
            if (this.from === null) {
                return;
            }
            const el = document.elementFromPoint(event.clientX, event.clientY);
            if (this.kind === 'unit') {
                const item = el?.closest('[data-round-stop-index]');
                if (!item || item.dataset.roundStopLocation !== this.locationKey) {
                    return;
                }
                const index = Number.parseInt(item.dataset.roundStopIndex ?? '', 10);
                if (Number.isInteger(index)) {
                    this.over = index;
                }
                return;
            }
            const group = el?.closest('[data-round-stop-location]');
            if (!group) {
                return;
            }
            this.over = String(group.dataset.roundStopLocation ?? '');
        },

        onPointerUp(event) {
            if (event.type === 'pointercancel') {
                this.resetDrag();
                return;
            }
            const kind = this.kind;
            const from = this.from;
            const to = this.over;
            this.resetDrag();
            if (from === null || to === null || from === to) {
                return;
            }
            if (kind === 'unit') {
                this.$wire.reorderRoundStop(from, to);
                return;
            }
            if (kind === 'location') {
                this.$wire.reorderRoundLocation(Number(from), Number(to));
            }
        },

        resetDrag() {
            this.kind = null;
            this.from = null;
            this.over = null;
            this.locationKey = null;
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

/**
 * Drag inspection-round stops onto another row. Persist via Livewire → Action.
 * Arrow buttons stay for keyboard and one-step moves.
 */

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpRoundStopSort) {
        return;
    }
    window.Alpine.__wpRoundStopSort = true;
    window.Alpine.data('wpRoundStopSort', () => ({
        from: null,
        over: null,

        onPointerDown(event, index) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            event.preventDefault();
            this.from = index;
            this.over = index;
            event.currentTarget.setPointerCapture(event.pointerId);
        },

        onPointerMove(event) {
            if (this.from === null) {
                return;
            }
            const el = document.elementFromPoint(event.clientX, event.clientY);
            const item = el?.closest('[data-round-stop-index]');
            if (!item) {
                return;
            }
            const index = Number.parseInt(item.dataset.roundStopIndex ?? '', 10);
            if (Number.isInteger(index)) {
                this.over = index;
            }
        },

        onPointerUp(event) {
            if (event.type === 'pointercancel') {
                this.from = null;
                this.over = null;
                return;
            }
            const from = this.from;
            const to = this.over;
            this.from = null;
            this.over = null;
            if (from === null || to === null || from === to) {
                return;
            }
            this.$wire.reorderRoundStop(from, to);
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);

if (window.Alpine) {
    registerAlpineComponent();
}

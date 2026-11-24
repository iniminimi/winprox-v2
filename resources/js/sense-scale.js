document.addEventListener('alpine:init', () => {
    window.Alpine.data('wpSenseScale', (config = {}) => ({
        pointId: config.pointId,
        metric: config.metric,
        mode: config.mode || 'band',
        min: Number(config.min),
        max: Number(config.max),
        step: Number(config.step),
        low: Number(config.low),
        target: config.target === null || config.target === undefined ? null : Number(config.target),
        high: Number(config.high),
        colors: config.colors || {},
        decimal: config.decimal || ',',
        dragging: null,
        editing: null,
        draft: '',
        initial: null,

        init() {
            this.initial = { low: this.low, target: this.target, high: this.high };
        },

        percent(value) {
            if (this.max <= this.min) {
                return 0;
            }

            return ((Number(value) - this.min) / (this.max - this.min)) * 100;
        },

        trackStyle() {
            if (this.mode === 'ceiling') {
                const at = this.percent(this.high);
                const yellow = Math.max(0, at * 0.72);

                return `background: linear-gradient(90deg, ${this.colors.low} 0%, ${this.colors.yellow} ${yellow}%, ${this.colors.high} ${at}%, ${this.colors.high} 100%)`;
            }

            const low = this.percent(this.low);
            const target = this.percent(this.target);
            const high = this.percent(this.high);

            return `background: linear-gradient(90deg, ${this.colors.low} 0%, ${this.colors.low} ${low}%, ${this.colors.target} ${target}%, ${this.colors.high} ${high}%, ${this.colors.high} 100%)`;
        },

        label(value) {
            const rounded = this.snap(value);
            const text = String(rounded).replace('.', this.decimal);

            return text;
        },

        snap(value) {
            const steps = Math.round((Number(value) - this.min) / this.step);
            const snapped = this.min + (steps * this.step);
            const clamped = Math.min(this.max, Math.max(this.min, snapped));

            return Math.round(clamped * 1000) / 1000;
        },

        startDrag(which, event) {
            if (this.editing) {
                return;
            }

            this.dragging = which;
            event.currentTarget.setPointerCapture?.(event.pointerId);
            this.moveDrag(event);
        },

        moveDrag(event) {
            if (!this.dragging) {
                return;
            }

            const track = this.$refs.track;
            if (!track) {
                return;
            }

            const rect = track.getBoundingClientRect();
            const ratio = rect.width <= 0 ? 0 : (event.clientX - rect.left) / rect.width;
            let value = this.snap(this.min + (Math.min(1, Math.max(0, ratio)) * (this.max - this.min)));
            this.place(this.dragging, value);
        },

        async endDrag() {
            if (!this.dragging) {
                return;
            }

            this.dragging = null;
            await this.persist();
        },

        place(which, value) {
            if (this.mode === 'ceiling') {
                this.high = value;

                return;
            }

            if (which === 'low') {
                this.low = Math.min(value, this.snap(this.target - this.step));
            } else if (which === 'target') {
                const floor = this.snap(this.low + this.step);
                const ceil = this.snap(this.high - this.step);
                this.target = Math.min(ceil, Math.max(floor, value));
            } else {
                this.high = Math.max(value, this.snap(this.target + this.step));
            }
        },

        beginEdit(which) {
            this.editing = which;
            const current = which === 'target' ? this.target : (which === 'low' ? this.low : this.high);
            this.draft = this.label(current);
            const ref = which === 'low' ? 'editLow' : (which === 'target' ? 'editTarget' : 'editHigh');
            this.$nextTick(() => this.$refs[ref]?.focus());
        },

        async commitEdit() {
            if (!this.editing) {
                return;
            }

            const which = this.editing;
            const parsed = Number(String(this.draft).trim().replace(',', '.'));
            this.editing = null;
            if (!Number.isFinite(parsed)) {
                return;
            }

            this.place(which, this.snap(parsed));
            await this.persist();
        },

        async persist() {
            const ok = await this.$wire.saveBands(this.pointId, this.metric, this.low, this.target, this.high);
            if (!ok && this.initial) {
                this.low = this.initial.low;
                this.target = this.initial.target;
                this.high = this.initial.high;
            }
        },
    }));
});

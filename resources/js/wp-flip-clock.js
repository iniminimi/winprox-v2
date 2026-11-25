document.addEventListener('alpine:init', () => {
    window.Alpine.data('wpFlipClock', (name = '') => ({
        name,
        label: name,
        digits: [
            { value: '0', from: '0', flip: false },
            { value: '0', from: '0', flip: false },
            { value: '0', from: '0', flip: false },
            { value: '0', from: '0', flip: false },
        ],
        timer: null,
        init() {
            this.paint(false);
            const tick = () => {
                this.paint(true);
                this.timer = setTimeout(tick, 1000 - (Date.now() % 1000));
            };
            this.timer = setTimeout(tick, 1000 - (Date.now() % 1000));
        },
        destroy() {
            clearTimeout(this.timer);
        },
        paint(animate) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const next = (hours + minutes).split('');
            this.label = `${this.name} ${hours}:${minutes}`.trim();
            next.forEach((digit, index) => {
                if (digit === this.digits[index].value) {
                    return;
                }
                const from = this.digits[index].value;
                this.digits[index] = {
                    value: digit,
                    from: animate ? from : digit,
                    flip: animate,
                };
                if (animate) {
                    setTimeout(() => {
                        if (this.digits[index].value === digit) {
                            this.digits[index] = { value: digit, from: digit, flip: false };
                        }
                    }, 560);
                }
            });
        },
    }));
});

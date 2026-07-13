(function () {
    var targets = document.querySelectorAll('[data-wp-text-reveal]');

    if (targets.length === 0) {
        return;
    }

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function reveal(el) {
        if (el.classList.contains('is-revealed')) {
            return;
        }

        el.classList.add('is-revealed');
    }

    if (reducedMotion) {
        targets.forEach(reveal);

        return;
    }

    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (! entry.isIntersecting) {
                    return;
                }

                reveal(entry.target);
                observer.unobserve(entry.target);
            });
        },
        {
            root: null,
            rootMargin: '0px 0px -8% 0px',
            threshold: 0.15,
        },
    );

    targets.forEach(function (el) {
        var section = el.closest('section, header');

        if (section && section.classList.contains('wp-welcome-hero')) {
            requestAnimationFrame(function () {
                reveal(el);
            });

            return;
        }

        observer.observe(el);
    });
})();

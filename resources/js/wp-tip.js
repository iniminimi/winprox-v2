// Globale ballon: vervangt elke native `title`-tooltip door een gestylede
// .wp-tip (native tooltips kunnen niet gestyled worden — geen radius/shadow).
// Rijke tips (bv. rooster-minimumbezetting) komen via data-wp-staffing-tip.
(() => {
    const tip = document.createElement('div');
    tip.className = 'wp-tip';
    tip.hidden = true;
    document.body.appendChild(tip);

    let anchor = null;
    let stashedTitle = null;

    const hide = () => {
        if (stashedTitle) {
            const [el, text] = stashedTitle;
            if (el.isConnected) {
                el.title = text;
            }
            stashedTitle = null;
        }
        tip.hidden = true;
        anchor = null;
    };

    const stashTitle = (el) => {
        // Verwijder title zodat de native browser-tooltip nooit verschijnt;
        // restore bij hide houdt de tekst toegankelijk tussen hovers.
        if (el?.title) {
            const text = el.title;
            el.removeAttribute('title');
            stashedTitle = [el, text];
            return text;
        }
        return '';
    };

    const position = (el) => {
        const rect = el.getBoundingClientRect();
        const tipRect = tip.getBoundingClientRect();
        const above = rect.top > tipRect.height + 16;
        const center = Math.min(
            Math.max(rect.left + rect.width / 2, tipRect.width / 2 + 8),
            window.innerWidth - tipRect.width / 2 - 8,
        );
        tip.classList.toggle('wp-tip--below', !above);
        tip.style.left = `${center}px`;
        tip.style.top = `${above ? rect.top : rect.bottom}px`;
    };

    const renderRich = (el, data) => {
        const nodes = [];
        if (data.title) {
            const titleEl = document.createElement('div');
            titleEl.className = 'wp-tip__title';
            titleEl.textContent = data.title;
            nodes.push(titleEl);
        }
        data.groups.forEach((group) => {
            const loc = document.createElement('div');
            loc.className = 'wp-tip__location';
            loc.textContent = group.location || '';
            const ul = document.createElement('ul');
            ul.className = 'wp-tip__items';
            (group.lines ?? []).forEach((line) => {
                const li = document.createElement('li');
                li.textContent = line;
                ul.appendChild(li);
            });
            nodes.push(loc, ul);
        });
        // Extra title op hetzelfde element (bv. feestdag-naam op de header)
        // wordt als muted voettekst toegevoegd.
        const extra = stashTitle(el);
        if (extra) {
            const foot = document.createElement('div');
            foot.className = 'wp-tip__foot';
            foot.textContent = extra;
            nodes.push(foot);
        }
        tip.replaceChildren(...nodes);
    };

    const renderText = (el) => {
        const text = stashTitle(el);
        if (!text) {
            return false;
        }
        const nodes = text.split('\n').map((line) => {
            const div = document.createElement('div');
            div.className = 'wp-tip__line';
            div.textContent = line;
            return div;
        });
        tip.replaceChildren(...nodes);
        return true;
    };

    const show = (el) => {
        let rendered = false;
        try {
            const rich = el.dataset?.wpStaffingTip ? JSON.parse(el.dataset.wpStaffingTip) : null;
            if (rich && Array.isArray(rich.groups) && rich.groups.length > 0) {
                renderRich(el, rich);
                rendered = true;
            }
        } catch {
            rendered = false;
        }
        if (!rendered && !renderText(el)) {
            return;
        }
        tip.hidden = false;
        anchor = el;
        position(el);
    };

    document.addEventListener('mouseover', (event) => {
        if (tip.contains(event.target)) {
            return;
        }
        const el = event.target?.closest?.('[data-wp-staffing-tip], [title]');
        if (!el || el.tagName === 'OPTION' || el.tagName === 'IFRAME') {
            return;
        }
        if (el === anchor) {
            return;
        }
        hide();
        show(el);
    });

    document.addEventListener('mouseout', (event) => {
        if (anchor && !anchor.contains(event.relatedTarget)) {
            hide();
        }
    });
    document.addEventListener('pointerdown', hide, true);
    document.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
})();

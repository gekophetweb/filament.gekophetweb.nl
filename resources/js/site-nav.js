/**
 * Shows the header links inline when the whole row fits, otherwise behind the
 * Menu button as a popover. Measures the real row instead of using a breakpoint,
 * so longer names or larger text move the switch point on their own.
 *
 * @param {HTMLElement} header The .site-header element.
 */
export function initSiteNav(header) {
    const row = header.querySelector('.header-inner');
    const menu = header.querySelector('.site-menu');
    if (!row || !menu) {
        return;
    }

    let lastWidth = 0;

    const update = () => {
        const width = header.clientWidth;
        const isWide = header.classList.contains('is-wide');

        // While collapsed only remeasure when the header grew, so shrinking does not thrash.
        if (!isWide && width <= lastWidth) {
            lastWidth = width;
            return;
        }
        lastWidth = width;

        const wasOpen = menu.matches(':popover-open');
        header.classList.add('is-measuring', 'is-wide');
        const fits = row.scrollWidth <= row.clientWidth;
        header.classList.toggle('is-wide', fits);

        if (fits) {
            menu.removeAttribute('popover');
        } else if (!menu.hasAttribute('popover')) {
            menu.setAttribute('popover', 'auto');
        } else if (wasOpen && !menu.matches(':popover-open')) {
            menu.showPopover();
        }

        requestAnimationFrame(() => header.classList.remove('is-measuring'));
    };

    const observer = new ResizeObserver(update);
    observer.observe(header);
    observer.observe(menu);
}

(() => {
    'use strict';

    // Mintlify's custom-script entry point runs once. Its navigation event runs
    // after the next page renders; no polling or document-wide observer is needed.
    const controllerKey = Symbol.for('flex-fields.docs.footer');
    window[controllerKey]?.dispose();

    const createElement = (tag, className, text) => {
        const element = document.createElement(tag);
        element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    const createLink = (label, href, className = '') => {
        const link = createElement('a', className, label);
        link.setAttribute('href', href);
        if (href.startsWith('https://')) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        }
        return link;
    };

    const createIntroduction = () => {
        const section = createElement('section', 'fff-docs-footer-intro');
        section.setAttribute('aria-labelledby', 'fff-docs-footer-title');
        const copy = createElement('div', 'fff-docs-footer-intro-copy');
        const heading = createElement('h2', 'fff-docs-footer-title', 'Make your next form a better one.');
        heading.id = 'fff-docs-footer-title';
        copy.append(
            createElement('p', 'fff-docs-footer-eyebrow', 'Build with FLEX'),
            heading,
            createElement('p', 'fff-docs-footer-lead', 'Find your components. Shape the experience. Keep your workflow.'),
        );
        const actions = createElement('div', 'fff-docs-footer-actions');
        actions.append(
            createLink('Explore components →', '/docs/component-library', 'fff-docs-footer-button fff-docs-footer-button-primary'),
            createLink('Try live demo ↗', 'https://demo.bjanczak.com/', 'fff-docs-footer-button fff-docs-footer-button-secondary'),
        );
        section.append(copy, actions);
        return section;
    };

    const createBrandDescription = () => {
        const content = createElement('div', 'fff-docs-footer-brand-description');
        content.append(createElement('p', 'fff-docs-footer-brand-copy', 'Thoughtful controls. One connected toolkit.'));
        const badges = createElement('div', 'fff-docs-footer-badges');
        for (const label of ['Filament 5', 'Laravel', 'PHP 8.3+']) {
            badges.append(createElement('span', 'fff-docs-footer-badge', label));
        }
        content.append(badges);
        return content;
    };

    const createLegal = () => {
        const legal = createElement('div', 'fff-docs-footer-legal');
        legal.append(createElement('p', '', `© ${new Date().getFullYear()} Bartłomiej Janczak`));
        const links = createElement('div', 'fff-docs-footer-legal-links');
        links.append(
            createLink('License terms', '/docs/license-terms'),
            createLink('Support', '/docs/troubleshooting'),
        );
        legal.append(links);
        return legal;
    };

    const enhanceFooter = () => {
        const footer = document.querySelector('footer#footer.advanced-footer');
        if (!footer || footer.querySelector('.fff-docs-footer-intro')) return;

        // Keep native links, logo, attribution and theme controls. Validate the
        // mounted layout before adding our own semantic regions to its shell.
        const shell = footer.firstElementChild;
        const directory = shell?.firstElementChild;
        const brand = directory?.firstElementChild;
        const links = directory?.lastElementChild;
        if (!shell || !directory || !brand || !links || brand === links || !brand.querySelector('img.nav-logo') || links.children.length === 0) return;

        footer.classList.add('fff-docs-footer');
        shell.classList.add('fff-docs-footer-shell');
        directory.classList.add('fff-docs-footer-directory');
        brand.classList.add('fff-docs-footer-brand');
        links.classList.add('fff-docs-footer-links');
        links.setAttribute('role', 'navigation');
        links.setAttribute('aria-label', 'Documentation resources');
        for (const column of links.children) column.classList.add('fff-docs-footer-column');

        brand.insertBefore(createBrandDescription(), brand.lastElementChild);
        shell.insertBefore(createIntroduction(), directory);
        directory.append(brand);
        directory.after(createLegal());
    };

    window.addEventListener('mintlify:navigate', enhanceFooter);
    window.addEventListener('pageshow', enhanceFooter);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceFooter, { once: true });
    } else {
        enhanceFooter();
    }

    window[controllerKey] = {
        dispose() {
            window.removeEventListener('mintlify:navigate', enhanceFooter);
            window.removeEventListener('pageshow', enhanceFooter);
            document.removeEventListener('DOMContentLoaded', enhanceFooter);
        },
    };
})();

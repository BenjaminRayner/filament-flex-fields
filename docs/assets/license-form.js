(() => {
    'use strict';

    const controllerKey = Symbol.for('flex-fields.docs.license-form');
    window[controllerKey]?.dispose();

    let pendingScript;
    const initialize = () => {
        if (!document.querySelector('iframe.fff-docs-license-form')) return;
        if (window.Tally?.loadEmbeds) {
            window.Tally.loadEmbeds();
            return;
        }
        if (pendingScript) return;

        // The native iframe remains usable even if the optional resizing script
        // is unavailable. No API credentials or contact data enter this script.
        pendingScript = document.createElement('script');
        pendingScript.src = 'https://tally.so/widgets/embed.js';
        pendingScript.async = true;
        pendingScript.addEventListener('load', () => {
            pendingScript = undefined;
            window.Tally?.loadEmbeds();
        }, { once: true });
        pendingScript.addEventListener('error', () => {
            pendingScript.remove();
            pendingScript = undefined;
        }, { once: true });
        document.head.append(pendingScript);
    };

    window.addEventListener('mintlify:navigate', initialize);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }

    window[controllerKey] = {
        dispose() {
            window.removeEventListener('mintlify:navigate', initialize);
            document.removeEventListener('DOMContentLoaded', initialize);
        },
    };
})();

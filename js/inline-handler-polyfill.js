// inline-handler-polyfill.js
// Convert legacy inline handlers into safe event listeners at runtime.
// NOTE: This polyfill intentionally only supports simple function calls like
// `fnName()` or `fnName('arg', 1)` to avoid executing arbitrary code from attributes.
(function(){
    'use strict';

    function safeParseArgs(argsRaw) {
        if (!argsRaw) return [];
        // Try JSON.parse first (expects double-quoted strings)
        try { return JSON.parse('[' + argsRaw + ']'); } catch (e) { /* ignore parse error */ }
        // Convert simple single-quoted strings to double-quoted ones and try again
        try {
            const converted = argsRaw.replace(/'([^'\\]*(?:\\.[^'\\]*)*)'/g, function(_, s){ return '"' + s.replace(/"/g, '\\"') + '"'; });
            return JSON.parse('[' + converted + ']');
        } catch (e) { /* ignore conversion parse error */ }
        // Fallback: return the whole expression as a single trimmed string
        return [ argsRaw.replace(/^\s*['"]?|['"]?\s*$/g, '') ];
    }

    function normalize() {
        try {
            const nodes = Array.from(document.querySelectorAll('[onclick],[data-onclick]'));
            nodes.forEach(el => {
                try {
                    const raw = el.getAttribute('data-onclick') || el.getAttribute('onclick');
                    if (!raw) return;
                    // Remove attributes to avoid duplicate execution
                    el.removeAttribute('onclick');
                    el.removeAttribute('data-onclick');

                    // Only allow simple function calls: name and optional args in parentheses, optionally with return
                    const m = raw.trim().match(/^(?:return\s+)?([A-Za-z_$][0-9A-Za-z_$]*)\s*(?:\((.*)\))?\s*;?\s*$/);
                    if (!m) {
                        console.warn('inline-handler skipped unsafe code:', raw);
                        return;
                    }
                    const fnName = m[1]; const argsRaw = m[2] || '';
                    el.addEventListener('click', function(event){
                        try {
                            const fn = window[fnName];
                            if (typeof fn !== 'function') {
                                console.warn('inline-handler function not found:', fnName);
                                return;
                            }
                            const args = safeParseArgs(argsRaw);
                            const res = fn.apply(el, args);
                            if (res === false) {
                                try { event && event.preventDefault && event.preventDefault(); } catch(e){ /* ignore */ }
                                try { event && event.stopPropagation && event.stopPropagation(); } catch(e){ /* ignore */ }
                            }
                        } catch (e) { console.warn('inline-handler exec failed', e); }
                    });
                } catch(e){ console.warn('inline-handler conversion failed', e); }
            });
        } catch(e){ console.warn('inline-handler normalize failed', e); }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', normalize); else normalize();
})();

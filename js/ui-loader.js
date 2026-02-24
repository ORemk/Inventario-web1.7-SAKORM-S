// js/ui-loader.js - compatibility loader to initialize UIManager when present
(function(){
    if (window.UIManager || window.UI) return;
    function installFallback(){
        window.UI = window.UI || {};
        window.UI.toast = window.UI.toast || function(msg, type, duration){ try { if (document && document.body) { const el = document.createElement('div'); el.className = 'app-toast fallback ' + (type||'info'); el.style.position = 'fixed'; el.style.right = '12px'; el.style.bottom = '12px'; el.style.background = '#222'; el.style.color = '#fff'; el.style.padding = '8px 12px'; el.style.borderRadius = '6px'; el.style.zIndex = '99999'; el.textContent = String(msg); document.body.appendChild(el); setTimeout(()=> el.remove(), duration || 4000); } else { console.warn('Toast:', msg); } } catch(e){ safeWarn('ui-loader.toast', e); } };
        window.UI.showDialog = window.UI.showDialog || function(opts){ try { const m = (opts && (opts.title || '') ) + '\n' + (opts && (opts.message || opts.html || '') || ''); if (window.UI && window.UI.toast) window.UI.toast(m, opts && opts.type ? opts.type : 'info', opts && opts.autoClose ? opts.autoClose : 6000); else console.info(m); } catch(e){ safeWarn('ui-loader.showDialog', e); } return { close: function(){} }; };
        window.UI._shimUsed = true;
    }
    // Install minimal UI shim immediately so main.js can call UI functions synchronously
    installFallback();
    var s = document.createElement('script');
    (function(){
        function resolveBase(){
            try{
                const be = document.querySelector('base');
                if (be && be.href) return be.href.replace(/\/$/, '');
            } catch(e){ safeWarn('ui-loader.resolveBase.baseQuery', e); }
            try {
                const p = location.pathname || '/';
                const parts = p.split('/').filter(Boolean);
                if (parts.length && parts[parts.length-1].includes('.')) parts.pop();
                return location.protocol + '//' + location.host + '/' + parts.join('/');
            } catch(e) { return location.protocol + '//' + location.host; }
        }
        const base = resolveBase();
        const prefix = base;
        s.src = (prefix.replace(/\/$/, '') + '/js/src/uiManager.js?t=' + Date.now());
    })();
    // Force immediate execution order
    s.async = false; s.defer = false;
    s.onload = function(){
        try {
            if (window.UIManager && typeof window.UIManager.init === 'function') window.UIManager.init();
            if (window.UIManager && !window.UI) window.UI = window.UIManager;
            console.info('ui-loader: js/src/uiManager.js loaded');
        } catch(e) { console.warn('ui-loader onload error', e); }
    };
    s.onerror = function(){
        console.warn('ui-loader: failed to load js/src/uiManager.js; falling back to legacy js/ui.js');
        var s2 = document.createElement('script');
        try {
            const be = document.querySelector('base');
            const baseHref = (be && be.href) ? be.href : (location.protocol + '//' + location.host + '/');
                s2.src = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('js/ui.js') + '?t=' + Date.now() : new URL('js/ui.js', baseHref).toString() + '?t=' + Date.now();
        } catch(e) {
            var fallbackBase = (document && document.baseURI) ? document.baseURI : (location.protocol + '//' + location.host + '/');
            s2.src = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('js/ui.js') + '?t=' + Date.now() : new URL('js/ui.js', fallbackBase).toString() + '?t=' + Date.now();
        }
        s2.async = false; s2.defer = false;
        s2.onload = function(){ console.info('ui-loader: legacy js/ui.js loaded'); };
        s2.onerror = function(){ console.error('ui-loader: failed to load legacy js/ui.js'); };
        document.head.appendChild(s2);
    };
    document.head.appendChild(s);
})();

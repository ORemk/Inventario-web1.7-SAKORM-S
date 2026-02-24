// js/helpers.js - compatibility loader (delegates to js/src/helpers.js)
(function(){
    if (window.Helpers && typeof window.Helpers.formatCurrency === 'function') return; // already available
    // Try to load modularized helpers if not present
    function ensure() {
        if (window.Helpers && typeof window.Helpers.formatCurrency === 'function') return;
        // Provide minimal helpers synchronously so other scripts can use them immediately
        installFallback();
        var s = document.createElement('script');
        // Resolve base path robustly (use <base> if present)
        (function(){
            function resolveBase(){
                try{ const be = document.querySelector('base'); if (be && be.getAttribute('href')) return be.getAttribute('href').replace(/\/$/, ''); }catch(e){ safeWarn('helpers.resolveBase.baseQuery', e); }
                const p = location.pathname || '/';
                const parts = p.split('/').filter(Boolean);
                if (parts.length && parts[parts.length-1].includes('.')) parts.pop();
                return '/' + parts.join('/');
            }
            const base = resolveBase();
            const prefix = (base.startsWith('http') ? base : (location.protocol + '//' + location.host + base));
            s.src = prefix.replace(/\/$/, '') + '/js/src/helpers.js?t=' + Date.now();
        })();
        s.async = false; s.defer = false;
        s.onload = function(){ console.info('helpers: loaded js/src/helpers.js'); };
        s.onerror = function(){ console.warn('helpers: failed to load js/src/helpers.js, keeping fallback'); };
        document.head.appendChild(s);
    }

    function installFallback(){
        window.Helpers = window.Helpers || {};
        window.Helpers.formatCurrency = window.Helpers.formatCurrency || function(amount){ try { return Number(amount).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' }); } catch(e){ return amount; } };
    }

    ensure();
})();

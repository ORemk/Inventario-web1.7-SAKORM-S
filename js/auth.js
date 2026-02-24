// js/auth.js - compatibility loader for class-based auth manager
(function(){
    if (window.Auth && typeof window.Auth.isLoggedIn === 'function') return;
    function installFallback(){ window.Auth = window.Auth || {}; window.Auth.isLoggedIn = window.Auth.isLoggedIn || function(){ return false; }; window.Auth.getUser = window.Auth.getUser || function(){ return null; }; }
    // Provide fallback immediately so Auth checks are synchronous
    installFallback();
    var s = document.createElement('script');
    // Resolve base path robustly (prefer <base> tag if present)
    (function(){
        function resolveBase(){
            try{ const be = document.querySelector('base'); if (be && be.getAttribute('href')) return be.getAttribute('href').replace(/\/$/, ''); }catch(e){ safeWarn('auth.resolveBase.baseQuery', e); }
            const p = location.pathname || '/';
            const parts = p.split('/').filter(Boolean);
            if (parts.length && parts[parts.length-1].includes('.')) parts.pop();
            return '/' + parts.join('/');
        }
        const base = resolveBase();
        const prefix = (base.startsWith('http') ? base : (location.protocol + '//' + location.host + base));
        s.src = prefix.replace(/\/$/, '') + '/js/src/authManager.js?t=' + Date.now();
    })();
    s.async = false; s.defer = false;
    s.onload = function(){ console.info('auth: loaded js/src/authManager.js'); };
    s.onerror = function(){ console.warn('auth: failed to load js/src/authManager.js, keeping fallback'); };
    document.head.appendChild(s);
})();

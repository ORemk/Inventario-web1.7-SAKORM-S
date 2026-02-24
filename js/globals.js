// js/globals.js - compatibility loader for class-based globals
(function(){
	if (window.GLOBALS && typeof window.GLOBALS.getVersion === 'function') return;
	function installFallback(){ window.GLOBALS = window.GLOBALS || {}; window.GLOBALS.appVersion = window.GLOBALS.appVersion || '20260129'; }
	// Ensure minimal GLOBALS is available synchronously for other scripts
	installFallback();

	// Compute a reliable application base path and expose helpers
	(function(){
		try{
			var baseEl = document.querySelector('base');
			var baseHref = '/';
			if (baseEl && baseEl.href) {
				try {
					baseHref = new URL(baseEl.href).pathname;
				} catch(e) {
					baseHref = '/';
				}
			}
			// Normalize to ensure leading and trailing slash
			if (!baseHref.startsWith('/')) baseHref = '/' + baseHref;
			if (!baseHref.endsWith('/')) baseHref = baseHref + '/';
			window.APP_BASE = baseHref;
			window.buildAppUrl = function(path){
				if (!path) return window.APP_BASE;
				if (path.charAt(0) === '/') path = path.substring(1);
				return window.APP_BASE + path;
			};
			console.info('globals: APP_BASE set to', window.APP_BASE);
		}catch(e){ try{ safeWarn('globals.initBase', e); }catch(_){/* noop */} }
	})();
	var s = document.createElement('script');
	(function(){
		function resolveBase(){
			try{ const be = document.querySelector('base'); if (be && be.getAttribute('href')) return be.getAttribute('href').replace(/\/$/, ''); }catch(e){ safeWarn('globals.resolveBase.baseQuery', e); }
			const p = location.pathname || '/';
			const parts = p.split('/').filter(Boolean);
			if (parts.length && parts[parts.length-1].includes('.')) parts.pop();
			return '/' + parts.join('/');
		}
		const base = resolveBase();
		const prefix = (base.startsWith('http') ? base : (location.protocol + '//' + location.host + base));
		s.src = prefix.replace(/\/$/, '') + '/js/src/globals.js?t=' + Date.now();
	})();
	// Force immediate execution order
	s.async = false; s.defer = false;
	s.onload = function(){ console.info('globals: loaded js/src/globals.js'); };
	s.onerror = function(){ console.warn('globals: failed to load js/src/globals.js'); };
	document.head.appendChild(s);
})();

// js/src/globals.js - class-based globals
(function(){
    class Globals {
        constructor(){
            this.appVersion = (window.GLOBALS && window.GLOBALS.appVersion) || '20260129';
            window.GLOBALS = window.GLOBALS || this;
        }

        getVersion(){ return this.appVersion; }
        setVersion(v){ this.appVersion = v; try { localStorage.setItem('app_version', v); } catch(e){ safeWarn('globals.setVersion', e); } }
    }

    if (!window.GlobalsManager) window.GlobalsManager = Globals;
    if (!window.GLOBALS || window.GLOBALS.getVersion === undefined) {
        try { window.GLOBALS = new Globals(); } catch(e) { console.warn('Globals init failed', e); }
    }
})();

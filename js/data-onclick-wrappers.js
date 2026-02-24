// Safe wrappers for complex data-onclick expressions
(function(){
    'use strict';

    window.showDatePicker = function(id) {
        try {
            const el = document.getElementById(id);
            if (!el) return;
            if (typeof el.showPicker === 'function') el.showPicker();
            else el.click();
        } catch(e){ console.warn('showDatePicker failed', e); }
    };

    window.closeWelcome = function() {
        try {
            const btn = document.getElementById('close-welcome');
            if (btn && typeof btn.__closeWelcome === 'function') return btn.__closeWelcome();
            // fallback: hide welcome element if present
            // support both legacy id 'welcome' and the current 'welcome-dialog'
            const welcome = document.getElementById('welcome-dialog') || document.getElementById('welcome');
            if (welcome) {
                try { welcome.style.display = 'none'; } catch(e){ console.warn('hide welcome failed', e); }
            }
        } catch(e){ console.warn('closeWelcome failed', e); }
    };

    window.navigateToUrl = function(url) {
        try { window.location.href = String(url); } catch(e) { console.warn('navigateToUrl failed', e); }
    };

})();

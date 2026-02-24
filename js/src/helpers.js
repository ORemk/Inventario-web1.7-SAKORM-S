// js/src/helpers.js - modularized helpers
(function(){
    class Helpers {
        static formatCurrency(amount) {
            try { return Number(amount).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' }); } catch(e){ return amount; }
        }

        // Add common helpers here in future (escapeHtml, parseDate, etc.)
        static escapeHtml(unsafe) {
            return String(unsafe).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
        }
    }

    // Expose as window.Helpers for backward compatibility
    window.Helpers = window.Helpers || {};
    // Copy static methods to window.Helpers as functions
    Object.getOwnPropertyNames(Helpers).forEach(name => {
        if (name === 'length' || name === 'prototype') return;
        if (typeof Helpers[name] === 'function') window.Helpers[name] = Helpers[name];
    });
})();

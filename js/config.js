/* js/config.js - Configuración global del frontend
 * Copia mínima que define `window.CONFIG` y `buildApiUrl`.
 */
(function(){
    window.CONFIG = window.CONFIG || {};
    // Por defecto en desarrollo la API está en la raíz del proyecto
    window.CONFIG.env = window.CONFIG.env || 'development';
    window.CONFIG.apiBase = window.CONFIG.apiBase || ''; // '' => raíz

    window.buildApiUrl = function(path) {
        // Si path inicia con http(s) o con / lo convierto a URL absoluta si hace falta
        if (!path) return window.location.origin + '/';
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        if (path.startsWith('/')) return window.location.origin + path;
        // Normal path like 'productos.php' or 'api/endpoint'
        const base = window.CONFIG.apiBase || '';
        const prefix = base && !base.startsWith('/') ? '/' + base : base;
        return window.location.origin + prefix + (base && !base.endsWith('/') && !path.startsWith('/') ? '/' : '') + path;
    };
})();

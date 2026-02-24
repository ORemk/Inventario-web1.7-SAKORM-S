/* js/src/apiClient.js - modular API client
   Exposes `API.createAPI(entity)` and convenience instances on `window.API*` for compatibility.
*/
(function(){
    'use strict';

    function parseJsonSafe(res) {
        return res.text().then(text => {
            try { return JSON.parse(text || '{}'); }
            catch (e) {
                try {
                    const m = text.match(/(\{[\s\S]*\}|\[[\s\S]*\])/);
                    if (m) return JSON.parse(m[0]);
                } catch (e2) { /* fallthrough */ }
                return { success: false, error: 'Invalid JSON', raw: text };
            }
        });
    }

    function handleResponse(res) {
        if (res.ok) return parseJsonSafe(res);
        return parseJsonSafe(res).then(json => ({ success: false, status: res.status, error: json?.error || res.statusText, raw: json?.raw }));
    }

    function createAPI(entity) {
        const base = (window.CONFIG && typeof window.CONFIG.apiBase === 'string' && window.CONFIG.apiBase !== '')
            ? (window.CONFIG.apiBase.replace(/\/$/, '') + '/')
            : 'api/';
        const endpoint = function(id){
            if (id === undefined || id === null) return (base + entity + '.php').replace(/^\//,'');
            return (base + entity + '.php?id=' + encodeURIComponent(id)).replace(/^\//,'');
        };

        return {
            async getAll() {
                try { const res = await fetch(endpoint()); return await handleResponse(res); } catch (e) { return { success: false, error: e.message }; }
            },
            async search(q) {
                try { const url = endpoint() + '?q=' + encodeURIComponent(q || ''); const res = await fetch(url); return await handleResponse(res); } catch (e) { return { success: false, error: e.message }; }
            },
            async get(id) {
                try { const res = await fetch(endpoint(id)); return await handleResponse(res); } catch (e) { return { success: false, error: e.message }; }
            },
            async create(data) {
                try { const res = await fetch(endpoint(), { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) }); return await handleResponse(res); } catch (e) { return { success: false, error: e.message }; }
            },
            async update(id, data) {
                try { const payload = Object.assign({}, data || {}); if (typeof payload.id === 'undefined' || payload.id === null) payload.id = id; const res = await fetch(endpoint(id), { method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) }); return await handleResponse(res); } catch (e) { return { success: false, error: e.message }; }
            },
            async delete(id) {
                try { const res = await fetch(endpoint(id), { method: 'DELETE', headers: {'Content-Type':'application/json'} }); return await handleResponse(res); } catch (e) { return { success: false, error: e.message }; }
            }
        }
    }

    // Expose
    window.API = window.API || {};
    window.API.createAPI = createAPI;
    try { window.API.__isRealClient = true; } catch(e){ safeWarn('apiClient.markRealClient', e); }
    window.ProductosAPI = window.ProductosAPI || createAPI('productos');
    window.CategoriasAPI = window.CategoriasAPI || createAPI('categorias');
    window.ClientesAPI = window.ClientesAPI || createAPI('clientes');
    window.ProveedoresAPI = window.ProveedoresAPI || createAPI('proveedores');
    window.VentasAPI = window.VentasAPI || createAPI('ventas');
    window.SalidasAPI = window.SalidasAPI || createAPI('salidas');
})();

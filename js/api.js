/* js/api.js - Implementación mínima de las APIs usadas por main.js
 * Proporciona `createAPI(entity)` y objetos: ProductosAPI, CategoriasAPI, ClientesAPI, ProveedoresAPI, VentasAPI, SalidasAPI
 */
(function(){
    'use strict';
    // Compatibility loader: dynamically load modularized API client
    (function(){
        if (window.API && typeof window.API.createAPI === 'function') return;
        function installFallback(){
            // Minimal fallback implementation (also executed immediately to guarantee APIs exist synchronously)
            function parseJsonSafe(res) { return res.text().then(text=>{ try{return JSON.parse(text||'{}');}catch(e){ try{ const m = text.match(/(\{[\s\S]*\}|\[[\s\S]*\])/); if (m) return JSON.parse(m[0]); }catch(e2){ safeWarn('api.parseJsonSafe.e2', e2); } return { success:false, error:'Invalid JSON', raw:text }; } }); }
            function handleResponse(res){ if (res.ok) return parseJsonSafe(res); return parseJsonSafe(res).then(json=>({ success:false, status:res.status, error: json?.error || res.statusText, raw: json?.raw })); }
            function createAPI(entity){ const base = (window.CONFIG && typeof window.CONFIG.apiBase === 'string' && window.CONFIG.apiBase !== '') ? (window.CONFIG.apiBase.replace(/\/$/,'') + '/') : 'api/'; const endpoint = function(id){ if (id === undefined || id === null) return (base + entity + '.php').replace(/^\//,''); return (base + entity + '.php?id=' + encodeURIComponent(id)).replace(/^\//,''); };
                return { async getAll(){ try{ const res = await fetch(endpoint()); return await handleResponse(res);}catch(e){return { success:false, error:e.message}} }, async get(id){ try{ const res = await fetch(endpoint(id)); return await handleResponse(res);}catch(e){return { success:false, error:e.message}} }, async create(data){ try{ const res = await fetch(endpoint(), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) }); return await handleResponse(res);}catch(e){return { success:false, error:e.message}} }, async update(id,data){ try{ const payload=Object.assign({},data||{}); if (typeof payload.id === 'undefined' || payload.id === null) payload.id = id; const res = await fetch(endpoint(id), { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)}); return await handleResponse(res);}catch(e){return { success:false, error:e.message}} }, async delete(id){ try{ const res = await fetch(endpoint(id), { method:'DELETE', headers:{'Content-Type':'application/json'} }); return await handleResponse(res);}catch(e){return { success:false, error:e.message}} } } }
            window.API = window.API || { createAPI };
            window.ProductosAPI = window.ProductosAPI || createAPI('productos');
            window.CategoriasAPI = window.CategoriasAPI || createAPI('categorias');
            window.ClientesAPI = window.ClientesAPI || createAPI('clientes');
            window.ProveedoresAPI = window.ProveedoresAPI || createAPI('proveedores');
            window.VentasAPI = window.VentasAPI || createAPI('ventas');
            window.SalidasAPI = window.SalidasAPI || createAPI('salidas');
        }

        // Execute fallback now so the basic API objects are available synchronously
        installFallback();

        function load() {
            var s = document.createElement('script');
            (function(){
                function resolveBase(){
                    try{ const be = document.querySelector('base'); if (be && be.getAttribute('href')) return be.getAttribute('href').replace(/\/$/, ''); }catch(e){ safeWarn('api.resolveBase.baseQuery', e); }
                    const p = location.pathname || '/';
                    const parts = p.split('/').filter(Boolean);
                    if (parts.length && parts[parts.length-1].includes('.')) parts.pop();
                    return '/' + parts.join('/');
                }
                const base = resolveBase();
                const prefix = (base.startsWith('http') ? base : (location.protocol + '//' + location.host + base));
                s.src = prefix.replace(/\/$/, '') + '/js/src/apiClient.js?t=' + Date.now();
            })();
            // Force synchronous execution order relative to existing scripts by disabling async/defer
            s.async = false; s.defer = false;
            s.onload = function(){ console.info('api: loaded js/src/apiClient.js'); };
            s.onerror = function(){ console.warn('api: failed to load js/src/apiClient.js'); /* installFallback called already */ };
            document.head.appendChild(s);
        }
        load();
    })();

})();

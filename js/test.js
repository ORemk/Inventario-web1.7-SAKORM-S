// test.js - debug/development hooks and sanity checks for globals
(function(){
    'use strict';
    window.TestHooks = window.TestHooks || {};

    window.TestHooks.checkAPIGlobals = function(){
        const apis = ['ProductosAPI','CategoriasAPI','ClientesAPI','ProveedoresAPI','VentasAPI','SalidasAPI'];
        const results = apis.map(name => {
            const exists = (typeof window[name] === 'object' && window[name] !== null && typeof window[name].getAll === 'function');
            console.log(`test: ${name}:`, exists ? '✅ available' : '❌ missing or incomplete');
            return { name, ok: !!exists };
        });
        const ok = results.every(r => r.ok);
        if (!ok) window.UI && window.UI.toast && window.UI.toast('Tests: algunas APIs no están disponibles', 'error', 5000);
        else window.UI && window.UI.toast && window.UI.toast('Tests: APIs disponibles', 'success', 2200);
        return { ok, details: results };
    };

    window.TestHooks.checkAI = function(){
        const funcs = ['toggleAIChat','toggleAIEnhancements','sendAIMessage'];
        const results = funcs.map(name => {
            const ok = (typeof window[name] === 'function');
            console.log(`test: ${name}:`, ok ? '✅ available' : '❌ missing');
            return { name, ok };
        });
        const ok = results.every(r => r.ok);
        if (!ok) window.UI && window.UI.toast && window.UI.toast('Tests: funciones AI faltantes', 'error', 5000);
        else window.UI && window.UI.toast && window.UI.toast('Tests: AI listo', 'success', 2200);
        return { ok, details: results };
    };

    // Verify the actual modular api client loaded (not only the fallback)
    window.TestHooks.checkAPIClient = function(){
        const ok = !!(window.API && typeof window.API.createAPI === 'function' && window.API.__isRealClient === true);
        console.log('test: API client real loaded:', ok ? '✅ real client' : '❌ fallback or missing');
        if (!ok) window.UI && window.UI.toast && window.UI.toast('Tests: cliente API modular no está cargado (se usa fallback)', 'warn', 5000);
        else window.UI && window.UI.toast && window.UI.toast('Tests: cliente API modular cargado', 'success', 2200);
        return { ok };
    };

    window.TestHooks.checkUI = function(){
        const ui = window.UI;
        const ok = !!(ui && typeof ui.toast === 'function' && typeof ui.showDialog === 'function');
        console.log('test: UI:', ok ? '✅ available' : '❌ missing or incomplete');
        if (!ok) window.UI && window.UI.toast && window.UI.toast('Tests: UI incompleta', 'error', 4000);
        return { ok };
    };

    window.TestHooks.runAll = function(){
        console.group('TestHooks: running all checks');
        const api = window.TestHooks.checkAPIGlobals();
        const ai = window.TestHooks.checkAI();
        const ui = window.TestHooks.checkUI();
        const client = window.TestHooks.checkAPIClient();
        console.groupEnd();
        const ok = api.ok && ai.ok && ui.ok && client.ok;
        console.log('TestHooks summary:', ok ? 'ALL OK ✅' : 'ISSUES ❌');
        return { ok, api, ai, ui, client };
    };

    // Convenience: run tests from console with testAll() or TestHooks.runAll()
    window.testAll = window.TestHooks.runAll;

    // Auto-run if ?runTests=1 is present in the URL (developer convenience)
    try { if (location.search && location.search.indexOf('runTests=1') !== -1) { document.addEventListener('DOMContentLoaded', function(){ setTimeout(()=>{ try{ window.TestHooks.runAll(); }catch(e){console.warn('testAll auto-run failed', e);} }, 120); }); } } catch(e){ safeWarn('test.autoRun', e); }
})();

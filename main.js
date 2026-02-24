/**
 * main.js - Funciones CRUD principales del sistema
 * Gestiona todas las operaciones de agregar, editar y eliminar datos
 * Conecta con los endpoints PHP a través de las APIs en js/api.js
 */

/* eslint-disable no-empty, no-unused-vars */
/* global Quagga, BarcodeDetector */
/* exported agregarProducto, confirmarEliminarTodo, agregarCategoria, agregarClienteForm, agregarProveedorForm, agregarVentaForm, agregarSalidaForm, mostrarSeccionVentas, mostrarSeccionSalidas, mostrarSeccionClientes, mostrarSeccionProveedores, toggleFiltros, limpiarBusqueda, importarExcel, exportarExcel, importarPDF, exportarPDF, descargarPlantillaExcel, descargarPlantillaCSV, importarInventarioDesdeArchivo, toggleTablaEjemplo */

// Logging guard: deshabilita console.log/info/debug en producción para reducir ruido.
(function(){
    try {
        const configEnv = (window && window.CONFIG && window.CONFIG.env) ? window.CONFIG.env : null;
        const isDebug = (typeof window !== 'undefined' && (window.DEBUG === true || (configEnv && configEnv !== 'production')));
        if (!isDebug && typeof console !== 'undefined') {
            ['log','info','debug'].forEach(fn => { if (console[fn]) console[fn] = function(){}; });
        }
    } catch (e) {
        // Silenciar cualquier fallo aquí para no romper la app
    }
})();

// Ensure UI shim exists to avoid ReferenceError if js/ui.js didn't load yet (safe fallback)
if (typeof window.UI === 'undefined') {
    // Visual toast creation using existing styles (.app-toast in app.css)
    const createDOMToast = function(msg, type = 'info', duration = 4000) {
        try {
            const el = document.createElement('div');
            el.className = 'app-toast ' + (type || 'info');
            el.innerHTML = typeof msg === 'object' ? JSON.stringify(msg) : String(msg);
            document.body.appendChild(el);
            setTimeout(() => el.classList.add('visible'), 20);
            setTimeout(() => { el.classList.remove('visible'); setTimeout(() => el.remove(), 360); }, duration);
        } catch (e) { console.warn('[UI shim] createDOMToast failed', e); }
    };

    const ensureLoaderElement = function() {
        let el = document.getElementById('app-shim-loader');
        if (!el) {
            el = document.createElement('div');
            el.id = 'app-shim-loader';
            el.style.position = 'fixed';
            el.style.left = '0';
            el.style.top = '0';
            el.style.right = '0';
            el.style.bottom = '0';
            el.style.display = 'none';
            el.style.alignItems = 'center';
            el.style.justifyContent = 'center';
            el.style.background = 'rgba(0,0,0,0.36)';
            el.style.zIndex = '99998';
            el.innerHTML = '<div style="background:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);font-weight:600;color:#111;">Loading</div>';
            document.body.appendChild(el);
        }
        return el;
    };

    window.UI = {
        _shimUsed: true,
        toast: (msg, type = 'info', duration = 4000, actionLabel, actionCallback) => {
            const text = (typeof msg === 'object') ? JSON.stringify(msg) : String(msg);
            try {
                if (type === 'error') console.error('[UI shim] ERROR', text);
                else if (type === 'warn' || type === 'warning') console.warn('[UI shim] WARN', text);
                else console.info('[UI shim]', type.toUpperCase(), text);
            } catch (e) { console.log('[UI shim]', text); }
            createDOMToast(text, type, duration);
            // Basic action support (fallback): if action callback provided, call it when clicked anywhere on toast
            if (actionLabel && typeof actionCallback === 'function') {
                // Not implemented in shim visually, but call immediately as fallback (no UI button)
                try { actionCallback(); } catch(e){ console.warn('UI shim action callback failed', e); }
            }
        },
        success: (msg, d = 4000) => window.UI.toast && window.UI.toast(`✅ ${msg}`, 'success', d),
        // New helper: showSuccess (supports modes: 'toast'|'dialog'|'both')
        showSuccess: (msg, opts = {}) => {
            const mode = opts.mode || 'both';
            if (mode === 'toast' || mode === 'both') window.UI.toast(msg, 'success', opts.duration || 3500, opts.actionLabel, opts.actionCallback);
            if (mode === 'dialog' || mode === 'both') window.UI.showDialog({ type: 'success', title: opts.title || 'Éxito', message: msg, autoClose: opts.autoClose || 3000, buttons: opts.buttons || [{ text: 'OK', action: 'close' }] });
        },
        alertSuccess: (msg, opts = {}) => window.UI.showDialog(Object.assign({ type: 'success', title: '✅ Éxito', message: msg }, opts)),
        alertError: (msg, opts = {}) => window.UI.showDialog(Object.assign({ type: 'error', title: '❌ Error', message: msg }, opts)),
        // Minimal handleAPIError shim
        handleAPIError: (res, opts = {}) => {
            const message = (res && res.error) ? res.error : (typeof res === 'string' ? res : (res && res.message ? res.message : 'Error desconocido'));
            const raw = res && res.raw ? res.raw : null;
            if (raw) {
                window.UI.showDialog({ type: 'error', title: opts.title || 'Error', html: `<p>${message}</p><pre style="max-height:220px;overflow:auto;">${String(raw).slice(0,2000)}</pre>`, buttons: [{ text: 'Cerrar', action: 'close' }] });
            } else {
                window.UI.alertError(message, opts);
            }
        },
        error: (msg) => window.UI.toast && window.UI.toast(`❌ ${msg}`, 'error', 6000),
        showDialog: (opts) => {
            try {
                // If an external UI implementation exists, delegate to it.
                if (window.UI && typeof window.UI._externalShowDialog === 'function') {
                    return window.UI._externalShowDialog(opts);
                }
            } catch (e) {
                console.warn('UI shim showDialog delegate failed', e);
            }
            console.warn('Dialog (shim):', (opts && (opts.message || opts.title)) || 'Dialog');
            return { close: () => {} };
        },
        closeDialog: () => {},
        showLoader: (m) => {
            const el = ensureLoaderElement();
            const inner = el.firstElementChild;
            if (inner) inner.textContent = m || 'Cargando...';
            el.style.display = 'flex';
        },
        hideLoader: () => { const el = document.getElementById('app-shim-loader'); if (el) el.style.display = 'none'; },
        confirm: (opts) => window.UI.confirm(opts && (opts.message || opts)),
        showSectionDialog: () => {},
        scrollToSection: (id) => { const el = document.getElementById(id); el && el.scrollIntoView({ behavior: 'smooth' }); }
    };
    // Ensure consumers use window.UI explicitly; avoid reassigning global `UI` variable here.
}

// Global navigation helpers used by `data-onclick="navigate"` and similar attributes.
window.navigate = function(target) {
    try {
        // If called as a handler via binder, `this` will be the element.
        if (!target || typeof target !== 'string') {
            if (this && this.dataset && this.dataset.target) target = this.dataset.target;
        }
        if (!target) return;
        // If target looks like a relative path without scheme, respect base href
        window.location.href = String(target);
    } catch (e) { console.warn('navigate failed', e); }
};

window.reloadPage = function() {
    try { window.location.reload(); } catch(e){ console.warn('reloadPage failed', e); }
};

// ============================================================================
// NAVBAR - Funciones para menú móvil y estado activo
// ============================================================================

// Utility helpers: pequeñas funciones para acceso seguro al DOM
function $id(id) { try { return document.getElementById(id); } catch(e) { return null; } }
function $qs(sel) { try { return document.querySelector(sel); } catch(e) { return null; } }
function $qsa(sel) { try { return document.querySelectorAll(sel); } catch(e) { return []; } }

// Small shims for optional helper functions used by forms. These are safe no-op fallbacks
function setFieldError(el, msg) { try { if (el && el.classList) el.classList.add('field-error'); console.warn('setFieldError:', msg); } catch(e) { console.warn('setFieldError failed', e); } }
function clearAllFieldErrors() { try { document.querySelectorAll('.field-error').forEach(e => e.classList.remove('field-error')); } catch(e) { console.warn('clearAllFieldErrors failed', e); } }
function safeWarn(context, e) { try { console.warn(context, e); } catch(_) { /* ignore */ } }

// Safe stubs for functions referenced in multiple places but possibly defined elsewhere
if (typeof window.eliminarProducto === 'undefined') {
    window.eliminarProducto = async function(id) { console.warn('eliminarProducto stub called for id', id); return Promise.resolve({ ok: false, stub: true }); };
}
if (typeof window.resaltarFila === 'undefined') {
    window.resaltarFila = function(tipo, id) { try { console.warn('resaltarFila stub', tipo, id); } catch(e) { safeWarn('resaltarFila failed', e); } };
}

// Conditional lightweight API stubs to avoid runtime/lint errors when real APIs are not loaded.
// These stubs do not alter behavior when real implementations are present (we don't overwrite existing objects).
const makeApiStub = (name) => ({
    getAll: async () => { console.warn(`${name} stub: getAll`); return []; },
    create: async () => { console.warn(`${name} stub: create`); return { success: false, stub: true }; },
    update: async () => { console.warn(`${name} stub: update`); return { success: false, stub: true }; },
    delete: async () => { console.warn(`${name} stub: delete`); return { success: false, stub: true }; }
});

if (typeof window.ProductosAPI === 'undefined') window.ProductosAPI = makeApiStub('ProductosAPI');
if (typeof window.CategoriasAPI === 'undefined') window.CategoriasAPI = makeApiStub('CategoriasAPI');
if (typeof window.ClientesAPI === 'undefined') window.ClientesAPI = makeApiStub('ClientesAPI');
if (typeof window.ProveedoresAPI === 'undefined') window.ProveedoresAPI = makeApiStub('ProveedoresAPI');
if (typeof window.VentasAPI === 'undefined') window.VentasAPI = makeApiStub('VentasAPI');
if (typeof window.SalidasAPI === 'undefined') window.SalidasAPI = makeApiStub('SalidasAPI');

// Local aliases so static analyzers see definitions when they reference bare identifiers
const ProductosAPI = window.ProductosAPI;
const CategoriasAPI = window.CategoriasAPI;
const ClientesAPI = window.ClientesAPI;
const ProveedoresAPI = window.ProveedoresAPI;
const VentasAPI = window.VentasAPI;
const SalidasAPI = window.SalidasAPI;

// Barcode / QR scanner helper (uses BarcodeDetector when available)
(function(){
    async function detectFromVideo(detector, video) {
        try {
            // detector.detect accepts Video/Canvas/Image sources
            const results = await detector.detect(video);
            return results && results.length ? results[0].rawValue || results[0].rawData : null;
        } catch(e){ return null; }
    }

    window.openCodeScanner = async function(opts = {}){
        const inputField = document.getElementById('codigo-producto');
        if (!inputField) { UI && UI.toast && UI.toast('Campo de código no encontrado', 'error'); return; }

        // Create modal UI
        const modal = document.createElement('div');
        modal.style.cssText = 'position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:120000;';
        const card = document.createElement('div');
        // position:relative so overlay can be absolutely positioned over the video
        card.style.cssText = 'position:relative;background:#fff;border-radius:12px;padding:12px;max-width:420px;width:92%;display:flex;flex-direction:column;gap:8px;align-items:stretch;overflow:hidden;';
        const header = document.createElement('div'); header.style.cssText='display:flex;justify-content:space-between;align-items:center;';
        const title = document.createElement('div'); title.textContent = 'Escanear código (QR/Barcode)'; title.style.fontWeight='700';
        const closeBtn = document.createElement('button'); closeBtn.innerHTML='✕'; closeBtn.style.cssText='background:transparent;border:none;font-size:18px;cursor:pointer;';
        header.appendChild(title); header.appendChild(closeBtn);
        const video = document.createElement('video'); video.autoplay = true; video.playsInline = true; video.style.cssText='width:100%;height:auto;border-radius:8px;background:#000';
        // overlay guide: centered rectangle to help user position barcode/QR
        const overlay = document.createElement('div');
        overlay.id = 'scan-overlay';
        overlay.style.cssText = 'position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:78%;height:44%;border:2px dashed rgba(67,206,162,0.85);border-radius:12px;pointer-events:none;box-shadow:0 0 24px rgba(67,206,162,0.08) inset;display:flex;align-items:center;justify-content:center;color:rgba(0,0,0,0.6);font-weight:600;font-size:0.95rem;background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.02));';
        overlay.textContent = 'Alinea el código dentro del recuadro';
        const info = document.createElement('div'); info.textContent = 'Apunta la cámara al código o QR.'; info.style.color='#555'; info.style.fontSize='0.95rem';
        const footer = document.createElement('div'); footer.style.cssText='display:flex;gap:8px;justify-content:flex-end;';
        const takePhotoFallback = document.getElementById('scan-image-input') || (function(){ const f = document.createElement('input'); f.type='file'; f.accept='image/*'; f.capture='environment'; f.style.display='none'; document.body.appendChild(f); return f; })();
        const btnPhoto = document.createElement('button'); btnPhoto.textContent = 'Usar cámara (foto)'; btnPhoto.style.cssText='padding:8px 10px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer;';
        footer.appendChild(btnPhoto);

        card.appendChild(header); card.appendChild(video); card.appendChild(overlay); card.appendChild(info); card.appendChild(footer);
        modal.appendChild(card); document.body.appendChild(modal);

        let stream = null; let detector = null; let scanning = true;

        function cleanup(){
            scanning = false;
            try { if (stream && stream.getTracks) stream.getTracks().forEach(t=>t.stop()); } catch(e){}
            try { modal.remove(); } catch(e){}
            try { if (typeof Quagga !== 'undefined' && Quagga.stop) { try{ Quagga.stop(); }catch(_){}} } catch(e){}
        }

        closeBtn.addEventListener('click', function(){ cleanup(); });
        modal.addEventListener('click', function(e){ if (e.target === modal) cleanup(); });

        btnPhoto.addEventListener('click', function(){ try{ takePhotoFallback.click(); }catch(e){ console.warn(e); } });
        takePhotoFallback.addEventListener('change', async function(){
            const f = this.files && this.files[0]; if (!f) return;
            try {
                const img = await createImageBitmap(f);
                if ('BarcodeDetector' in window) {
                    try { detector = new BarcodeDetector({formats: ['qr_code','ean_13','code_128','ean_8']}); } catch(e){ detector = null; }
                    if (detector) {
                        const res = await detector.detect(img);
                        if (res && res.length) {
                            inputField.value = res[0].rawValue || '';
                            inputField.focus();
                            cleanup();
                            UI && UI.toast && UI.toast('Código detectado', 'success');
                            return;
                        }
                    }
                }
                UI && UI.toast && UI.toast('No se detectó código en la foto', 'error');
            } catch(e){ console.warn('photo detect failed', e); UI && UI.toast && UI.toast('Error procesando imagen', 'error'); }
        });

        // Try to start camera
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            await video.play();

            if ('BarcodeDetector' in window) {
                try { detector = new BarcodeDetector({formats: ['qr_code','ean_13','code_128','ean_8']}); } catch(e){ detector = null; }
            }

            if (!detector) {
                info.textContent = 'Escaneo en vivo no disponible en este navegador. Usa "Usar cámara (foto)" o un escáner físico.';
                // Attempt to load QuaggaJS as a robust barcode fallback (many browsers)
                try {
                    if (typeof Quagga === 'undefined') {
                        await new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = 'https://unpkg.com/quagga@0.12.1/dist/quagga.min.js';
                            s.onload = resolve; s.onerror = reject; document.head.appendChild(s);
                        });
                    }
                } catch(e) {
                    console.warn('Quagga load failed', e);
                }

                if (typeof Quagga !== 'undefined') {
                    info.textContent = 'Escaneando (Quagga)... apunta al código';
                    try {
                        // init Quagga to attach to the video element
                        Quagga.init({
                            inputStream: {
                                name: 'Live',
                                type: 'LiveStream',
                                target: video,
                                constraints: { facingMode: 'environment' }
                            },
                            decoder: {
                                readers: ['ean_reader','ean_8_reader','code_128_reader','code_39_reader','upc_reader','upc_e_reader']
                            },
                            locate: true
                        }, function(err) {
                            if (err) { console.warn('Quagga init error', err); info.textContent = 'Quagga init error'; return; }
                            Quagga.start();
                        });

                        Quagga.onDetected(function(result){
                            try {
                                const code = (result && result.codeResult && result.codeResult.code) ? result.codeResult.code : null;
                                if (code) {
                                    inputField.value = code;
                                    inputField.focus();
                                    UI && UI.toast && UI.toast('Código detectado', 'success');
                                    try { Quagga.stop(); } catch(e){}
                                    cleanup();
                                }
                            } catch(e){ console.warn('quagga onDetected error', e); }
                        });
                    } catch(e){ console.warn('Quagga start failed', e); }
                }
            } else {
                info.textContent = 'Escaneando... apunta al código';
                // scanning loop
                (async function loop(){
                    while(scanning) {
                        try {
                            const result = await detectFromVideo(detector, video);
                            if (result) {
                                inputField.value = result;
                                inputField.focus();
                                UI && UI.toast && UI.toast('Código detectado', 'success');
                                cleanup();
                                return;
                            }
                        } catch(e){ /* ignore */ }
                        await new Promise(r=>setTimeout(r, 300));
                    }
                })();
            }
        } catch (e) {
            console.warn('Camera start failed', e);
            info.textContent = 'No se pudo acceder a la cámara. Usa "Usar cámara (foto)" o conecta un escáner físico.';
        }
    };

    // Attach button on DOM ready
    document.addEventListener('DOMContentLoaded', function(){
        try {
            const btn = document.getElementById('btn-scan-code');
            if (btn && !btn._bound) { btn.addEventListener('click', function(e){ e.preventDefault(); window.openCodeScanner && window.openCodeScanner(); }); btn._bound = true; }
        } catch(e){ console.warn('attach scan button failed', e); }
    });
})();

// Note: functions like `eliminarProducto` and `resaltarFila` are defined later in this file
// and exposed on `window` — avoid creating local const aliases here to prevent duplicate declarations.

// Delegating fallback so calls to `eliminarProducto(id)` before the full implementation
// don't throw. If a full implementation is attached to `window.eliminarProducto` later,
// the delegator will call it.
async function eliminarProducto(id) {
    try {
        if (typeof window.eliminarProducto === 'function' && window.eliminarProducto !== eliminarProducto) {
            return await window.eliminarProducto(id);
        }
    } catch (e) {
        console.warn('eliminarProducto delegator failed', e);
    }
    console.warn('eliminarProducto: no implementation available (stub)', id);
    return { success: false, stub: true };
}


/**
 * Inicializar funcionalidad de navbar
 */
function initNavbar() {
    const toggle = document.getElementById('navbar-toggle');
    const menu = document.querySelector('.navbar-menu');
    const links = document.querySelectorAll('.navbar-link');
    
    console.log('✅ Inicializando navbar...');
    console.log('📊 Navbar links encontrados:', links.length);
    
    // Toggle del menú móvil
    if (toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            try { toggle.classList.toggle('active'); } catch(e){ console.warn('Navbar toggle error', e); }
            try { if (menu) menu.classList.toggle('active'); } catch(e){ console.warn('Navbar toggle error', e); }
            // Update ARIA attributes for accessibility
            try { toggle.setAttribute('aria-expanded', toggle.classList.contains('active') ? 'true' : 'false'); } catch(e){ console.warn('Navbar aria update failed', e); }
            try { if (menu) menu.setAttribute('aria-hidden', toggle.classList.contains('active') ? 'false' : 'true'); } catch(e){ console.warn('Navbar aria update failed', e); }
            try { console.log('🍔 Menu toggle:', toggle.classList.contains('active') ? 'abierto' : 'cerrado'); } catch(e){ console.warn('Navbar log failed', e); }
        });
    }
    
    // Agregar event listeners a los links
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            // Extraer el ID de la sección del onclick
            const onclickAttr = this.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes('navegarA')) {
                // Extraer sectionId del atributo onclick
                // Use RegExp constructor to avoid inline regex with embedded quotes
                const match = onclickAttr.match(new RegExp("['\"]([^'\"]+)['\"]"));
                if (match && match[1]) {
                    const sectionId = match[1];
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('🔗 Link clickeado:', sectionId);
                    
                    // Cerrar menú
                    if (toggle) toggle.classList.remove('active');
                    if (menu) menu.classList.remove('active');

                    // Navegar: preferir API Sections cuando exista (evita manipular style.display directamente)
                    const section = document.getElementById(sectionId);
                    if (section) {
                        try {
                            if (window.Sections && typeof window.Sections.toggle === 'function') {
                                // Ensure the target section is open (Sections.toggle toggles state)
                                const visual = section;
                                const isOpen = visual && visual.classList && visual.classList.contains('is-open');
                                if (!isOpen) {
                                    try { window.Sections.toggle(sectionId); } catch(e){ safeWarn('Sections.toggle failed', e); }
                                }
                            } else {
                                // Fallback: Restore basic visibility without forcing display:none elsewhere
                                section.style.display = 'block';
                                const contentContainer = section.querySelector('.section-content');
                                if (contentContainer) {
                                    contentContainer.style.display = 'block';
                                    contentContainer.style.opacity = '1';
                                }
                            }
                        } catch(e){ safeWarn('navegacion open section', e); }

                        setTimeout(() => {
                            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 50);
                    }
                    
                    return false;
                }
            }
        });
    });
    
    console.log('✅ Navbar inicializado correctamente');
}

/**
 * Actualizar el link activo en la navbar según la sección visible
 */
function updateActiveNavLink() {
    const links = document.querySelectorAll('.navbar-link');
    links.forEach(link => link.classList.remove('active'));
    
    // No hacer nada más por ahora - los links se marcan manualmente
}

// ============================================================================
// NAVEGACIÓN
// ============================================================================

/**
 * Navegar a una sección desde la navbar
 */
function navegarA(event, sectionId) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    console.log('🔗 Navegando a:', sectionId);
    
    // Cerrar menú móvil
    const toggle = document.getElementById('navbar-toggle');
    const menu = document.querySelector('.navbar-menu');
    if (toggle) toggle.classList.remove('active');
    if (menu) menu.classList.remove('active');
    
    // Ocultar/cerrar todas las secciones principales (usar Sections si está disponible para evitar set display:none)
    document.querySelectorAll('section.section, .section-card').forEach(s => {
        try {
            if (window.Sections && typeof window.Sections.toggle === 'function') {
                if (s.classList && s.classList.contains('is-open')) {
                    const contentEl = s.querySelector('.section-content') || s.querySelector('[id$="-content"]');
                    const id = (s.id) ? s.id : (contentEl && contentEl.id ? contentEl.id.replace(/-content$/,'') : null);
                    if (id) {
                        try { window.Sections.toggle(id); } catch(e){ safeWarn('Sections.toggle during navegarA', e); }
                    }
                }
            } else {
                // Fallback: hide the section element (legacy behavior)
                s.style.display = 'none';
            }
        } catch(e){ safeWarn('navegarA hide sections', e); }
    });
    // Mostrar solo la sección solicitada
    const section = document.getElementById(sectionId);
    if (section) {
        try {
            if (window.Sections && typeof window.Sections.toggle === 'function') {
                const visual = section;
                const isOpen = visual && visual.classList && visual.classList.contains('is-open');
                if (!isOpen) {
                    try { window.Sections.toggle(sectionId); } catch(e){ safeWarn('Sections.toggle on navigate', e); }
                }
            } else {
                section.style.display = 'block';
            }
        } catch(e){ safeWarn('navegarA open section', e); }

        // Scroll suave y resaltado persistente
        setTimeout(() => {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (typeof resaltarSeccion === 'function') resaltarSeccion(sectionId, 7000);
        }, 50);
        // Marcar el link activo en la navbar (resaltar la sección actual)
        try {
            const links = document.querySelectorAll('.navbar-link');
            links.forEach(link => {
                const target = (link.dataset && link.dataset.target) ? link.dataset.target : null;
                const dataOn = link.getAttribute('data-onclick') || '';
                const onclickAttr = link.getAttribute('onclick') || '';
                const href = link.getAttribute('href') || '';
                const matches = (target === sectionId) || dataOn.indexOf("'" + sectionId + "'") !== -1 || dataOn.indexOf('"' + sectionId + '"') !== -1 || onclickAttr.indexOf("'" + sectionId + "'") !== -1 || onclickAttr.indexOf('"' + sectionId + '"') !== -1 || (href === '#' + sectionId);
                if (matches) {
                    link.classList.add('active');
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.classList.remove('active');
                    link.removeAttribute('aria-current');
                }
            });
        } catch (e) {
            console.warn('No se pudo actualizar el estado activo del navbar', e);
        }
    } else {
        console.warn('❌ Sección no encontrada:', sectionId);
        // Si no existe la sección, remover active de todos los links
        try { document.querySelectorAll('.navbar-link').forEach(l=>{ l.classList.remove('active'); l.removeAttribute('aria-current'); }); } catch(e){ safeWarn('Failed clearing navbar active state', e); }
    }
    
    return false;
}

/**
 * Restaurar la vista completa: mostrar todas las secciones y volver al inicio
 * Usado por el enlace "Ver todo" en la navbar
 */
function mostrarTodo(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    }

    // Mostrar todas las secciones (dejar en estado inicial)
    document.querySelectorAll('section.section, .section-card').forEach(s => {
        try { s.style.display = ''; } catch(e) { safeWarn('mostrarTodo restore display', e); }
    });

    // If Sections is available, clear saved collapsed state and expand sections visually
    try {
        if (window.Sections && typeof window.Sections.toggle === 'function') {
            try { localStorage.removeItem('sections_visibility_v1'); } catch(e){ /* ignore */ }
            document.querySelectorAll('section.section, .section-card').forEach(s => {
                try {
                    const contentEl = s.querySelector('.section-content') || s.querySelector('[id$="-content"]');
                    const id = (s.id) ? s.id : (contentEl && contentEl.id ? contentEl.id.replace(/-content$/,'') : null);
                    const content = (id && document.getElementById(id + '-content')) || contentEl;
                    if (content) {
                        s.classList.add('is-open');
                        content.style.opacity = '1';
                        content.style.maxHeight = 'none';
                        const btn = document.querySelector('.btn-toggle-section[aria-controls="' + (id ? id + '-content' : '') + '"]') || document.querySelector('.btn-toggle-section[aria-controls="' + (id || '') + '"]');
                        if (btn) {
                            try { btn.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar'; btn.setAttribute('aria-expanded','true'); } catch(e){ /* ignore */ }
                        }
                    }
                } catch(e){ /* ignore */ }
            });
        }
    } catch(e){ safeWarn('mostrarTodo sections restore', e); }

    // Quitar clases de activo del navbar
    try { document.querySelectorAll('.navbar-link').forEach(l=>{ l.classList.remove('active'); l.removeAttribute('aria-current'); }); } catch(e){ safeWarn('Failed clearing navbar active state', e); }

    // Scroll al inicio
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Notificación breve si existe helper UI
    if (window.UI && typeof window.UI.toast === 'function') {
        window.UI.toast('Vista completa restaurada', 'success', 1800);
    }

    return false;
}

/**
 * Abrir el test de SQL desde la navbar
 */
// abrirTestSQL removed (test SQL button removed from UI)

// ============================================================================
// UTILIDADES
// ============================================================================

// Delegación global para botones de eliminar (soporta botones estáticos y generados dinámicamente)
(function(){
    function inferTipoDesdeElemento(el) {
        // Buscar atributo data-type en el botón o en el contenedor más cercano
        if (!el) return null;
        const btnType = el.dataset && el.dataset.type;
        if (btnType) return btnType;
        const row = el.closest('[data-type]') || el.closest('section');
        if (row) {
            if (row.dataset && row.dataset.type) return row.dataset.type;
            const sec = row.id || (row.getAttribute('id') || '');
            // mapear ids de sección a tipos
            const map = { 'productos':'producto', 'categorias':'categoria', 'clientes':'cliente', 'proveedores':'proveedor', 'ventas':'venta', 'salidas':'salida' };
            if (map[sec]) return map[sec];
        }
        return null;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest && e.target.closest('.btn-eliminar');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const id = btn.dataset && (btn.dataset.id || btn.getAttribute('data-id'));
        const tipo = btn.dataset && (btn.dataset.type || inferTipoDesdeElemento(btn));
        if (!id || !tipo) {
            console.warn('Botón eliminar sin id o tipo detectado', btn);
            UI.showDialog({ title:'❌ Error', icon:'⚠️', message: 'No se pudo determinar el elemento a eliminar.', buttons: [{ text:'OK', action:'close' }] });
            return;
        }

        // Etiquetar el modal para referencia si botones estáticos usan delegación
        try { const modal = document.getElementById('modal-dialogo'); if (modal) { modal.dataset.triggerButton = btn.id || ''; } } catch(e){ console.warn('modal dataset tagging failed', e); }

        // Abrir confirmación con la función existente
        if (typeof confirmarEliminar === 'function') confirmarEliminar(tipo, id);
    }, { capture: false });
})();
// Prefill helpers used by AI quick actions
window.prefillProductForm = function(data){
    try {
        if (!data) return;
        // Fill form fields if present
        try { const el = $id('codigo-producto'); if (el && data.codigo !== undefined) el.value = data.codigo; } catch(e){ console.warn('prefill codigo failed', e); }
        try { const el = $id('nombre-producto'); if (el && data.nombre !== undefined) el.value = data.nombre; } catch(e){ console.warn('prefill nombre failed', e); }
        try { const el = $id('cantidad-producto'); if (el && data.cantidad !== undefined) el.value = data.cantidad; } catch(e){ console.warn('prefill cantidad failed', e); }
        try { const el = $id('precio-producto'); if (el && data.precio !== undefined) el.value = data.precio; } catch(e){ console.warn('prefill precio failed', e); }
        try { const el = $id('costo-producto'); if (el && data.costo !== undefined) el.value = data.costo; } catch(e){ console.warn('prefill costo failed', e); }
        try { const el = $id('fecha-caducidad'); if (el && data.fecha_caducidad !== undefined) el.value = data.fecha_caducidad; } catch(e){ console.warn('prefill fecha failed', e); }
        if (data.categoria_id !== undefined && data.categoria_id !== null) {
            const sel = document.getElementById('categoria-producto'); if (sel) { sel.value = data.categoria_id; }
        }
        // Navigate to product form
        if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'registro-productos');
        // Focus on name input
        setTimeout(()=>{ const el = document.getElementById('nombre-producto'); if (el) el.focus(); }, 200);
        UI && UI.toast && UI.toast('Formulario preflleno con datos de AI', 'success');
    } catch(e){ console.warn('prefillProductForm failed', e); }
};

window.prefillCategory = function(name){
    try {
        if (!name) return;
        const el = document.getElementById('nueva-categoria'); if (el) el.value = name;
        if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'ajustes');
        UI && UI.toast && UI.toast('Campo categoría rellenado', 'info');
    } catch(e){ console.warn('prefillCategory failed', e); }
};

/**
 * Abrir date picker sin recursión infinita
 */
function abrirDatePicker(elementId) {
    console.log('📅 Abriendo date picker para:', elementId);
    
    const elemento = document.getElementById(elementId);
    
    if (!elemento) {
        console.error('❌ Elemento no encontrado:', elementId);
        return false;
    }
    
    console.log('✅ Elemento encontrado:', elemento.tagName, elemento.type);
    
    try {
        // Asegurar que el elemento está visible y accesible
        if (elemento.style.display === 'none') {
            elemento.style.display = '';
        }

        // Preferir API moderna showPicker si está disponible
        if (typeof elemento.showPicker === 'function') {
            try { elemento.showPicker(); }
            catch(e) { elemento.focus(); elemento.click(); }
        } else {
            // Fallback: focus + click
            elemento.focus();
            try { elemento.click(); } catch(e){ safeWarn('element click failed', e); }
        }
        return true;
    } catch (e) {
        console.error('Error abriendo date picker:', e);
        return false;
    }
}

/**
 * Rellenar fecha de registro automáticamente con la fecha/hora actual
 */
function rellenarFechaRegistroActual() {
    const hoy = new Date();
    const fechaFormato = hoy.toISOString().split('T')[0]; // YYYY-MM-DD
    const inputFecha = document.getElementById('fecha-registro');
    if (inputFecha) {
        inputFecha.value = fechaFormato;
    }
}

/**
 * Inicializar formulario
 */
function inicializarFormulario() {
    rellenarFechaRegistroActual();
    inicializarDatePickers();
}

/**
 * Inicializar event listeners para date pickers
 */
function inicializarDatePickers() {
    const fechaCaducidad = document.getElementById('fecha-caducidad');
    const iconoCaducidad = document.getElementById('fecha-caducidad-icon');
    
    if (fechaCaducidad) {
        // Agregar evento click SIN preventDefault - permite que el picker nativo funcione
        fechaCaducidad.addEventListener('click', function(e) {
            abrirDatePicker('fecha-caducidad');
        });
        
        // Agregar evento change para logging
        fechaCaducidad.addEventListener('change', function(e) {
            console.log('📅 Fecha seleccionada:', this.value);
        });
        
        console.log('✅ Date picker inicializado');
    }
    
    // Evento para el icono
    if (iconoCaducidad) {
        iconoCaducidad.addEventListener('click', function(e) {
            abrirDatePicker('fecha-caducidad');
        });
    }
}

// Helper para formatear errores de API, incluye vista previa de 'raw' si existe
function formatAPIError(res) {
    if (!res) return 'Respuesta inválida';
    const base = res && res.error ? res.error : 'Respuesta inválida';
    if (res.raw) {
        const raw = String(res.raw).trim();
        const preview = raw.length > 180 ? raw.slice(0,180) + '...' : raw;
        return `${base} (respuesta: ${preview})`;
    }
    return base;
}

// Validation helpers: add/remove visual error state and messages
window.setFieldError = function(el, msg) {
    try {
        if (!el) return;
        el.classList.add('field-error');
        // remove existing message
        const next = el.nextElementSibling;
        if (next && next.classList && next.classList.contains('error-msg')) {
            next.textContent = msg || '';
            return;
        }
        const span = document.createElement('div');
        span.className = 'error-msg';
        span.textContent = msg || '';
        el.parentNode.insertBefore(span, el.nextSibling);
    } catch(e){ console.warn('setFieldError failed', e); }
};

window.clearFieldError = function(el) {
    try {
        if (!el) return;
        el.classList.remove('field-error');
        const next = el.nextElementSibling;
        if (next && next.classList && next.classList.contains('error-msg')) next.remove();
    } catch(e){ console.warn('clearFieldError failed', e); }
};

window.clearAllFieldErrors = function() {
    try {
        document.querySelectorAll('.field-error').forEach(el=>el.classList.remove('field-error'));
        document.querySelectorAll('.error-msg').forEach(el=>el.remove());
    } catch(e){console.warn('clearAllFieldErrors failed', e);}
};


// Inicialización robusta de botones flotantes y acciones principales
document.addEventListener('DOMContentLoaded', function() {
    inicializarFormulario();

    // Botones flotantes (calculadora, AI, AI Enhance)
    const fabCalc = document.getElementById('btnCalc');
    const fabAI = document.getElementById('btnAIChat');
    const fabAIEnhance = document.getElementById('btnAIEnhance');

    // Helper to sync ARIA/visual states
    function updateFabStates() {
        try {
            // Calculadora
            const calcModal = document.getElementById('calculatorModal');
            if (fabCalc) {
                const pressed = !!(calcModal && calcModal.classList.contains('active'));
                fabCalc.setAttribute('aria-pressed', pressed ? 'true' : 'false');
                fabCalc.classList.toggle('active', pressed);
            }
            // AI Chat
            const aiChat = document.getElementById('aiChatbot');
            if (fabAI) {
                const pressed = !!(aiChat && aiChat.classList.contains('active'));
                fabAI.setAttribute('aria-pressed', pressed ? 'true' : 'false');
                fabAI.classList.toggle('active', pressed);
            }
            // AI Enhance mode
            const body = document.body;
            if (fabAIEnhance) {
                const pressed = !!(body && body.classList.contains('ai-enhanced'));
                fabAIEnhance.setAttribute('aria-pressed', pressed ? 'true' : 'false');
                fabAIEnhance.classList.toggle('active', pressed);
            }
        } catch(e){ console.warn('updateFabStates failed', e); }
    }

    // Keyboard activation for accessibility (Enter / Space)
    function enableKeyboardActivation(el) {
        if (!el) return;
        el.setAttribute('role','button');
        el.tabIndex = el.tabIndex >= 0 ? el.tabIndex : 0;
        el.addEventListener('keydown', function(e){
            if (e.key === 'Enter' || e.key === ' ' || e.code === 'Space') {
                e.preventDefault(); el.click();
            }
        });
    }

    // Attach handlers (idempotent)
    if (fabCalc) {
        fabCalc.onclick = function(){
            if (typeof window.toggleCalculator === 'function') { window.toggleCalculator(); updateFabStates(); }
            else { UI.showDialog({title:'Calculadora',message:'Calculadora no disponible',type:'info'}); }
            return false;
        };
        enableKeyboardActivation(fabCalc);
    }

    if (fabAI) {
        fabAI.onclick = function(){
            if (typeof window.toggleAIChat === 'function') { window.toggleAIChat(); updateFabStates(); }
            else { UI.showDialog({title:'Asistente virtual',message:'Asistente virtual no disponible',type:'info'}); }
            return false;
        };
        enableKeyboardActivation(fabAI);
    }

    if (fabAIEnhance) {
        fabAIEnhance.onclick = function(){
            if (typeof window.toggleAIEnhancements === 'function') { window.toggleAIEnhancements(); updateFabStates(); }
            else { UI.showDialog({title:'Mejora AI',message:'Función de mejora AI no disponible',type:'info'}); }
            return false;
        };
        enableKeyboardActivation(fabAIEnhance);
    }

    // Ensure initial sync
    setTimeout(updateFabStates, 50);

    // Wrap toggle functions so external calls also update FAB visual states
    (function(){
        let attempts = 0, maxAttempts = 10, interval = 200;
        function wrapAll() {
            try {
                let wrappedAny = false;
                if (typeof window.toggleCalculator === 'function' && !window.toggleCalculator.__fabWrapped) {
                    const _origCalc = window.toggleCalculator;
                    window.toggleCalculator = function(){ const r = _origCalc.apply(this, arguments); try{ updateFabStates(); }catch(e){ safeWarn('updateFabStates (calculator) failed', e); } return r; };
                    window.toggleCalculator.__fabWrapped = true;
                    wrappedAny = true;
                }
                if (typeof window.toggleAIChat === 'function' && !window.toggleAIChat.__fabWrapped) {
                    const _origAI = window.toggleAIChat;
                    window.toggleAIChat = function(){ const r = _origAI.apply(this, arguments); try{ updateFabStates(); }catch(e){ safeWarn('updateFabStates (AIChat) failed', e); } return r; };
                    window.toggleAIChat.__fabWrapped = true;
                    wrappedAny = true;
                }
                if (typeof window.toggleAIEnhancements === 'function' && !window.toggleAIEnhancements.__fabWrapped) {
                    const _origEnh = window.toggleAIEnhancements;
                    window.toggleAIEnhancements = function(){ const r = _origEnh.apply(this, arguments); try{ updateFabStates(); }catch(e){ safeWarn('updateFabStates (AIEnhancements) failed', e); } return r; };
                    window.toggleAIEnhancements.__fabWrapped = true;
                    wrappedAny = true;
                }
                if (!wrappedAny && attempts < maxAttempts) {
                    attempts++;
                    setTimeout(wrapAll, interval);
                }
            } catch(e) { console.warn('Wrapping toggles failed', e); }
        }
        wrapAll();
    })();

    // Botones de logo (modificar, guardar, cancelar)
    const btnModificar = document.getElementById('btn-modificar');
    const btnGuardar = document.getElementById('btn-guardar');
    const btnCancelar = document.getElementById('btn-cancelar');
    const logoBox = document.getElementById('logo-box');
    const logoBtns = document.getElementById('logo-btns');
    if (btnModificar && logoBtns) btnModificar.onclick = function(){
        logoBtns.style.opacity = 1;
        logoBtns.style.pointerEvents = 'auto';
    };
    if (btnGuardar && logoBtns) btnGuardar.onclick = function(){
        logoBtns.style.opacity = 0;
        logoBtns.style.pointerEvents = 'none';
        window.UI && window.UI.success && window.UI.success('Logo guardado (demo)');
    };
    if (btnCancelar && logoBtns) btnCancelar.onclick = function(){
        logoBtns.style.opacity = 0;
        logoBtns.style.pointerEvents = 'none';
    };

    // Delegación para navegación: click / teclado en links de la navbar
    (function(){
        function extractSectionIdFromLink(link) {
            if (!link) return null;
            if (link.dataset && link.dataset.target) return link.dataset.target;
            const dataOn = link.getAttribute('data-onclick') || '';
            const onclickAttr = link.getAttribute('onclick') || '';
            // Use RegExp constructor to avoid inline literal containing quotes
            const m = (dataOn || onclickAttr).match(new RegExp("navegarA\\(\\s*event\\s*,\\s*['\\\"]([^'\\\"]+)['\\\"]\\s*\\)"));
            if (m) return m[1];
            const href = link.getAttribute('href') || '';
            if (href && href.startsWith('#') && href.length > 1) return href.slice(1);
            return null;
        }

        const navContainer = document.querySelector('.navbar') || document.body;
        if (navContainer) {
            navContainer.addEventListener('click', function(e){
                const link = e.target && e.target.closest && e.target.closest('.navbar-link');
                if (!link) return;
                const sec = extractSectionIdFromLink(link);
                if (!sec) return;
                e.preventDefault();
                e.stopPropagation();
                if (typeof navegarA === 'function') navegarA(e, sec);
            });

            navContainer.addEventListener('keydown', function(e){
                if (e.key !== 'Enter' && e.key !== ' ') return;
                const link = e.target && e.target.closest && e.target.closest('.navbar-link');
                if (!link) return;
                const sec = extractSectionIdFromLink(link);
                if (!sec) return;
                e.preventDefault();
                if (typeof navegarA === 'function') navegarA(e, sec);
            });
        }
    })();

    // Asegurar funciones globales
    if (typeof window.toggleCalculator !== 'function') {
        window.toggleCalculator = function(){UI.showDialog({title:'Calculadora',message:'Calculadora no disponible',type:'info'});};
    }
    if (typeof window.toggleAIChat !== 'function') {
        window.toggleAIChat = function(){UI.showDialog({title:'Asistente virtual',message:'Asistente virtual no disponible',type:'info'});};
    }
    if (typeof window.toggleAIEnhancements !== 'function') {
        window.toggleAIEnhancements = function(){UI.showDialog({title:'Mejora AI',message:'Función de mejora AI no disponible',type:'info'});};
    }
});

// Helper: wrap promise or function returning promise with timeout to prevent hangs
function apiCallWithTimeout(promiseOrFn, ms = 7000) {
    const p = (typeof promiseOrFn === 'function') ? promiseOrFn() : promiseOrFn;
    let timer;
    const timeout = new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('timeout')), ms); });
    return Promise.race([p, timeout]).then(res => { clearTimeout(timer); return res; }).catch(err => { clearTimeout(timer); throw err; });
}

// Global safe binder for `data-onclick` and `data-target` to replace unsafe inline handlers.
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Bind simple data-onclick: allows calls like `fnName()` or `fnName('arg', 1)`.
        (function(){
            function safeParseArgs(argsRaw) {
                if (!argsRaw) return [];
                try { return JSON.parse('[' + argsRaw + ']'); } catch (e) { /* ignore parse error */ }
                try {
                    const converted = argsRaw.replace(/'([^'\\]*(?:\\.[^'\\]*)*)'/g, function(_, s){ return '"' + s.replace(/"/g, '\\"') + '"'; });
                    return JSON.parse('[' + converted + ']');
                } catch (e) { /* ignore conversion parse error */ }
                return [ argsRaw.replace(/^\s*['"]?|['"]?\s*$/g, '') ];
            }

            document.querySelectorAll('[data-onclick]').forEach(function(el){
                const code = (el.getAttribute('data-onclick') || '').trim();
                if (!code) return;
                const m = code.match(/^([A-Za-z_$][0-9A-Za-z_$]*)\s*(?:\((.*)\))?\s*;?\s*$/);
                if (!m) { console.warn('main.js skipping unsafe data-onclick:', code); return; }
                const fnName = m[1]; const argsRaw = m[2] || '';
                el.addEventListener('click', function(evt){
                    try {
                        const fn = window[fnName];
                        if (typeof fn !== 'function') { console.warn('main.js data-onclick not found:', fnName); return; }
                        const args = safeParseArgs(argsRaw);
                        const result = fn.apply(this, args);
                        // Prevent default if handler explicitly returns false
                        if (result === false) {
                            try { evt.preventDefault(); } catch(e){ /* ignore */ }
                        } else {
                            // For anchors that point to '#' or are purely fragment links, prevent navigation by default
                            try {
                                const href = (el.getAttribute && el.getAttribute('href')) ? el.getAttribute('href') : null;
                                if (el.tagName === 'A' && href && href.trim().charAt(0) === '#') evt.preventDefault();
                            } catch(e){ /* ignore */ }
                        }
                    } catch(e){ console.warn('main.js data-onclick exec failed', e); }
                });
            });
        })();

        // Bind data-target to call navegarA if present (common navigation pattern)
        document.querySelectorAll('[data-target]').forEach(function(el){
            el.addEventListener('click', function(evt){
                try {
                    const target = el.dataset && el.dataset.target;
                    if (!target) return;
                    if (typeof navegarA === 'function') {
                        evt.preventDefault(); evt.stopPropagation(); navegarA(evt, target);
                    }
                } catch(e){ safeWarn('data-target click failed', e); }
            });
        });
    } catch(e) { safeWarn('main.js data-onclick binder failed', e); }
});

// ============================================================================
// PRODUCTOS - CRUD OPERATIONS
// ============================================================================

/**
 * Cargar todos los productos desde la base de datos
 */
async function cargarProductos() {
    try {
        console.log('📥 Cargando productos...');
        let res;
        try {
            res = await apiCallWithTimeout(() => ProductosAPI.getAll(), 7000);
        } catch (error) {
            if (error && error.message === 'timeout') { console.warn('ProductosAPI timeout'); UI.toast && UI.toast('Timeout cargando productos', 'warn', 5000); return; }
            console.error('❌ Error cargando productos:', error); UI.alertError('Error al cargar productos: ' + (error && error.message || error)); return;
        }
        let productos = [];
        if (Array.isArray(res)) productos = res;
        else if (res && res.success === true && Array.isArray(res.data)) productos = res.data;
        else {
            console.error('Error: ProductosAPI no retornó un array', res);
            UI.handleAPIError(res, { title: 'Error cargando productos' });
            return;
        }
        
        mostrarProductos(productos);
        console.log(`✅ ${productos.length} productos cargados`);
    } catch (error) {
        console.error('❌ Error cargando productos:', error);
        UI.alertError('Error al cargar productos: ' + (error && error.message || error));
    }
}

/**
 * Mostrar productos en la tabla
 */
function mostrarProductos(productos = []) {
    // Guardar en variable global para exportaciones
    window.productos = productos;
    
    const tbody = document.getElementById('tabla-body');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (productos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#999;">No hay productos registrados</td></tr>';
        return;
    }
    
    productos.forEach(prod => {
        const row = document.createElement('tr');
        const imagenHTML = prod.imagen ? `<img src="uploads/${prod.imagen}" style="max-width:40px;max-height:40px;" title="${prod.imagen}">` : 'N/A';
        row.innerHTML = `
            <td>${prod.codigo || 'N/A'}</td>
            <td>${prod.nombre || 'N/A'}</td>
            <td>${prod.categoria_nombre || prod.categoria_id || 'N/A'}</td>
            <td>${prod.cantidad || 0}</td>
            <td>$${parseFloat(prod.costo || 0).toFixed(2)}</td>
            <td>$${parseFloat(prod.precio || 0).toFixed(2)}</td>
            <td>${prod.fecha_caducidad || 'N/A'}</td>
            <td>${prod.created_at ? new Date(prod.created_at).toLocaleDateString('es-MX') : 'N/A'}</td>
            <td>${imagenHTML}</td>
            <td>
                <button type="button" class="btn-editar" data-id="${prod.id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn-eliminar" data-type="producto" data-id="${prod.id}" aria-label="Eliminar producto ${prod.nombre || prod.codigo}" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);

        // Attach event listeners safely (avoid inline onclick and ensure prod.id exists)
        try {
            const editBtn = row.querySelector('.btn-editar');
            if (editBtn) editBtn.addEventListener('click', function (e) { e.preventDefault(); editarProducto(prod.id); });
            // El listener de eliminar se maneja por delegación global (.btn-eliminar)
            // Se deja el botón con data-type/data-id para que la delegación lo detecte.
        } catch (e) { console.warn('Could not attach product action listeners', e); }
    });
}

/**
 * Agregar nuevo producto
 */
async function agregarProducto() {
    try {
        console.log('🔵 ===== INICIANDO: Agregar Producto =====');
        
        // Deshabilitar botón principal de agregar para evitar envíos múltiples
        const btnProd = document.getElementById('btn-agregar');
        if (btnProd) btnProd.disabled = true;

        // Clear previous inline errors and obtain values
        try { if (typeof clearAllFieldErrors === 'function') clearAllFieldErrors(); } catch(e){ safeWarn('clearAllFieldErrors failed', e); }
        // Obtener valores del formulario
        const codigo = document.getElementById('codigo-producto')?.value?.trim();
        const nombre = document.getElementById('nombre-producto')?.value?.trim();
        const categoria_id = document.getElementById('categoria-producto')?.value;
        const cantidad = parseInt(document.getElementById('cantidad-producto')?.value || 0);
        const costo = parseFloat(document.getElementById('costo-producto')?.value);
        const precio = parseFloat(document.getElementById('precio-producto')?.value);
        const fecha_caducidad = document.getElementById('fecha-caducidad')?.value;
        const fecha_registro = document.getElementById('fecha-registro')?.value;
        const imagen = document.getElementById('imagen-producto')?.files?.[0];
        
        console.log('📝 Valores capturados del formulario:', {
            codigo: codigo || '(vacío)',
            nombre: nombre || '(vacío)',
            categoria_id: categoria_id || '(vacío)',
            cantidad,
            costo,
            precio,
            fecha_caducidad: fecha_caducidad || '(vacío)',
            fecha_registro: fecha_registro || '(vacío)',
            imagen: imagen?.name || 'sin imagen'
        });
        
        // Obtener fecha de registro automáticamente si está vacía
        let fecha_registro_final = fecha_registro;
        if (!fecha_registro_final) {
            const hoy = new Date();
            fecha_registro_final = hoy.toISOString().split('T')[0];
            console.log('📅 Fecha de registro asignada automáticamente:', fecha_registro_final);
        }
        
        // Validar campos requeridos
        console.log('🔍 Validando campos requeridos...');
        if (!codigo) {
            console.warn('❌ Código vacío');
            try { const el = document.getElementById('codigo-producto'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor ingresa el Código del Producto'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError codigo failed', e); }
            if (btnProd) btnProd.disabled = false;
            return;
        }
        
        if (!nombre) {
            console.warn('❌ Nombre vacío');
            try { const el = document.getElementById('nombre-producto'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor ingresa el Nombre del Producto'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError nombre failed', e); }
            if (btnProd) btnProd.disabled = false;
            return;
        }
        
        if (!categoria_id) {
            console.warn('❌ Categoría no seleccionada');
            try { const el = document.getElementById('categoria-producto'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor selecciona una Categoría'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError categoria failed', e); }
            if (btnProd) btnProd.disabled = false;
            return;
        }
        
        if (isNaN(costo) || costo === null) {
            console.warn('❌ Costo inválido');
            try { const el = document.getElementById('costo-producto'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor ingresa un Costo válido'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError costo failed', e); }
            if (btnProd) btnProd.disabled = false;
            return;
        }
        
        if (isNaN(precio) || precio === null) {
            console.warn('❌ Precio inválido');
            try { const el = document.getElementById('precio-producto'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor ingresa un Precio válido'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError precio failed', e); }
            if (btnProd) btnProd.disabled = false;
            return;
        }
        
        if (!fecha_caducidad) {
            console.warn('❌ Fecha de caducidad vacía');
            try { const el = document.getElementById('fecha-caducidad'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor selecciona la Fecha de Caducidad'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError fecha failed', e); }
            if (btnProd) btnProd.disabled = false;
            return;
        }
        
        console.log('✅ Todas las validaciones pasaron');
        // Frontend duplicate check: query existing products by name/code before showing confirmation
        try {
            const q = encodeURIComponent(codigo || nombre);
            const resp = await fetch('/api/productos.php?q=' + q, { method: 'GET' });
            if (resp && resp.ok) {
                const json = await resp.json();
                if (json && Array.isArray(json.data) && json.data.length > 0) {
                    // check for exact duplicates (case-insensitive for name)
                    const dup = json.data.find(p => (p.codigo && p.codigo === codigo) || (p.nombre && p.nombre.toLowerCase() === (nombre || '').toLowerCase()));
                    if (dup) {
                        UI.showDialog({
                            title: '⚠️ Producto duplicado',
                            icon: '🚫',
                            message: `Ya existe un producto con el mismo nombre o código: <strong>${dup.nombre || dup.codigo}</strong>`,
                            buttons: [{ text: 'OK', action: 'close' }]
                        });
                        if (btnProd) btnProd.disabled = false;
                        return;
                    }
                }
            }
        } catch (err) {
            console.warn('No fue posible verificar duplicados en frontend:', err);
            // proceed silently — server will still reject duplicates with 409
        }

        if (cantidad < 0 || costo < 0 || precio < 0) {
            UI.showDialog({
                title: '⚠️ Valores Inválidos',
                icon: '❌',
                message: 'La cantidad, costo y precio deben ser valores positivos (≥ 0)',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        // Crear objeto de producto
        const producto = {
            codigo,
            nombre,
            categoria_id: parseInt(categoria_id),
            cantidad,
            costo,
            precio,
            fecha_caducidad,
            fecha_registro: fecha_registro_final
        };
        
        // Si hay imagen, agregarla
        if (imagen) {
            producto.imagen = imagen.name;
        }
        
        // Mostrar diálogo de confirmación mejorado
        const categoriaNombre = document.getElementById('categoria-producto').options[document.getElementById('categoria-producto').selectedIndex].text;
        const ganancia = precio - costo;
        const margenGanancia = costo > 0 ? ((ganancia / costo) * 100).toFixed(2) : 0;
        
        // Crear vista previa de imagen si existe
        let imagenPreview = '';
        if (imagen) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagenPreview = e.target.result;
            };
            reader.readAsDataURL(imagen);
        }
        
        const mensaje = `
            <div style="max-height: 500px; overflow-y: auto; padding: 0 10px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">📦 ${nombre}</h3>
                    <p style="margin: 5px 0; font-size: 14px;">Código: <strong>${codigo}</strong></p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                    <div style="background: #f0f4ff; padding: 12px; border-radius: 6px; border-left: 4px solid #667eea;">
                        <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">🗂️ Categoría</p>
                        <p style="margin: 0; font-size: 14px; font-weight: bold; color: #333;">${categoriaNombre}</p>
                    </div>
                    <div style="background: #f0f4ff; padding: 12px; border-radius: 6px; border-left: 4px solid #667eea;">
                        <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">📦 Cantidad</p>
                        <p style="margin: 0; font-size: 14px; font-weight: bold; color: #333;">${cantidad} unid.</p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                    <div style="background: #fff3e0; padding: 12px; border-radius: 6px; border-left: 4px solid #ff9800;">
                        <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">💰 Costo</p>
                        <p style="margin: 0; font-size: 16px; font-weight: bold; color: #e65100;">$${parseFloat(costo).toFixed(2)}</p>
                    </div>
                    <div style="background: #f3e5f5; padding: 12px; border-radius: 6px; border-left: 4px solid #9c27b0;">
                        <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">💲 Precio Venta</p>
                        <p style="margin: 0; font-size: 16px; font-weight: bold; color: #6a1b9a;">$${parseFloat(precio).toFixed(2)}</p>
                    </div>
                </div>
                
                <div style="background: #e8f5e9; padding: 12px; border-radius: 6px; border-left: 4px solid #4caf50; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">💹 Ganancia por Unidad</p>
                            <p style="margin: 0; font-size: 16px; font-weight: bold; color: #2e7d32;">$${ganancia.toFixed(2)}</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">📈 Margen (%)</p>
                            <p style="margin: 0; font-size: 16px; font-weight: bold; color: #2e7d32;">${margenGanancia}%</p>
                        </div>
                    </div>
                </div>
                
                <div style="background: #e3f2fd; padding: 12px; border-radius: 6px; border-left: 4px solid #2196f3; margin-bottom: 15px;">
                    <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">📅 Fecha Caducidad</p>
                    <p style="margin: 0; font-size: 14px; font-weight: bold; color: #1565c0;">${fecha_caducidad}</p>
                </div>
                
                ${imagen ? `
                    <div style="background: #fce4ec; padding: 12px; border-radius: 6px; border-left: 4px solid #e91e63; margin-bottom: 15px;">
                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #666;">🖼️ Imagen: <strong>${imagen.name}</strong></p>
                        <div style="width: 100%; max-height: 150px; background: white; border-radius: 4px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <img src="${imagenPreview}" style="max-width: 100%; max-height: 150px; object-fit: contain;" alt="Previsualización">
                        </div>
                    </div>
                ` : ''}
                
                <div style="background: #fff9c4; padding: 12px; border-radius: 6px; border-left: 4px solid #fbc02d;">
                    <p style="margin: 0; font-size: 12px; color: #333;">✅ ¿Deseas continuar con la adición del producto a la base de datos?</p>
                </div>
            </div>
        `;
        
        UI.showDialog({
            title: '✅ Confirmar Agregar Producto',
            icon: '📦',
            message: mensaje,
            buttons: [
                { 
                    text: '❌ Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: '✅ Agregar Producto', 
                    onClick: async () => {
                        await enviarProductoAPI(producto);
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('Error:', error);
        UI.showDialog({
            title: '❌ Error',
            icon: '⚠️',
            message: 'Error al procesar el producto: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Enviar producto a la API
 */
async function enviarProductoAPI(producto) {
    try {
        console.log('📤 Enviando producto a API...', producto);
        
        // Mostrar overlay de carga
        UI.showLoader('Agregando ' + producto.nombre + '...');
        
        console.log('📤 Datos enviados al servidor (ProductosAPI.create):', JSON.stringify(producto));
        const resultado = await ProductosAPI.create(producto);
        
        console.log('📥 Respuesta del servidor:', resultado);
        
        if (resultado && resultado.success) {
            console.log('✅ Producto agregado exitosamente');
            UI.hideLoader();
            // Re-enable add button (previene bloqueo si el usuario navega rápido)
            try { const btnProd = document.getElementById('btn-agregar'); if (btnProd) btnProd.disabled = false; } catch(e){ safeWarn('reenable btn-agregar failed', e); }
                // Reset form, reload and show notification
            document.getElementById('formulario-inventario')?.reset();
            await cargarProductos();

            // Mostrar diálogo de éxito no intrusivo con auto-close y opción de ver la lista
            UI.showDialog({
                type: 'success',
                title: 'Producto agregado',
                message: `El producto <strong>${producto.nombre}</strong> se agregó correctamente.`,
                html: `El producto <strong>${producto.nombre}</strong> se agregó correctamente.`,
                autoClose: 3500,
                buttons: [
                    { text: 'Ver productos', class: 'btn primary', onClick: ()=>{ UI.closeDialog(); UI.scrollToSection('productos'); } },
                    { text: 'Cerrar', class: 'btn', action: 'close' }
                ]
            });

            // Toast adicional con acción rápida
            // Mostrar únicamente el toast ya que el diálogo de éxito ya se mostró arriba
            UI.showSuccess(`Producto <strong>${producto.nombre}</strong> agregado`, { mode: 'toast', actionLabel: 'Ver', actionCallback: async () => { UI.scrollToSection('productos'); } });
        } else {
            console.error('❌ Error en la respuesta:', resultado);
            UI.hideLoader();
                    try { const btnProd = document.getElementById('btn-agregar'); if (btnProd) btnProd.disabled = false; } catch(e){ safeWarn('reenable btn-agregar failed', e); }

            // Manejo especial para respuestas con JSON inválido: intentar extraer JSON embebido o mostrar raw
            if (resultado && resultado.error && resultado.error.indexOf('Invalid JSON') !== -1) {
                // Intentar extraer JSON substring del raw si existe
                const raw = resultado.raw || '';
                let extracted = null;
                try {
                    const m = raw.match(/(\{[\s\S]*\}|\[[\s\S]*\])/);
                    if (m) extracted = JSON.parse(m[0]);
                } catch (err) {
                    console.warn('No se pudo parsear JSON embebido:', err);
                }

                if (extracted && extracted.success) {
                    // Si por suerte contiene un success verdadero, continuar
                    console.log('✅ Respuesta embebida parseada satisfactoriamente:', extracted);
                    try { const btnProd = document.getElementById('btn-agregar'); if (btnProd) btnProd.disabled = false; } catch(e){ safeWarn('reenable btn-agregar failed', e); }
                    UI.success('Producto agregado (respuesta parseada)');
                    await cargarProductos();
                    return;
                }

                // Mostrar diálogo con opción de ver RAW para debug
                UI.showDialog({
                    title: '❌ Error: Respuesta no válida',
                    icon: '⚠️',
                    message: `El servidor devolvió una respuesta no válida (Invalid JSON). Preview:<br><pre style="max-height:220px;overflow:auto;white-space:pre-wrap;background:#111;color:#fff;padding:8px;border-radius:6px;">${(raw || '').replace(/</g,'&lt;')}</pre><br>Revisa los logs del servidor.`,
                    buttons: [{ text: 'OK', action: 'close' }]
                });
                return;
            }

            // Mostrar error habitual
            let mensajeError = resultado?.error || 'No se pudo agregar el producto';
            if (resultado?.errors && Array.isArray(resultado.errors)) {
                mensajeError = resultado.errors.map(err => `• ${err}`).join('<br>');
                console.error('Errores de validación:', resultado.errors);
            }
            UI.showDialog({
                title: '❌ Error al Agregar Producto',
                icon: '⚠️',
                message: mensajeError,
                buttons: [{ text: 'OK', action: 'close' }]
            });
        }
    } catch (error) {
        console.error('❌ Error al enviar producto:', error);
        UI.hideLoader();
        try { const btnProd = document.getElementById('btn-agregar'); if (btnProd) btnProd.disabled = false; } catch(e){ safeWarn('reenable btn-agregar failed', e); }
        UI.showDialog({
            title: '❌ Error',
            icon: '⚠️',
            message: 'Error al procesar la solicitud: ' + (error.message || error),
            buttons: [{ text: 'OK', action: 'close' }]
        });
        console.error('❌ ERROR DEL SISTEMA:', error);
        
        // Cerrar modal de procesamiento
        const modal = document.getElementById('modal-dialogo');
        if (modal) modal.style.display = 'none';
        
        UI.showDialog({
            title: '❌ Error del Sistema',
            icon: '⚠️',
            message: '<strong>Error al comunicarse con el servidor:</strong><br>' + error.message + '<br><br>Revisa la consola del navegador (F12) para más detalles.',
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Editar producto (mostrar modal de edición)
 */
/**
 * Editar producto existente
 */
async function editarProducto(id) {
    try {
        console.log('✏️ Abriendo editor para producto ID:', id);
        
        // Buscar el producto en la lista global
        const productos = window.productos || [];
        const producto = productos.find(p => p.id === id || p.id === parseInt(id));
        
        if (!producto) {
            UI.showDialog({
                title: '❌ Error',
                message: 'No se pudo encontrar el producto',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        console.log('📦 Producto encontrado:', producto);
        
        // Obtener lista de categorías para el select
        let categorias = [];
        try {
            const res = await CategoriasAPI.getAll();
            if (Array.isArray(res)) categorias = res;
            else if (res && res.success === true && Array.isArray(res.data)) categorias = res.data;
            else categorias = [];
        } catch (error) {
            console.warn('⚠️ Error cargando categorías:', error);
            categorias = [];
        }
        
        // Crear HTML del diálogo de edición
        const categoriasHTML = (categorias || []).map(cat => 
            `<option value="${cat.id}" ${producto.categoria_id === cat.id ? 'selected' : ''}>${cat.nombre}</option>`
        ).join('');
        
        const htmlContent = `
            <div style="max-height: 500px; overflow-y: auto;">
                <div class="form-group">
                    <label for="edit-codigo">Código</label>
                    <input type="text" id="edit-codigo" value="${producto.codigo || ''}" placeholder="Ej: PRD001">
                </div>
                
                <div class="form-group">
                    <label for="edit-nombre">Nombre *</label>
                    <input type="text" id="edit-nombre" value="${producto.nombre || ''}" placeholder="Nombre del producto" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-categoria">Categoría *</label>
                    <select id="edit-categoria" required>
                        <option value="">-- Selecciona una categoría --</option>
                        ${categoriasHTML}
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-cantidad">Cantidad</label>
                    <input type="number" id="edit-cantidad" value="${producto.cantidad || 0}" min="0">
                </div>
                
                <div class="form-group">
                    <label for="edit-costo">Costo</label>
                    <input type="number" id="edit-costo" value="${producto.costo || 0}" min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="edit-precio">Precio</label>
                    <input type="number" id="edit-precio" value="${producto.precio || 0}" min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="edit-fecha-caducidad">Fecha de Caducidad</label>
                    <input type="date" id="edit-fecha-caducidad" value="${producto.fecha_caducidad || ''}">
                </div>
                <div class="form-group">
                    <label for="edit-imagen">Imagen (opcional)</label>
                    <input type="file" id="edit-imagen" accept="image/*">
                    ${producto.imagen ? `<div style="margin-top:6px;font-size:12px;color:#666;">Actual: ${producto.imagen}</div>` : ''}
                </div>
            </div>
        `;
        
        // Mostrar diálogo
        let __isSaving = false;
        UI.showDialog({
            title: '✏️ Editar Producto',
            icon: '📝',
            message: htmlContent,
            buttons: [
                { 
                    text: 'Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: 'Guardar Cambios', 
                    onClick: async () => {
                        // Guardar referencia al modal y evitar envíos duplicados a nivel modal
                        const modalEl = document.getElementById('modal-dialogo');
                        if (modalEl && modalEl.dataset && modalEl.dataset.saving === '1') {
                            console.warn('Guardado duplicado detenido por dataset.saving');
                            try { if (window.UI && typeof window.UI._sendTelemetry === 'function') window.UI._sendTelemetry({ event: 'duplicate_save_prevented', entity: 'producto', id: id, ts: new Date().toISOString() }); } catch(e){ safeWarn('telemetry send failed', e); }
                            try { if (window.UI && typeof window.UI.toast === 'function') window.UI.toast('Guardado ya en curso', 'info', 1600); } catch(e){ safeWarn('UI.toast (duplicate save) failed', e); }
                            return;
                        }
                        if (modalEl) modalEl.dataset.saving = '1';

                        // Disable the save button visually and show spinner
                        let guardBtn = null;
                        let guardBtnOriginalHtml = null;
                        try { 
                            guardBtn = modalEl && Array.from(modalEl.querySelectorAll('button')).find(b => (b.textContent||'').trim().toLowerCase().includes('guardar') || b.classList.contains('primary'));
                            if (guardBtn) {
                                guardBtnOriginalHtml = guardBtn.innerHTML;
                                guardBtn.disabled = true;
                                guardBtn.classList.add('is-loading');
                                guardBtn.setAttribute('aria-busy','true');
                                guardBtn.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + guardBtnOriginalHtml;
                            }
                        } catch(e){ safeWarn('prepare guardBtn failed', e); }

                        // Prevent duplicate submissions while saving (closure-level)
                            if (typeof __isSaving !== 'undefined' && __isSaving) {
                            try { if (window.UI && typeof window.UI._sendTelemetry === 'function') window.UI._sendTelemetry({ event: 'duplicate_save_prevented', entity: 'producto', id: id, ts: new Date().toISOString() }); } catch(e){ safeWarn('telemetry send failed', e); }
                            if (modalEl) { delete modalEl.dataset.saving; }
                            if (guardBtn) { try { guardBtn.disabled = false; guardBtn.classList.remove('is-loading'); guardBtn.removeAttribute('aria-busy'); if (guardBtnOriginalHtml !== null) guardBtn.innerHTML = guardBtnOriginalHtml; } catch(e){ safeWarn('restore guardBtn failed', e); } }
                            try { if (window.UI && typeof window.UI.toast === 'function') UI.toast('Guardado ya en curso', 'info', 1600); } catch(e){ safeWarn('UI.toast (guard) failed', e); }
                            return;
                        }
                        __isSaving = true; 

                        // Obtener valores actualizados
                        const nombreActualizado = document.getElementById('edit-nombre')?.value?.trim();
                        const categoriaActualizada = document.getElementById('edit-categoria')?.value;
                        const cantidadActualizada = parseInt(document.getElementById('edit-cantidad')?.value || 0);
                        let costoActualizado = parseFloat(document.getElementById('edit-costo')?.value);
                        if (Number.isNaN(costoActualizado)) costoActualizado = undefined;
                        let precioActualizado = parseFloat(document.getElementById('edit-precio')?.value);
                        if (Number.isNaN(precioActualizado)) precioActualizado = undefined;
                        const fechaCaducidadActualizada = document.getElementById('edit-fecha-caducidad')?.value;
                        const codigoActualizado = document.getElementById('edit-codigo')?.value?.trim();

                        // Validar campos requeridos
                        if (!nombreActualizado) {
                            UI.showDialog({
                                title: '⚠️ Campo Requerido',
                                message: 'Por favor ingresa el <strong>Nombre del Producto</strong>',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            __isSaving = false;
                            if (modalEl) delete modalEl.dataset.saving;
                            if (guardBtn) guardBtn.disabled = false;
                            return;
                        }

                        if (!categoriaActualizada) {
                            UI.showDialog({
                                title: '⚠️ Campo Requerido',
                                message: 'Por favor selecciona una <strong>Categoría</strong>',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            __isSaving = false;
                            if (modalEl) delete modalEl.dataset.saving;
                            if (guardBtn) guardBtn.disabled = false;
                            return;
                        }

                        try {
                            // If user selected a new image in the edit modal, upload it first
                            let uploadedImagenFilename = null;
                            try {
                                const imagenFile = document.getElementById('edit-imagen')?.files?.[0];
                                if (imagenFile) {
                                    UI.showLoader('Subiendo imagen...');
                                    const fd = new FormData();
                                    fd.append('file', imagenFile);
                                    const uploadEndpoint = (window.CONFIG && window.CONFIG.apiBase) ? (window.CONFIG.apiBase.replace(/\/$/,'') + '/upload.php') : 'api/upload.php';
                                    const upRes = await fetch(uploadEndpoint, { method: 'POST', body: fd });
                                    let upJson = null;
                                    try { upJson = await upRes.json(); } catch(e) { upJson = null; }
                                    UI.hideLoader();
                                    if (!upRes.ok || !upJson || upJson.success !== true || !upJson.filename) {
                                        UI.showDialog({ title: '❌ Error', message: 'No se pudo subir la imagen: ' + (upJson && upJson.error ? upJson.error : ('HTTP ' + (upRes.status || '??'))), buttons: [{ text: 'OK', action: 'close' }] });
                                        __isSaving = false;
                                        if (modalEl) delete modalEl.dataset.saving;
                                        if (guardBtn) guardBtn.disabled = false;
                                        return;
                                    }
                                    uploadedImagenFilename = upJson.filename;
                                }
                            } catch (e) {
                                UI.hideLoader();
                                console.error('Error subiendo imagen:', e);
                                UI.showDialog({ title: '❌ Error', message: 'Error al subir la imagen: ' + (e.message || e), buttons: [{ text: 'OK', action: 'close' }] });
                                __isSaving = false;
                                if (modalEl) delete modalEl.dataset.saving;
                                if (guardBtn) guardBtn.disabled = false;
                                return;
                            }

                            // Use original product values as fallback when inputs are empty/undefined.
                            const payload = {
                                codigo: (typeof codigoActualizado !== 'undefined' && codigoActualizado !== '') ? codigoActualizado : (producto.codigo ?? null),
                                nombre: nombreActualizado,
                                categoria_id: parseInt(categoriaActualizada),
                                cantidad: Number.isFinite(cantidadActualizada) ? cantidadActualizada : (producto.cantidad ?? 0),
                                costo: (typeof costoActualizado !== 'undefined') ? costoActualizado : (producto.costo !== undefined ? parseFloat(producto.costo) : null),
                                precio: (typeof precioActualizado !== 'undefined') ? precioActualizado : (producto.precio !== undefined ? parseFloat(producto.precio) : null),
                                fecha_caducidad: (fechaCaducidadActualizada && fechaCaducidadActualizada !== '') ? fechaCaducidadActualizada : (producto.fecha_caducidad ?? null),
                                imagen: (uploadedImagenFilename !== null) ? uploadedImagenFilename : (producto.imagen ?? null)
                            };

                            console.log('📤 Actualizando producto (payload):', { id, ...payload });

                            const attemptUpdate = async () => {
                                const res = await ProductosAPI.update(id, payload);
                                console.log('✅ Respuesta del servidor:', res);
                                return res;
                            };

                            let resultado = await attemptUpdate();

                            if (resultado && resultado.success) {
                                try {
                                    // Close the edit dialog and refresh list, show non-blocking success toast
                                    try { if (typeof UI.closeDialog === 'function') UI.closeDialog(); else if (document.getElementById('modal-dialogo')) document.getElementById('modal-dialogo').style.display = 'none'; } catch(e){ safeWarn('closing modal failed', e); }
                                    // Extra safety: remove modal buttons and dataset to prevent re-opening or duplicate handlers
                                    try {
                                        const modalEl2 = document.getElementById('modal-dialogo');
                                        if (modalEl2) {
                                            const botonesCont = modalEl2.querySelector('#modal-dialogo-botones');
                                            if (botonesCont) botonesCont.innerHTML = '';
                                            try { delete modalEl2.dataset.saving; } catch(e){ safeWarn('delete modal dataset.saving failed', e); }
                                            try { delete modalEl2.dataset.targetType; delete modalEl2.dataset.targetId; } catch(e){ safeWarn('delete modal dataset targets failed', e); }
                                        }
                                    } catch(e) { safeWarn('modal cleanup failed', e); }
                                    try { cargarProductos(); } catch(e){ safeWarn('cargarProductos failed', e); }
                                    try { UI.showSuccess && UI.showSuccess('Producto actualizado correctamente', { mode: 'toast' }); } catch(e) { try { UI.toast && UI.toast('Producto actualizado correctamente', 'success', 3000); } catch(e){ safeWarn('UI.toast fallback failed', e); } }
                                } catch(e) { console.warn('Post-update success handling failed', e); }
                            } else if (resultado && resultado.status === 500 && resultado.error && /lock wait timeout|1205/i.test(resultado.error)) {
                                UI.showDialog({
                                    title: '⚠️ Bloqueo en base de datos',
                                    message: 'No se pudo guardar porque la base de datos reportó un bloqueo. Puedes intentar guardar de nuevo.',
                                    buttons: [
                                        { text: 'Cancelar', action: 'close' },
                                        { text: 'Reintentar', onClick: async () => {
                                            UI.showLoader('Reintentando actualización...');
                                            try {
                                                const retryRes = await attemptUpdate();
                                                UI.hideLoader();
                                                if (retryRes && retryRes.success) {
                                                    UI.showSuccess('Producto actualizado tras reintento', { mode: 'toast' });
                                                    cargarProductos();
                                                    document.getElementById('modal-dialogo').style.display = 'none';
                                                } else {
                                                    UI.showDialog({ title: '❌ Error', message: retryRes?.error || 'No se pudo actualizar tras reintento', buttons: [{ text: 'OK', action: 'close' }] });
                                                }
                                            } catch (err) {
                                                UI.hideLoader();
                                                UI.showDialog({ title:'❌ Error', message: 'Error al reintentar: ' + (err.message || err), buttons:[{text:'OK', action:'close'}] });
                                            }
                                        } }
                                    ]
                                });
                            } else {
                                UI.showDialog({
                                    title: '❌ Error',
                                    message: (resultado && resultado.error) || 'Error al actualizar el producto',
                                    buttons: [{ text: 'OK', action: 'close' }]
                                });
                            }
                        } catch (error) {
                            console.error('❌ Error actualizando producto:', error);
                            UI.showDialog({
                                title: '❌ Error',
                                message: 'Error al actualizar: ' + error.message,
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                        } finally {
                            __isSaving = false;
                            if (modalEl) { delete modalEl.dataset.saving; }
                            if (guardBtn) {
                                try {
                                    guardBtn.disabled = false;
                                    guardBtn.classList.remove('is-loading');
                                    guardBtn.removeAttribute('aria-busy');
                                    if (guardBtnOriginalHtml !== null) guardBtn.innerHTML = guardBtnOriginalHtml;
                                } catch(e) { safeWarn('restore guardBtn failed', e); }
                            }
                        }
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('❌ Error en editarProducto:', error);
        UI.showDialog({
            title: '❌ Error',
            message: 'Error al abrir el editor: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Confirmar eliminación de producto
 */
function confirmarEliminar(tipo, id) {
    const tiposTexto = {
        'producto': { icon: '📦', articulo: 'el producto' },
        'categoria': { icon: '🗂️', articulo: 'la categoría' },
        'cliente': { icon: '👤', articulo: 'el cliente' },
        'proveedor': { icon: '🤝', articulo: 'el proveedor' },
        'venta': { icon: '💳', articulo: 'la venta' },
        'salida': { icon: '📤', articulo: 'la salida' }
    };
    
    const tipoInfo = tiposTexto[tipo] || { icon: '❓', articulo: 'el registro' };
    
    // Etiquetar el modal con el objetivo para que los botones estáticos puedan usar delegación
    try {
        const modalEl = document.getElementById('modal-dialogo');
        if (modalEl) { modalEl.dataset.targetType = tipo; modalEl.dataset.targetId = id; }
    } catch(e) { console.warn('No se pudo establecer dataset en modal', e); }

    UI.showDialog({
        title: '⚠️ Confirmar Eliminación',
        icon: '🗑️',
        message: `¿Estás seguro de que deseas eliminar ${tipoInfo.articulo}?<br><br><strong>Esta acción no se puede deshacer.</strong>`,
        buttons: [
            { 
                text: 'Cancelar', 
                action: 'close',
                type: 'normal'
            },
            {
                text: 'Eliminar', 
                onClick: async () => {
                    await eliminarRegistro(tipo, id);
                },
                type: 'danger'
            }
        ]
    });
}

/**
 * Eliminar registro genérico
 */
async function eliminarRegistro(tipo, id) {
    switch(tipo) {
        case 'producto':
            await eliminarProducto(id);
            break;
        case 'categoria':
            await eliminarCategoria(id);
            break;
        case 'cliente':
            await eliminarCliente(id);
            break;
        case 'proveedor':
            await eliminarProveedor(id);
            break;
        case 'venta':
            await eliminarVenta(id);
            break;
        case 'salida':
            await eliminarSalida(id);
            break;
        default:
            UI.showDialog({
                title: '❌ Error',
                icon: '⚠️',
                message: 'Tipo de registro no reconocido',
                buttons: [{ text: 'OK', action: 'close' }]
            });
    }
}

/**
 * Eliminar producto
 */
if (typeof window.eliminarProducto === 'undefined') {
    window.eliminarProducto = async function eliminarProducto(id) {
        try {
            console.log('🗑️ Eliminando producto', id);
            UI.showLoader('Eliminando producto...');
            const resultado = await ProductosAPI.delete(id);
            UI.hideLoader();
            
            if (resultado && resultado.success) {
                // Feedback visual inmediato en la fila afectada
                try { resaltarFila('producto', id); } catch(e){ safeWarn('resaltarFila (producto) failed', e); }
                UI.showSuccess('Producto eliminado', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { UI.scrollToSection('productos'); } });
                await cargarProductos();
            } else if (resultado && resultado.status === 409) {
                UI.showDialog({
                    title: 'No se puede eliminar',
                    icon: '⚠️',
                    message: resultado.error || 'Existen registros relacionados que impiden eliminar este producto. Revisa Ventas o Salidas relacionadas primero.',
                    buttons: [
                        { text: 'Abrir Salidas', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'salidas'); } },
                        { text: 'Abrir Ventas', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'ventas'); } },
                        { text: 'Cerrar', action: 'close' }
                    ]
                });
            } else {
                UI.showDialog({
                    title: '❌ Error',
                    icon: '⚠️',
                    message: resultado?.error || 'No se pudo eliminar el producto',
                    buttons: [{ text: 'OK', action: 'close' }]
                });
            }
        } catch (error) {
            console.error('Error:', error);
            UI.hideLoader();
            UI.showDialog({ title: '❌ Error', icon: '⚠️', message: 'Error al eliminar: ' + (error.message || error), buttons: [{ text: 'OK', action: 'close' }] });
        }
    };
}

/**
 * Eliminar TODO el inventario (con confirmación triple)
 */
async function confirmarEliminarTodo() {
    // Primera confirmación (simple)
    const ok1 = await UI.confirm({ title: '⚠️ Confirmación', message: '¿Desea REALMENTE eliminar TODO el inventario?' });
    if (!ok1) return;

    // Segunda confirmación (reforzada)
    const ok2 = await UI.confirm({ title: '⚠️⚠️ Última Oportunidad', message: 'ESTA ACCIÓN ES IRREVERSIBLE. ¿Estás completamente seguro?' });
    if (!ok2) return;

    // Confirmación final con input exacto
    const ok3 = await UI.confirm({ title: 'Confirmar texto', message: 'Escribe <strong>ELIMINAR TODO</strong> para confirmar<br><small>Se eliminarán <strong>todos</strong> los productos y las salidas relacionadas.</small>', input: true, inputPlaceholder: 'ELIMINAR TODO', confirmValue: 'ELIMINAR TODO', okText: 'Eliminar', cancelText: 'Cancelar' });
    if (!ok3) {
        UI.toast('Cancelado. Texto incorrecto.', 'warn', 3000);
        return;
    }

    try {
        UI.showLoader('Eliminando todo el inventario...');
        console.log('🔥 Eliminando TODO el inventario');
        const resultado = await ProductosAPI.delete('all');
        UI.hideLoader();

        if (resultado && resultado.success) {
            UI.showSuccess('Inventario eliminado', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarProductos(); UI.scrollToSection('productos'); } });
            await cargarProductos();
        } else {
            UI.showDialog({ title: '❌ Error', icon: '⚠️', message: resultado?.error || 'Error desconocido', buttons: [{ text: 'OK', action: 'close' }] });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.hideLoader();
        UI.showDialog({ title: '❌ Error', icon: '⚠️', message: 'Error: ' + (error.message || error), buttons: [{ text: 'OK', action: 'close' }] });
    }
}

// ============================================================================
// CATEGORÍAS - CRUD OPERATIONS
// ============================================================================

/**
 * Cargar categorías
 */
async function cargarCategorias() {
    try {
        let res;
        try { res = await apiCallWithTimeout(() => CategoriasAPI.getAll(), 7000); } catch (error) { if (error && error.message === 'timeout') { console.warn('CategoriasAPI timeout'); UI.toast && UI.toast('Timeout cargando categorías', 'warn', 5000); return; } throw error; }
        const categorias = Array.isArray(res) ? res : (res && res.data ? res.data : []);
        if (res && res.success === false) {
            UI.handleAPIError(res, { title: 'Error cargando categorías' });
            return;
        }
        
        // Llenar select de productos
        const select = document.getElementById('categoria-producto');
        if (select) {
            select.innerHTML = '<option value="" disabled selected>Seleccione una categoría...</option>';
            categorias.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.nombre;
                select.appendChild(option);
            });
        }
        
        // Mostrar en tabla
        mostrarCategorias(categorias);
    } catch (error) {
        console.error('Error cargando categorías:', error);
    }
}

/**
 * Mostrar categorías en tabla
 */
function mostrarCategorias(categorias = []) {
    // Guardar en variable global para referencia
    window.categorias = categorias;
    
    const tbody = document.getElementById('tabla-categorias');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (categorias.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#999;">Sin categorías registradas</td></tr>';
        return;
    }
    
    categorias.forEach(cat => {
        const row = document.createElement('tr');
        const tdName = document.createElement('td'); tdName.textContent = cat.nombre || 'N/A'; row.appendChild(tdName);
        const tdCreated = document.createElement('td'); tdCreated.textContent = cat.created_at ? new Date(cat.created_at).toLocaleDateString('es-MX') : 'N/A'; row.appendChild(tdCreated);
        const tdActions = document.createElement('td');

        const btnEdit = document.createElement('button');
        btnEdit.className = 'btn-editar';
        btnEdit.title = 'Editar';
        btnEdit.addEventListener('click', function(e){ e.preventDefault(); try { editarCategoria(cat.id); } catch(err){ safeWarn('editarCategoria click', err); } });
        const iconEdit = document.createElement('i'); iconEdit.className = 'fas fa-edit'; btnEdit.appendChild(iconEdit);
        tdActions.appendChild(btnEdit);

        const btnDel = document.createElement('button');
        btnDel.className = 'btn-eliminar';
        btnDel.setAttribute('data-type','categoria');
        btnDel.setAttribute('data-id', String(cat.id));
        btnDel.setAttribute('aria-label', 'Eliminar categoría ' + (cat.nombre || ''));
        btnDel.title = 'Eliminar';
        btnDel.innerHTML = '<i class="fas fa-trash"></i>';
        tdActions.appendChild(btnDel);

        row.appendChild(tdActions);
        tbody.appendChild(row);
    });
}

// API ejemplo eliminado: funciones relacionadas removidas

/**
 * Agregar categoría
 */
async function agregarCategoria() {
    try {
        console.log('🔵 Iniciando: agregarCategoria()');
        
        // Deshabilitar botón para evitar clics múltiples (obtener por selector para ser robusto)
        const btn = document.querySelector('#formulario-categorias .btn-agregar') || null;
        if (btn) btn.disabled = true;
        
        const inputElement = document.getElementById('nueva-categoria');
        if (!inputElement) {
            console.error('❌ Elemento "nueva-categoria" no encontrado');
            UI.alertError('Error: Campo de categoría no encontrado');
            if (btn) btn.disabled = false;
            return;
        }
        
        const nombre = inputElement.value?.trim();
        console.log('📝 Nombre ingresado:', nombre || '(vacío)');
        
        if (!nombre) {
            console.warn('❌ Campo vacío');
            if (btn) btn.disabled = false;
            try { const el = document.getElementById('nueva-categoria'); if (typeof setFieldError === 'function') setFieldError(el,'Por favor ingresa el nombre de la categoría'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError nueva-categoria failed', e); }
            return;
        }
        
        if (nombre.length > 100) {
            console.warn('❌ Nombre muy largo:', nombre.length);
            if (btn) btn.disabled = false;
            try { const el = document.getElementById('nueva-categoria'); if (typeof setFieldError === 'function') setFieldError(el,'El nombre no puede exceder 100 caracteres'); if (el) el.focus(); } catch(e){ safeWarn('setFieldError nueva-categoria length failed', e); }
            return;
        }
        
        console.log('✅ Validación pasada');
        // Frontend duplicate check using cached categories (if loaded)
        try {
            const existing = (window.categorias || []).find(c => (c.nombre || '').toLowerCase() === nombre.toLowerCase());
            if (existing) {
                UI.showDialog({ title: '⚠️ Categoría duplicada', icon: '🚫', message: `Ya existe la categoría: <strong>${existing.nombre}</strong>`, buttons: [{ text: 'OK', action: 'close' }] });
                if (btn) btn.disabled = false;
                return;
            }
        } catch (e) { console.warn('categoria pre-check failed', e); }

        // Mostrar confirmación
        UI.showDialog({
            title: '✅ Confirmar Nueva Categoría',
            icon: '🗂️',
            message: `¿Deseas crear la categoría:<br><br><strong>"${nombre}"</strong>?`,
            buttons: [
                { text: 'Cancelar', action: 'close', type: 'normal' },
                { 
                    text: 'Crear Categoría', 
                    onClick: async () => {
                        console.log('🔘 Botón "Crear Categoría" presionado');
                        await crearCategoriaAPI(nombre);
                        // Re-habilitar botón después de terminar
                        if (btn) btn.disabled = false;
                    },
                    type: 'success'
                }
            ]
        });
    } catch (error) {
        console.error('❌ Error en agregarCategoria():', error);
        const btn = event?.target;
        if (btn) btn.disabled = false;
        UI.showDialog({
            title: '❌ Error',
            icon: '⚠️',
            message: 'Error al procesar la categoría: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Crear categoría en la API
 */
async function crearCategoriaAPI(nombre) {
    try {
        console.log('📤 Creando categoría:', nombre);
        UI.showLoader('Creando categoría...');
        
        const resultado = await CategoriasAPI.create({ nombre });
        console.log('📥 Respuesta del servidor:', resultado);
        UI.hideLoader();
        if (resultado && resultado.success) {
            console.log('✅ Categoría creada exitosamente');
            
            // Limpiar campo del formulario
            try { const el = $id('nueva-categoria'); if (el) el.value = ''; } catch(e){ safeWarn('clear nueva-categoria failed', e); }
            
            // Recargar categorías
            await cargarCategorias();
            try { const btn = document.querySelector('#formulario-categorias .btn-agregar'); if (btn) btn.disabled = false; } catch(e){ safeWarn('reenable categoria btn failed', e); }
            
            // Mostrar éxito
            UI.showDialog({
                title: '✅ ¡Éxito!',
                icon: '🎉',
                message: `La categoría <strong>"${nombre}"</strong> ha sido creada correctamente.`,
                buttons: [{ 
                    text: 'OK', 
                    action: 'close'
                }]
            });
        } else {
            console.error('❌ Error en respuesta:', resultado);
            try { const btn = document.querySelector('#formulario-categorias .btn-agregar'); if (btn) btn.disabled = false; } catch(e){ safeWarn('reenable categoria btn failed', e); }
            UI.showDialog({
                title: '❌ Error',
                icon: '⚠️',
                message: resultado?.error || 'No se pudo crear la categoría',
                buttons: [{ text: 'OK', action: 'close' }]
            });
        }
    } catch (error) {
        console.error('❌ Error del sistema:', error);
        UI.hideLoader();
        try { const btn = document.querySelector('#formulario-categorias .btn-agregar'); if (btn) btn.disabled = false; } catch(e){ safeWarn('reenable categoria btn failed', e); }
        UI.showDialog({
            title: '❌ Error del Sistema',
            icon: '⚠️',
            message: 'Error al comunicarse con el servidor: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Editar categoría
 */
async function editarCategoria(id) {
    try {
        console.log('✏️ Editando categoría ID:', id);
        
        // Buscar la categoría en la lista global
        const categorias = window.categorias || [];
        const categoria = categorias.find(c => c.id === id || c.id === parseInt(id));
        
        if (!categoria) {
            UI.showDialog({
                title: '❌ Error',
                message: 'No se pudo encontrar la categoría',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        console.log('🗂️ Categoría encontrada:', categoria);
        
        const htmlContent = `
            <div class="form-group">
                <label for="edit-nombre-categoria">Nombre de la Categoría *</label>
                <input type="text" id="edit-nombre-categoria" value="${categoria.nombre || ''}" placeholder="Nombre de la categoría" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
        `;
        
        UI.showDialog({
            title: '✏️ Editar Categoría',
            icon: '🗂️',
            message: htmlContent,
            buttons: [
                { 
                    text: 'Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: 'Guardar Cambios', 
                    onClick: async () => {
                        const nombreActualizado = document.getElementById('edit-nombre-categoria')?.value?.trim();
                        
                        if (!nombreActualizado) {
                            UI.showDialog({
                                title: '⚠️ Campo Requerido',
                                message: 'Por favor ingresa el nombre de la categoría',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        if (nombreActualizado.length > 100) {
                            UI.showDialog({
                                title: '⚠️ Nombre Muy Largo',
                                message: 'El nombre no puede exceder 100 caracteres',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        try {
                            console.log('📤 Actualizando categoría:', nombreActualizado);
                            
                            const resultado = await CategoriasAPI.update(id, { nombre: nombreActualizado });
                            
                            if (resultado.success) {
                                UI.showDialog({
                                    title: '✅ Éxito',
                                    message: 'Categoría actualizada correctamente',
                                    buttons: [{ 
                                        text: 'OK', 
                                        onClick: () => {
                                            cargarCategorias();
                                            document.getElementById('modal-dialogo').style.display = 'none';
                                        }
                                    }]
                                });
                            } else {
                                UI.showDialog({
                                    title: '❌ Error',
                                    message: resultado.error || 'Error al actualizar la categoría',
                                    buttons: [{ text: 'OK', action: 'close' }]
                                });
                            }
                        } catch (error) {
                            console.error('❌ Error actualizando:', error);
                            UI.showDialog({
                                title: '❌ Error',
                                message: 'Error: ' + error.message,
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                        }
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('❌ Error en editarCategoria:', error);
        UI.showDialog({
            title: '❌ Error',
            message: 'Error: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Eliminar categoría
 */
async function eliminarCategoria(id) {
    try {
        UI.showLoader('Eliminando categoría...');
        const resultado = await CategoriasAPI.delete(id);
        UI.hideLoader();
        
        if (resultado && resultado.success) {
            try { resaltarFila('categoria', id); } catch(e){ safeWarn('resaltarFila (categoria) failed', e); }
            UI.showSuccess('Categoría eliminada', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarCategorias(); UI.scrollToSection('categorias'); } });
            await cargarCategorias();
        } else if (resultado && resultado.status === 409) {
            UI.showDialog({ title: 'No se puede eliminar', icon: '⚠️', message: resultado.error || 'Existen productos relacionados. Abra Productos para revisar.', buttons: [ { text: 'Abrir Productos', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'productos'); } }, { text: 'Cerrar', action: 'close' } ] });
        } else {
            UI.showDialog({ title: '❌ Error', icon: '⚠️', message: resultado?.error || 'No se pudo eliminar la categoría', buttons: [{ text: 'OK', action: 'close' }] });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.hideLoader();
        UI.showDialog({ title: '❌ Error del Sistema', icon: '⚠️', message: 'Error al eliminar: ' + (error.message || error), buttons: [{ text: 'OK', action: 'close' }] });
    }
}

// ============================================================================
// CLIENTES - CRUD OPERATIONS
// ============================================================================

/**
 * Cargar clientes
 */
async function cargarClientes() {
    try {
        const res = await ClientesAPI.getAll();
        let clientes = [];
        if (Array.isArray(res)) clientes = res;
        else if (res && res.success === true && Array.isArray(res.data)) clientes = res.data;
        else {
            console.error('Error: ClientesAPI no retornó un array', res);
            UI.handleAPIError(res, { title: 'Error cargando clientes' });
            return;
        }
        mostrarClientes(clientes);
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al cargar clientes: ' + (error && error.message || error));
    }
}

/**
 * Mostrar clientes
 */
function mostrarClientes(clientes = []) {
    // Guardar en variable global
    window.clientes = clientes;
    
    const tbody = document.getElementById('tabla-clientes');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (clientes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">Sin clientes registrados</td></tr>';
        return;
    }
    
    clientes.forEach(cli => {
        const row = document.createElement('tr');
        const tdName = document.createElement('td'); tdName.textContent = cli.nombre || 'N/A'; row.appendChild(tdName);
        const tdEmail = document.createElement('td'); tdEmail.textContent = cli.email || 'N/A'; row.appendChild(tdEmail);
        const tdTel = document.createElement('td'); tdTel.textContent = cli.telefono || 'N/A'; row.appendChild(tdTel);
        const tdCreated = document.createElement('td'); tdCreated.textContent = cli.created_at ? new Date(cli.created_at).toLocaleDateString('es-MX') : 'N/A'; row.appendChild(tdCreated);
        const tdActions = document.createElement('td');

        const btnEdit = document.createElement('button'); btnEdit.className = 'btn-editar'; btnEdit.title = 'Editar';
        btnEdit.addEventListener('click', function(e){ e.preventDefault(); try{ editarCliente(cli.id); } catch(err){ safeWarn('editarCliente click', err); } });
        const iconEdit = document.createElement('i'); iconEdit.className = 'fas fa-edit'; btnEdit.appendChild(iconEdit);
        tdActions.appendChild(btnEdit);

        const btnDel = document.createElement('button'); btnDel.className = 'btn-eliminar'; btnDel.setAttribute('data-type','cliente'); btnDel.setAttribute('data-id', String(cli.id)); btnDel.setAttribute('aria-label','Eliminar cliente ' + (cli.nombre||'')); btnDel.title='Eliminar'; btnDel.innerHTML = '<i class="fas fa-trash"></i>';
        tdActions.appendChild(btnDel);

        row.appendChild(tdActions);
        tbody.appendChild(row);
    });
}

/**
 * Agregar cliente
 */
async function agregarClienteForm(event) {
    event.preventDefault();
    
    try {
        const nombre = document.getElementById('cliente-nombre')?.value?.trim();
        const email = document.getElementById('cliente-email')?.value?.trim();
        const telefono = document.getElementById('cliente-telefono')?.value?.trim();
        
        // Validar campos requeridos
        if (!nombre || !email) {
            UI.showDialog({
                title: '⚠️ Campos Requeridos',
                icon: '❌',
                message: 'Por favor completa los campos requeridos:<br><strong>• Nombre<br>• Email</strong>',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        // Validar email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            UI.showDialog({
                title: '⚠️ Email Inválido',
                icon: '❌',
                message: 'Por favor ingresa un email válido',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        // Mostrar confirmación
        UI.showDialog({
            title: '✅ Confirmar Nuevo Cliente',
            icon: '👤',
            message: `<strong>Resumen del Cliente:</strong><br>
                <br>👤 Nombre: <strong>${nombre}</strong>
                <br>📧 Email: <strong>${email}</strong>
                ${telefono ? `<br>📞 Teléfono: <strong>${telefono}</strong>` : ''}
                <br><br>¿Deseas continuar?`,
            buttons: [
                { text: 'Cancelar', action: 'close', type: 'normal' },
                { 
                    text: 'Agregar Cliente', 
                    onClick: async () => {
                        await crearClienteAPI(nombre, email, telefono);
                    },
                    type: 'success'
                }
            ]
        });
    } catch (error) {
        console.error('Error:', error);
        UI.showDialog({
            title: '❌ Error',
            icon: '⚠️',
            message: 'Error al procesar el cliente: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Crear cliente en la API
 */
async function crearClienteAPI(nombre, email, telefono) {
    try {
        const resultado = await ClientesAPI.create({ nombre, email, telefono });
        
        if (resultado.success) {
            const modal = document.getElementById('modal-dialogo');
            if (modal) modal.style.display = 'none';
            
            UI.showDialog({
                title: '✅ ¡Éxito!',
                icon: '🎉',
                message: `El cliente <strong>"${nombre}"</strong> ha sido agregado correctamente.`,
                buttons: [{ 
                    text: 'OK', 
                    onClick: async () => {
                        document.getElementById('formulario-clientes')?.reset();
                        await cargarClientes();
                    }
                }]
            });
        } else {
            const modal = document.getElementById('modal-dialogo');
            if (modal) modal.style.display = 'none';
            
            UI.showDialog({
                title: '❌ Error',
                icon: '⚠️',
                message: resultado.error || 'No se pudo agregar el cliente',
                buttons: [{ text: 'OK', action: 'close' }]
            });
        }
    } catch (error) {
        console.error('Error:', error);
        
        const modal = document.getElementById('modal-dialogo');
        if (modal) modal.style.display = 'none';
        
        UI.showDialog({
            title: '❌ Error del Sistema',
            icon: '⚠️',
            message: 'Error al comunicarse con el servidor: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Editar cliente
 */
async function editarCliente(id) {
    try {
        console.log('✏️ Editando cliente ID:', id);
        
        // Buscar el cliente en la lista global
        const clientes = window.clientes || [];
        const cliente = clientes.find(c => c.id === id || c.id === parseInt(id));
        
        if (!cliente) {
            UI.showDialog({
                title: '❌ Error',
                message: 'No se pudo encontrar el cliente',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        console.log('👤 Cliente encontrado:', cliente);
        
        const htmlContent = `
            <div style="max-height: 400px; overflow-y: auto;">
                <div class="form-group">
                    <label for="edit-cliente-nombre">Nombre *</label>
                    <input type="text" id="edit-cliente-nombre" value="${cliente.nombre || ''}" placeholder="Nombre del cliente" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="edit-cliente-email">Email</label>
                    <input type="email" id="edit-cliente-email" value="${cliente.email || ''}" placeholder="correo@ejemplo.com" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="edit-cliente-telefono">Teléfono</label>
                    <input type="tel" id="edit-cliente-telefono" value="${cliente.telefono || ''}" placeholder="+1 (555) 000-0000" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>
        `;
        
        UI.showDialog({
            title: '✏️ Editar Cliente',
            icon: '👤',
            message: htmlContent,
            buttons: [
                { 
                    text: 'Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: 'Guardar Cambios', 
                    onClick: async () => {
                        const nombreActualizado = document.getElementById('edit-cliente-nombre')?.value?.trim();
                        const emailActualizado = document.getElementById('edit-cliente-email')?.value?.trim();
                        const telefonoActualizado = document.getElementById('edit-cliente-telefono')?.value?.trim();
                        
                        if (!nombreActualizado) {
                            UI.showDialog({
                                title: '⚠️ Campo Requerido',
                                message: 'Por favor ingresa el nombre del cliente',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        try {
                            console.log('📤 Actualizando cliente:', {
                                nombre: nombreActualizado,
                                email: emailActualizado,
                                telefono: telefonoActualizado
                            });
                            
                            const resultado = await ClientesAPI.update(id, {
                                nombre: nombreActualizado,
                                email: emailActualizado,
                                telefono: telefonoActualizado
                            });
                            
                            if (resultado.success) {
                                UI.showDialog({
                                    title: '✅ Éxito',
                                    message: 'Cliente actualizado correctamente',
                                    buttons: [{ 
                                        text: 'OK', 
                                        onClick: () => {
                                            cargarClientes();
                                            document.getElementById('modal-dialogo').style.display = 'none';
                                        }
                                    }]
                                });
                            } else {
                                UI.showDialog({
                                    title: '❌ Error',
                                    message: resultado.error || 'Error al actualizar el cliente',
                                    buttons: [{ text: 'OK', action: 'close' }]
                                });
                            }
                        } catch (error) {
                            console.error('❌ Error actualizando:', error);
                            UI.showDialog({
                                title: '❌ Error',
                                message: 'Error: ' + error.message,
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                        }
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('❌ Error en editarCliente:', error);
        UI.showDialog({
            title: '❌ Error',
            message: 'Error: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Eliminar cliente
 */
async function eliminarCliente(id) {
    try {
        UI.showLoader('Eliminando cliente...');
        const resultado = await ClientesAPI.delete(id);
        UI.hideLoader();
        
        if (resultado && resultado.success) {
            try { resaltarFila('cliente', id); } catch(e){ safeWarn('resaltarFila (cliente) failed', e); }
            UI.showSuccess('Cliente eliminado', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarClientes(); UI.scrollToSection('clientes'); } });
            await cargarClientes();
        } else if (resultado && resultado.status === 409) {
            UI.showDialog({ title: 'No se puede eliminar', icon: '⚠️', message: resultado.error || 'Existen ventas relacionadas con este cliente. Abra Ventas para revisar.', buttons: [ { text: 'Abrir Ventas', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'ventas'); } }, { text: 'Cerrar', action: 'close' } ] });
        } else {
            UI.showDialog({ title: '❌ Error', icon: '⚠️', message: resultado?.error || 'No se pudo eliminar el cliente', buttons: [{ text: 'OK', action: 'close' }] });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.hideLoader();
        UI.showDialog({ title: '❌ Error del Sistema', icon: '⚠️', message: 'Error al eliminar: ' + (error.message || error), buttons: [{ text: 'OK', action: 'close' }] });
    }
}

// ============================================================================
// PROVEEDORES - CRUD OPERATIONS
// ============================================================================

/**
 * Cargar proveedores
 */
async function cargarProveedores() {
    try {
        let res;
        try { res = await apiCallWithTimeout(() => ProveedoresAPI.getAll(), 7000); } catch (error) { if (error && error.message === 'timeout') { console.warn('ProveedoresAPI timeout'); UI.toast && UI.toast('Timeout cargando proveedores', 'warn', 5000); return; } throw error; }
        let proveedores = [];
        if (Array.isArray(res)) proveedores = res;
        else if (res && res.success === true && Array.isArray(res.data)) proveedores = res.data;
        else {
            console.error('Error: ProveedoresAPI no retornó un array', res);
            UI.handleAPIError(res, { title: 'Error cargando proveedores' });
            return;
        }
        mostrarProveedores(proveedores);
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al cargar proveedores: ' + (error && error.message || error));
    }
}

/**
 * Mostrar proveedores
 */
function mostrarProveedores(proveedores = []) {
    // Guardar en variable global
    window.proveedores = proveedores;
    
    const tbody = document.getElementById('tabla-proveedores');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (proveedores.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">Sin proveedores registrados</td></tr>';
        return;
    }
    
    proveedores.forEach(prov => {
        const row = document.createElement('tr');
        const tdName = document.createElement('td'); tdName.textContent = prov.nombre || 'N/A'; row.appendChild(tdName);
        const tdEmail = document.createElement('td'); tdEmail.textContent = prov.email || 'N/A'; row.appendChild(tdEmail);
        const tdTel = document.createElement('td'); tdTel.textContent = prov.telefono || 'N/A'; row.appendChild(tdTel);
        const tdCreated = document.createElement('td'); tdCreated.textContent = prov.created_at ? new Date(prov.created_at).toLocaleDateString('es-MX') : 'N/A'; row.appendChild(tdCreated);
        const tdActions = document.createElement('td');

        const btnEdit = document.createElement('button'); btnEdit.className = 'btn-editar'; btnEdit.title = 'Editar';
        btnEdit.addEventListener('click', function(e){ e.preventDefault(); try{ editarProveedor(prov.id); } catch(err){ safeWarn('editarProveedor click', err); } });
        const iconEdit = document.createElement('i'); iconEdit.className = 'fas fa-edit'; btnEdit.appendChild(iconEdit);
        tdActions.appendChild(btnEdit);

        const btnDel = document.createElement('button'); btnDel.className = 'btn-eliminar'; btnDel.setAttribute('data-type','proveedor'); btnDel.setAttribute('data-id', String(prov.id)); btnDel.setAttribute('aria-label','Eliminar proveedor ' + (prov.nombre||'')); btnDel.title='Eliminar'; btnDel.innerHTML = '<i class="fas fa-trash"></i>';
        tdActions.appendChild(btnDel);

        row.appendChild(tdActions);
        tbody.appendChild(row);
    });
}

/**
 * Agregar proveedor
 */
async function agregarProveedorForm(event) {
    event.preventDefault();
    
    try {
        const nombre = document.getElementById('proveedor-nombre')?.value?.trim();
        const email = document.getElementById('proveedor-email')?.value?.trim();
        const telefono = document.getElementById('proveedor-telefono')?.value?.trim();
        
        if (!nombre || !email) {
            UI.alertError('Nombre y email son requeridos');
            return;
        }
        
        const resultado = await ProveedoresAPI.create({ nombre, email, telefono });
        
        if (resultado.success) {
            UI.showSuccess(`Proveedor <strong>${nombre}</strong> agregado`, { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarProveedores(); UI.scrollToSection('proveedores'); } });
            document.getElementById('formulario-proveedores')?.reset();
            await cargarProveedores();
        } else {
            UI.handleAPIError(resultado, { title: 'Error al agregar proveedor' });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al agregar proveedor');
    }
}

/**
 * Editar proveedor
 */
async function editarProveedor(id) {
    try {
        console.log('✏️ Editando proveedor ID:', id);
        
        // Buscar el proveedor en la lista global
        const proveedores = window.proveedores || [];
        const proveedor = proveedores.find(p => p.id === id || p.id === parseInt(id));
        
        if (!proveedor) {
            UI.showDialog({
                title: '❌ Error',
                message: 'No se pudo encontrar el proveedor',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        console.log('🤝 Proveedor encontrado:', proveedor);
        
        const htmlContent = `
            <div style="max-height: 400px; overflow-y: auto;">
                <div class="form-group">
                    <label for="edit-proveedor-nombre">Nombre *</label>
                    <input type="text" id="edit-proveedor-nombre" value="${proveedor.nombre || ''}" placeholder="Nombre del proveedor" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="edit-proveedor-email">Email *</label>
                    <input type="email" id="edit-proveedor-email" value="${proveedor.email || ''}" placeholder="correo@proveedor.com" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="edit-proveedor-telefono">Teléfono</label>
                    <input type="tel" id="edit-proveedor-telefono" value="${proveedor.telefono || ''}" placeholder="+1 (555) 000-0000" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>
        `;
        
        UI.showDialog({
            title: '✏️ Editar Proveedor',
            icon: '🤝',
            message: htmlContent,
            buttons: [
                { 
                    text: 'Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: 'Guardar Cambios', 
                    onClick: async () => {
                        const nombreActualizado = document.getElementById('edit-proveedor-nombre')?.value?.trim();
                        const emailActualizado = document.getElementById('edit-proveedor-email')?.value?.trim();
                        const telefonoActualizado = document.getElementById('edit-proveedor-telefono')?.value?.trim();
                        
                        if (!nombreActualizado) {
                            UI.showDialog({
                                title: '⚠️ Campo Requerido',
                                message: 'Por favor ingresa el nombre del proveedor',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        if (!emailActualizado) {
                            UI.showDialog({
                                title: '⚠️ Campo Requerido',
                                message: 'Por favor ingresa el email del proveedor',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        try {
                            console.log('📤 Actualizando proveedor:', {
                                nombre: nombreActualizado,
                                email: emailActualizado,
                                telefono: telefonoActualizado
                            });
                            
                            const resultado = await ProveedoresAPI.update(id, {
                                nombre: nombreActualizado,
                                email: emailActualizado,
                                telefono: telefonoActualizado
                            });
                            
                            if (resultado.success) {
                                UI.showDialog({
                                    title: '✅ Éxito',
                                    message: 'Proveedor actualizado correctamente',
                                    buttons: [{ 
                                        text: 'OK', 
                                        onClick: () => {
                                            cargarProveedores();
                                            document.getElementById('modal-dialogo').style.display = 'none';
                                        }
                                    }]
                                });
                            } else {
                                UI.showDialog({
                                    title: '❌ Error',
                                    message: resultado.error || 'Error al actualizar el proveedor',
                                    buttons: [{ text: 'OK', action: 'close' }]
                                });
                            }
                        } catch (error) {
                            console.error('❌ Error actualizando:', error);
                            UI.showDialog({
                                title: '❌ Error',
                                message: 'Error: ' + error.message,
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                        }
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('❌ Error en editarProveedor:', error);
        UI.showDialog({
            title: '❌ Error',
            message: 'Error: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Eliminar proveedor
 */
async function eliminarProveedor(id) {
    try {
        const resultado = await ProveedoresAPI.delete(id);
        
        if (resultado.success) {
            try { resaltarFila('proveedor', id); } catch(e){ safeWarn('resaltarFila (proveedor) failed', e); }
            UI.showSuccess('Proveedor eliminado', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarProveedores(); UI.scrollToSection('proveedores'); } });
            await cargarProveedores();
        } else if (resultado && resultado.status === 409) {
            UI.showDialog({ title: 'No se puede eliminar', icon: '⚠️', message: resultado.error || 'Existen registros relacionados con este proveedor. Abra Productos para revisar.', buttons: [ { text: 'Abrir Productos', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'productos'); } }, { text: 'Cerrar', action: 'close' } ] });
        } else {
            UI.handleAPIError(resultado, { title: 'Error al eliminar proveedor' });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al eliminar');
    }
}

// ============================================================================
// VENTAS - CRUD OPERATIONS
// ============================================================================

/**
 * Cargar ventas
 */
async function cargarVentas() {
    try {
        let res;
        try { res = await apiCallWithTimeout(() => VentasAPI.getAll(), 7000); } catch (error) { if (error && error.message === 'timeout') { console.warn('VentasAPI timeout'); UI.toast && UI.toast('Timeout cargando ventas', 'warn', 5000); return; } throw error; }
        let ventas = [];
        if (Array.isArray(res)) ventas = res;
        else if (res && res.success === true && Array.isArray(res.data)) ventas = res.data;
        else {
            console.error('Error: VentasAPI no retornó un array', res);
            UI.handleAPIError(res, { title: 'Error cargando ventas' });
            return;
        }
        mostrarVentas(ventas);
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al cargar ventas: ' + (error && error.message || error));
    }
}

/**
 * Mostrar ventas
 */
function mostrarVentas(ventas = []) {
    // Guardar en variable global
    window.ventas = ventas;
    
    const tbody = document.getElementById('tabla-ventas');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (ventas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">Sin ventas registradas</td></tr>';
        return;
    }
    
    ventas.forEach(venta => {
        const row = document.createElement('tr');
        const tdCliente = document.createElement('td'); tdCliente.textContent = venta.cliente_id || 'N/A'; row.appendChild(tdCliente);
        const tdTotal = document.createElement('td'); tdTotal.textContent = '$' + parseFloat(venta.total || 0).toFixed(2); row.appendChild(tdTotal);
        const tdFecha = document.createElement('td'); tdFecha.textContent = venta.fecha || 'N/A'; row.appendChild(tdFecha);
        const tdCreated = document.createElement('td'); tdCreated.textContent = venta.created_at ? new Date(venta.created_at).toLocaleDateString('es-MX') : 'N/A'; row.appendChild(tdCreated);
        const tdActions = document.createElement('td');

        const btnEdit = document.createElement('button'); btnEdit.className = 'btn-editar'; btnEdit.title = 'Editar';
        btnEdit.addEventListener('click', function(e){ e.preventDefault(); try{ editarVenta(venta.id); } catch(err){ safeWarn('editarVenta click', err); } });
        const iconEdit = document.createElement('i'); iconEdit.className = 'fas fa-edit'; btnEdit.appendChild(iconEdit);
        tdActions.appendChild(btnEdit);

        const btnDel = document.createElement('button'); btnDel.className = 'btn-eliminar'; btnDel.setAttribute('data-type','venta'); btnDel.setAttribute('data-id', String(venta.id)); btnDel.setAttribute('aria-label','Eliminar venta ' + (venta.id||'')); btnDel.title='Eliminar'; btnDel.innerHTML = '<i class="fas fa-trash"></i>';
        tdActions.appendChild(btnDel);

        row.appendChild(tdActions);
        tbody.appendChild(row);
    });
}

/**
 * Agregar venta
 */
async function agregarVentaForm(event) {
    event.preventDefault();
    
    try {
        const cliente_id = document.getElementById('venta-cliente')?.value;
        const total = parseFloat(document.getElementById('venta-total')?.value);
        const fecha = document.getElementById('venta-fecha')?.value;
        
        if (!cliente_id || isNaN(total) || !fecha) {
            UI.alertError('Todos los campos son requeridos');
            return;
        }
        
        const resultado = await VentasAPI.create({ cliente_id, total, fecha });
        
        if (resultado.success) {
            UI.showSuccess('Venta registrada', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarVentas(); UI.scrollToSection('ventas'); } });
            document.getElementById('formulario-ventas')?.reset();
            await cargarVentas();
        } else {
            UI.handleAPIError(resultado, { title: 'Error al agregar venta' });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al agregar venta');
    }
}

/**
 * Editar venta
 */
async function editarVenta(id) {
    try {
        console.log('✏️ Editando venta ID:', id);
        
        // Buscar la venta en la lista global
        const ventas = window.ventas || [];
        const venta = ventas.find(v => v.id === id || v.id === parseInt(id));
        
        if (!venta) {
            UI.showDialog({
                title: '❌ Error',
                message: 'No se pudo encontrar la venta',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        console.log('💳 Venta encontrada:', venta);
        
        // Obtener lista de clientes
        let clientes = [];
        try {
            const res = await ClientesAPI.getAll();
            if (Array.isArray(res)) clientes = res;
            else if (res && res.success === true && Array.isArray(res.data)) clientes = res.data;
            else clientes = [];
        } catch (error) {
            console.warn('⚠️ Error cargando clientes:', error);
        }
        
        const clientesHTML = (clientes || []).map(cli => 
            `<option value="${cli.id}" ${venta.cliente_id === cli.id ? 'selected' : ''}>${cli.nombre}</option>`
        ).join('');
        
        const htmlContent = `
            <div style="max-height: 400px; overflow-y: auto;">
                <div class="form-group">
                    <label for="edit-venta-cliente">Cliente *</label>
                    <select id="edit-venta-cliente" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">-- Selecciona un cliente --</option>
                        ${clientesHTML}
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-venta-total">Total *</label>
                    <input type="number" id="edit-venta-total" value="${venta.total || 0}" min="0" step="0.01" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="edit-venta-fecha">Fecha *</label>
                    <input type="date" id="edit-venta-fecha" value="${venta.fecha || ''}" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>
        `;
        
        UI.showDialog({
            title: '✏️ Editar Venta',
            icon: '💳',
            message: htmlContent,
            buttons: [
                { 
                    text: 'Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: 'Guardar Cambios', 
                    onClick: async () => {
                        const clienteActualizado = document.getElementById('edit-venta-cliente')?.value;
                        const totalActualizado = parseFloat(document.getElementById('edit-venta-total')?.value);
                        const fechaActualizada = document.getElementById('edit-venta-fecha')?.value;
                        
                        if (!clienteActualizado || isNaN(totalActualizado) || !fechaActualizada) {
                            UI.showDialog({
                                title: '⚠️ Campos Requeridos',
                                message: 'Por favor completa todos los campos',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        try {
                            console.log('📤 Actualizando venta:', {
                                cliente_id: clienteActualizado,
                                total: totalActualizado,
                                fecha: fechaActualizada
                            });
                            
                            const resultado = await VentasAPI.update(id, {
                                cliente_id: parseInt(clienteActualizado),
                                total: totalActualizado,
                                fecha: fechaActualizada
                            });
                            
                            if (resultado.success) {
                                UI.showDialog({
                                    title: '✅ Éxito',
                                    message: 'Venta actualizada correctamente',
                                    buttons: [{ 
                                        text: 'OK', 
                                        onClick: () => {
                                            cargarVentas();
                                            document.getElementById('modal-dialogo').style.display = 'none';
                                        }
                                    }]
                                });
                            } else {
                                UI.showDialog({
                                    title: '❌ Error',
                                    message: resultado.error || 'Error al actualizar la venta',
                                    buttons: [{ text: 'OK', action: 'close' }]
                                });
                            }
                        } catch (error) {
                            console.error('❌ Error actualizando:', error);
                            UI.showDialog({
                                title: '❌ Error',
                                message: 'Error: ' + error.message,
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                        }
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('❌ Error en editarVenta:', error);
        UI.showDialog({
            title: '❌ Error',
            message: 'Error: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Eliminar venta
 */
async function eliminarVenta(id) {
    try {
        const resultado = await VentasAPI.delete(id);
        
        if (resultado.success) {
            try { resaltarFila('venta', id); } catch(e){ safeWarn('resaltarFila (venta) failed', e); }
            UI.showDialog({
                title: '✅ ¡Éxito!',
                icon: '🎉',
                message: 'La venta ha sido eliminada correctamente.',
                buttons: [{ 
                    text: 'OK', 
                    onClick: async () => {
                        await cargarVentas();
                    }
                }]
            });
        } else if (resultado && resultado.status === 409) {
            UI.showDialog({ title: 'No se puede eliminar', icon: '⚠️', message: resultado.error || 'No se puede eliminar la venta debido a integridad referencial.', buttons: [ { text: 'Abrir Ventas', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'ventas'); } }, { text: 'Cerrar', action: 'close' } ] });
        } else {
            UI.showDialog({
                title: '❌ Error',
                icon: '⚠️',
                message: resultado.error || 'No se pudo eliminar la venta',
                buttons: [{ text: 'OK', action: 'close' }]
            });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.showDialog({
            title: '❌ Error del Sistema',
            icon: '⚠️',
            message: 'Error al eliminar: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

// ============================================================================
// SALIDAS - CRUD OPERATIONS
// ============================================================================

/**
 * Cargar salidas
 */
async function cargarSalidas() {
    try {
        let res;
        try { res = await apiCallWithTimeout(() => SalidasAPI.getAll(), 7000); } catch (error) { if (error && error.message === 'timeout') { console.warn('SalidasAPI timeout'); UI.toast && UI.toast('Timeout cargando salidas', 'warn', 5000); return; } throw error; }
        let salidas = [];
        if (Array.isArray(res)) salidas = res;
        else if (res && res.success === true && Array.isArray(res.data)) salidas = res.data;
        else {
            console.error('Error: SalidasAPI no retornó un array', res);
            UI.handleAPIError(res, { title: 'Error cargando salidas' });
            return;
        }
        mostrarSalidas(salidas);
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al cargar salidas: ' + (error && error.message || error));
    }
}

/**
 * Mostrar salidas
 */
function mostrarSalidas(salidas = []) {
    // Guardar en variable global
    window.salidas = salidas;
    
    const tbody = document.getElementById('tabla-salidas');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (salidas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">Sin salidas registradas</td></tr>';
        return;
    }
    
    salidas.forEach(salida => {
        const row = document.createElement('tr');
        const tdProd = document.createElement('td'); tdProd.textContent = salida.producto || salida.producto_id || 'N/A'; row.appendChild(tdProd);
        const tdCant = document.createElement('td'); tdCant.textContent = salida.cantidad || 'N/A'; row.appendChild(tdCant);
        const tdFecha = document.createElement('td'); tdFecha.textContent = salida.fecha || 'N/A'; row.appendChild(tdFecha);
        const tdCreated = document.createElement('td'); tdCreated.textContent = salida.created_at ? new Date(salida.created_at).toLocaleDateString('es-MX') : 'N/A'; row.appendChild(tdCreated);
        const tdActions = document.createElement('td');

        const btnEdit = document.createElement('button'); btnEdit.className = 'btn-editar'; btnEdit.title = 'Editar';
        btnEdit.addEventListener('click', function(e){ e.preventDefault(); try{ editarSalida(salida.id); } catch(err){ safeWarn('editarSalida click', err); } });
        const iconEdit = document.createElement('i'); iconEdit.className = 'fas fa-edit'; btnEdit.appendChild(iconEdit);
        tdActions.appendChild(btnEdit);

        const btnDel = document.createElement('button'); btnDel.className = 'btn-eliminar'; btnDel.setAttribute('data-type','salida'); btnDel.setAttribute('data-id', String(salida.id)); btnDel.setAttribute('aria-label','Eliminar salida ' + (salida.id||'')); btnDel.title='Eliminar'; btnDel.innerHTML = '<i class="fas fa-trash"></i>';
        tdActions.appendChild(btnDel);

        row.appendChild(tdActions);
        tbody.appendChild(row);
    });
}

/**
 * Agregar salida
 */
async function agregarSalidaForm(event) {
    event.preventDefault();
    
    try {
        const producto_id = document.getElementById('salida-producto')?.value;
        const cantidad = parseInt(document.getElementById('salida-cantidad')?.value);
        const fecha = document.getElementById('salida-fecha')?.value;
        
        if (!producto_id || isNaN(cantidad) || !fecha) {
            UI.alertError('Todos los campos son requeridos');
            return;
        }
        
        const resultado = await SalidasAPI.create({ producto_id, cantidad, fecha });
        
        if (resultado.success) {
            UI.showSuccess('Salida registrada', { mode: 'both', actionLabel: 'Ver', actionCallback: async () => { await cargarSalidas(); UI.scrollToSection('salidas'); } });
            document.getElementById('formulario-salidas')?.reset();
            await cargarSalidas();
        } else {
            UI.handleAPIError(resultado, { title: 'Error al agregar salida' });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.alertError('Error al agregar salida');
    }
}

/**
 * Editar salida
 */
async function editarSalida(id) {
    try {
        console.log('✏️ Editando salida ID:', id);
        
        // Buscar la salida en la lista global
        const salidas = window.salidas || [];
        const salida = salidas.find(s => s.id === id || s.id === parseInt(id));
        
        if (!salida) {
            UI.showDialog({
                title: '❌ Error',
                message: 'No se pudo encontrar la salida',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        console.log('📤 Salida encontrada:', salida);
        
        // Obtener lista de productos
        let productos = [];
        try {
            const res = await ProductosAPI.getAll();
            if (Array.isArray(res)) productos = res;
            else if (res && res.success === true && Array.isArray(res.data)) productos = res.data;
            else productos = [];
        } catch (error) {
            console.warn('⚠️ Error cargando productos:', error);
        }
        
        const productosHTML = (productos || []).map(prod => 
            `<option value="${prod.id}" ${salida.producto_id === prod.id ? 'selected' : ''}>${prod.nombre}</option>`
        ).join('');
        
        const htmlContent = `
            <div style="max-height: 400px; overflow-y: auto;">
                <div class="form-group">
                    <label for="edit-salida-producto">Producto *</label>
                    <select id="edit-salida-producto" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">-- Selecciona un producto --</option>
                        ${productosHTML}
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-salida-cantidad">Cantidad *</label>
                    <input type="number" id="edit-salida-cantidad" value="${salida.cantidad || 0}" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="edit-salida-fecha">Fecha *</label>
                    <input type="date" id="edit-salida-fecha" value="${salida.fecha || ''}" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>
        `;
        
        UI.showDialog({
            title: '✏️ Editar Salida',
            icon: '📤',
            message: htmlContent,
            buttons: [
                { 
                    text: 'Cancelar', 
                    action: 'close',
                    type: 'normal'
                },
                { 
                    text: 'Guardar Cambios', 
                    onClick: async () => {
                        const productoActualizado = document.getElementById('edit-salida-producto')?.value;
                        const cantidadActualizada = parseInt(document.getElementById('edit-salida-cantidad')?.value);
                        const fechaActualizada = document.getElementById('edit-salida-fecha')?.value;
                        
                        if (!productoActualizado || isNaN(cantidadActualizada) || !fechaActualizada) {
                            UI.showDialog({
                                title: '⚠️ Campos Requeridos',
                                message: 'Por favor completa todos los campos',
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                            return;
                        }
                        
                        try {
                            console.log('📤 Actualizando salida:', {
                                producto_id: productoActualizado,
                                cantidad: cantidadActualizada,
                                fecha: fechaActualizada
                            });
                            
                            const resultado = await SalidasAPI.update(id, {
                                producto_id: parseInt(productoActualizado),
                                cantidad: cantidadActualizada,
                                fecha: fechaActualizada
                            });
                            
                            if (resultado.success) {
                                UI.showDialog({
                                    title: '✅ Éxito',
                                    message: 'Salida actualizada correctamente',
                                    buttons: [{ 
                                        text: 'OK', 
                                        onClick: () => {
                                            cargarSalidas();
                                            document.getElementById('modal-dialogo').style.display = 'none';
                                        }
                                    }]
                                });
                            } else {
                                UI.showDialog({
                                    title: '❌ Error',
                                    message: resultado.error || 'Error al actualizar la salida',
                                    buttons: [{ text: 'OK', action: 'close' }]
                                });
                            }
                        } catch (error) {
                            console.error('❌ Error actualizando:', error);
                            UI.showDialog({
                                title: '❌ Error',
                                message: 'Error: ' + error.message,
                                buttons: [{ text: 'OK', action: 'close' }]
                            });
                        }
                    },
                    type: 'success'
                }
            ]
        });
        
    } catch (error) {
        console.error('❌ Error en editarSalida:', error);
        UI.showDialog({
            title: '❌ Error',
            message: 'Error: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

/**
 * Eliminar salida
 */
async function eliminarSalida(id) {
    try {
        const resultado = await SalidasAPI.delete(id);
        
        if (resultado.success) {
            try { resaltarFila('salida', id); } catch(e){ safeWarn('resaltarFila (salida) failed', e); }
            UI.showDialog({
                title: '✅ ¡Éxito!',
                icon: '🎉',
                message: 'La salida ha sido eliminada correctamente.',
                buttons: [{ 
                    text: 'OK', 
                    onClick: async () => {
                        await cargarSalidas();
                    }
                }]
            });
        } else if (resultado && resultado.status === 409) {
            UI.showDialog({ title: 'No se puede eliminar', icon: '⚠️', message: resultado.error || 'No se puede eliminar la salida debido a integridad referencial.', buttons: [ { text: 'Abrir Salidas', onClick: ()=>{ if (typeof navegarA === 'function') navegarA({preventDefault:()=>{}, stopPropagation:()=>{}}, 'salidas'); } }, { text: 'Cerrar', action: 'close' } ] });
        } else {
            UI.showDialog({
                title: '❌ Error',
                icon: '⚠️',
                message: resultado.error || 'No se pudo eliminar la salida',
                buttons: [{ text: 'OK', action: 'close' }]
            });
        }
    } catch (error) {
        console.error('Error:', error);
        UI.showDialog({
            title: '❌ Error del Sistema',
            icon: '⚠️',
            message: 'Error al eliminar: ' + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
}

// ============================================================================
// FUNCIONES DE UTILIDAD Y SECCIONES
// ============================================================================

/**
 * Resaltar sección y desplazar a ella (función auxiliar)
 */
function resaltarSeccion(idSeccion, duration = 7000) {
    const section = document.getElementById(idSeccion);
    if (!section) return;

    // Limpiar resaltados previos en otras secciones
    try {
        document.querySelectorAll('.selected-highlight').forEach(el => {
            if (el !== section) { el.classList.remove('selected-highlight'); el.classList.remove('selected-highlight-fade'); if (el._highlightTimer) { clearTimeout(el._highlightTimer); delete el._highlightTimer; } }
        });
    } catch(e) { /* ignore */ }

    // Animación breve de pulso (visual inmediato)
    section.style.animation = 'none';
    setTimeout(() => { section.style.animation = 'pulse-highlight 0.6s ease-out'; }, 50);

    // Aplicar clase de resaltado persistente que durará `duration` ms
    section.classList.remove('selected-highlight-fade');
    section.classList.add('selected-highlight');

    // Asegurar foco para accesibilidad
    try { section.setAttribute('tabindex', '-1'); section.focus({preventScroll:true}); } catch(e){ safeWarn('section focus failed', e); }

    // Limpiar cualquier timer previo
    if (section._highlightTimer) { clearTimeout(section._highlightTimer); }

    section._highlightTimer = setTimeout(() => {
        try {
            // Añadir una pequeña transición de salida
            section.classList.add('selected-highlight-fade');
            setTimeout(() => { section.classList.remove('selected-highlight', 'selected-highlight-fade'); try{ delete section._highlightTimer; }catch(e){ safeWarn('clear highlight timer failed', e); } }, 350);
        } catch(e){ console.warn('Error al limpiar resaltado', e); }
    }, Number(duration) || 7000);
}

/**
 * Mostrar sección de ventas y cargar datos
 */
/**
 * Resaltar una fila (o elemento contenedor) para dar feedback visual cuando se realiza
 * una acción (por ejemplo, eliminar). Encuentra la fila por data-id o por boton dentro.
 */
function resaltarFila(tipo, id, duration = 1200) {
    try {
        if (!id) return;
        // Buscar elemento con data-id y tipo si es posible
        let selector = `[data-id="${id}"]`;
        let el = document.querySelector(selector);
        if (!el) {
            // Intentar buscar por atributos personalizados: fila con data-type y data-id
            el = document.querySelector(`[data-type="${tipo}"][data-id="${id}"]`) || document.querySelector(`#tabla-body [data-id="${id}"]`);
        }
        // Si encontramos un botón, resaltar su fila padre
        if (el && el.tagName === 'BUTTON') el = el.closest('tr') || el;
        if (!el) return;
        el.classList.add('row-highlight');
        setTimeout(()=> { el.classList.remove('row-highlight'); }, duration);
    } catch (e) { console.warn('resaltarFila error', e); }
}

async function mostrarSeccionVentas() {
    resaltarSeccion('registro-ventas');
    await cargarVentas();
    try {
        window.UI && window.UI.showSectionDialog && window.UI.showSectionDialog('registro-ventas', { title: 'Ventas', message: 'Consulta las ventas recientes, genera nuevos tickets y revisa el historial de transacciones.' });
    } catch(e){console.warn('UI dialog failed', e);}
}

/**
 * Mostrar sección de salidas y cargar datos
 */
async function mostrarSeccionSalidas() {
    resaltarSeccion('registro-salidas');
    await cargarSalidas();
    try {
        window.UI && window.UI.showSectionDialog && window.UI.showSectionDialog('registro-salidas', { title: 'Salidas', message: 'Gestiona salidas y movimientos de inventario. Registra y acompaña cada salida.' });
    } catch(e){console.warn('UI dialog failed', e);}
}

/**
 * Mostrar sección de clientes y cargar datos
 */
async function mostrarSeccionClientes() {
    resaltarSeccion('registro-clientes');
    await cargarClientes();
    try {
        window.UI && window.UI.showSectionDialog && window.UI.showSectionDialog('registro-clientes', { title: 'Clientes', message: 'Administra clientes, historial de compras y datos de contacto.' });
    } catch(e){console.warn('UI dialog failed', e);}
}

/**
 * Mostrar sección de proveedores y cargar datos
 */
async function mostrarSeccionProveedores() {
    resaltarSeccion('registro-proveedores');
    await cargarProveedores();
    try {
        window.UI && window.UI.showSectionDialog && window.UI.showSectionDialog('registro-proveedores', { title: 'Proveedores', message: 'Administra proveedores y condiciones de compra, añade contactos y documentos.' });
    } catch(e){console.warn('UI dialog failed', e);}
}

// ============================================================================
// FILTROS Y BÚSQUEDA
// ============================================================================

/**
 * Filtrar productos
 */
function filtrarProductos() {
    // Filtrado en el cliente combinado con búsqueda en servidor
    const nombre = document.getElementById('filtro-nombre')?.value || '';
    if (nombre.length >= 2) {
        // Búsqueda en servidor en tiempo real
        debouncedServerSearch(nombre);
    } else if (nombre.length === 0) {
        // Restaurar lista completa
        cargarProductos();
    }
}

// Debounce helper
function debounce(fn, wait) {
    let t = null;
    return function(...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
}

const debouncedServerSearch = debounce(async function(q){
    try {
        const res = await ProductosAPI.search(q);
        let productos = [];
        if (Array.isArray(res)) productos = res;
        else if (res && res.success === true && Array.isArray(res.data)) productos = res.data;
        mostrarProductos(productos);
    } catch (e) {
        console.warn('Error en búsqueda remota:', e);
    }
}, 300);

/**
 * Limpiar búsqueda
 */
/**
 * Alternar visibilidad de los filtros
 */
function toggleFiltros() {
    const btnToggle = document.getElementById('btn-toggle-filtros');
    const contenedor = document.getElementById('filtros-contenedor');
    
    if (!btnToggle || !contenedor) {
        console.error('❌ Elementos de filtros no encontrados');
        return;
    }
    
    const isHidden = contenedor.style.display === 'none';
    contenedor.style.display = isHidden ? 'block' : 'none';
    
    btnToggle.textContent = isHidden ? 'Ocultar Filtros' : 'Mostrar Filtros';
    console.log(`📋 Filtros ${isHidden ? 'mostrados' : 'ocultos'}`);
}

function limpiarBusqueda() {
    try { const el = $id('filtro-nombre'); if (el) el.value = ''; } catch(e){ safeWarn('clear filtro-nombre failed', e); }
    try { const el2 = $id('filtro-categoria'); if (el2) el2.value = ''; } catch(e){ safeWarn('clear filtro-categoria failed', e); }
    try { const el3 = $id('filtro-existencia'); if (el3) el3.value = ''; } catch(e){ safeWarn('clear filtro-existencia failed', e); }
    try { const el4 = $id('orden-productos'); if (el4) el4.value = 'nombre-asc'; } catch(e){ safeWarn('set orden-productos failed', e); }
    filtrarProductos();
    UI.showSuccess('Filtros limpios', { mode: 'both' });
}

// ============================================================================
// IMPORTAR/EXPORTAR
// ============================================================================

/**
 * Importar Excel - Redirigir a la sección de Importar Inventario
 */
function importarExcel() {
    console.log('📥 Importar Excel - Redirigiendo a sección de Importar Inventario...');
    UI.showDialog({
        title: '📥 Importar Inventario',
        icon: '📊',
        message: 'Para importar un archivo Excel, dirígete a la sección <strong>"Importar Inventario desde Archivo"</strong> donde encontrarás:<br><br>✅ Descarga de plantilla Excel<br>✅ Carga de archivos .xlsx y .xls<br>✅ Vista previa antes de importar<br>✅ Importación automática a la BD',
        buttons: [
            { text: 'Cancelar', action: 'close', type: 'normal' },
            { 
                text: 'Ir a Importar Inventario', 
                onClick: () => {
                    navegarA({preventDefault: () => {}, stopPropagation: () => {}}, 'ejemplo-tabla');
                },
                type: 'success'
            }
        ]
    });
}

/**
 * Exportar Excel - Descargar todos los productos en formato Excel
 */
function exportarExcel() {
    try {
        console.log('📤 Exportando productos a Excel...');
        
        if (!window.productos || window.productos.length === 0) {
            UI.alertError('No hay productos para exportar');
            return;
        }
        
        UI.showDialog({
            title: '⏳ Generando archivo',
            icon: '🔄',
            message: 'Procesando ' + window.productos.length + ' productos...',
            buttons: []
        });
        
        // Preparar datos
        const datos = [
            ['Código', 'Nombre', 'Categoría', 'Cantidad', 'Costo', 'Precio', 'Ganancia', 'Fecha Caducidad', 'Fecha Registro']
        ];
        
        window.productos.forEach(prod => {
            const ganancia = (prod.precio - prod.costo) * prod.cantidad;
            datos.push([
                prod.codigo || '',
                prod.nombre || '',
                prod.categoria_nombre || '',
                prod.cantidad || 0,
                prod.costo || 0,
                prod.precio || 0,
                ganancia.toFixed(2),
                prod.fecha_caducidad || '',
                prod.fecha_registro || ''
            ]);
        });
        
        // Crear workbook
        const ws = XLSX.utils.aoa_to_sheet(datos);
        ws['!cols'] = [
            {wch: 12}, {wch: 25}, {wch: 18}, {wch: 12}, 
            {wch: 12}, {wch: 12}, {wch: 12}, {wch: 15}, {wch: 15}
        ];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Inventario');
        
        const fecha = new Date().toISOString().split('T')[0];
        XLSX.writeFile(wb, `inventario_${fecha}.xlsx`);
        
        // Cerrar modal
        const modal = document.getElementById('modal-dialogo');
        if (modal) modal.style.display = 'none';
        
        UI.showSuccess('Inventario exportado a Excel correctamente', { mode: 'both' });
        console.log('✅ Exportación completada: ' + window.productos.length + ' productos');
    } catch (error) {
        console.error('❌ Error:', error);
        UI.alertError('Error al exportar a Excel: ' + error.message);
    }
}

/**
 * Importar PDF - Mostrar información
 */
function importarPDF() {
    console.log('📥 Importar PDF - Información');
    UI.showDialog({
        title: '⚠️ Importar desde PDF',
        icon: '❌',
        message: '<strong>La importación desde PDF no está soportada</strong><br><br>Por favor utiliza uno de estos formatos:<br><br>✅ <strong>Excel</strong> (.xlsx, .xls)<br>✅ <strong>CSV</strong> (.csv)<br><br>Dirígete a la sección <strong>"Importar Inventario desde Archivo"</strong> para importar tus datos.',
        buttons: [
            { text: 'OK', action: 'close', type: 'normal' },
            { 
                text: 'Ir a Importar', 
                onClick: () => {
                    navegarA({preventDefault: () => {}, stopPropagation: () => {}}, 'ejemplo-tabla');
                },
                type: 'success'
            }
        ]
    });
}

/**
 * Exportar PDF - Generar reporte en PDF
 */
function exportarPDF() {
    try {
        console.log('📤 Exportando productos a PDF...');
        
        if (!window.productos || window.productos.length === 0) {
            UI.alertError('No hay productos para exportar');
            return;
        }
        
        UI.showDialog({
            title: '⏳ Generando PDF',
            icon: '🔄',
            message: 'Procesando ' + window.productos.length + ' productos...',
            buttons: []
        });
        
        // Crear PDF
        const {jsPDF} = window.jspdf;
        const doc = new jsPDF();
        
        // Configurar documento
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        
        // Encabezado
        doc.setFontSize(16);
        doc.text('📦 REPORTE DE INVENTARIO', 14, 20);
        
        doc.setFontSize(10);
        doc.text(`Generado: ${new Date().toLocaleString()}`, 14, 28);
        doc.text(`Total de productos: ${window.productos.length}`, 14, 35);
        
        // Preparar datos para tabla
        const datosTabla = window.productos.map(prod => {
            const ganancia = (prod.precio - prod.costo) * prod.cantidad;
            return [
                prod.codigo || '',
                prod.nombre || '',
                prod.categoria_nombre || '',
                prod.cantidad || 0,
                '$' + (prod.costo || 0).toFixed(2),
                '$' + (prod.precio || 0).toFixed(2),
                '$' + ganancia.toFixed(2)
            ];
        });
        
        // Crear tabla
        doc.autoTable({
            head: [['Código', 'Nombre', 'Categoría', 'Cantidad', 'Costo', 'Precio', 'Ganancia']],
            body: datosTabla,
            startY: 42,
            theme: 'grid',
            headStyles: {fillColor: [255, 152, 0], textColor: [255, 255, 255], fontStyle: 'bold'},
            bodyStyles: {textColor: [50, 50, 50]},
            alternateRowStyles: {fillColor: [245, 245, 245]},
            margin: {top: 10, right: 10, bottom: 10, left: 10}
        });
        
        // Resumen
        const totalCosto = window.productos.reduce((sum, p) => sum + (p.costo * p.cantidad), 0);
        const totalPrecio = window.productos.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
        const totalGanancia = totalPrecio - totalCosto;
        
        const finalY = doc.lastAutoTable.finalY + 10;
        
        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.text(`Costo Total: $${totalCosto.toFixed(2)}`, 14, finalY);
        doc.text(`Valor Total: $${totalPrecio.toFixed(2)}`, 14, finalY + 8);
        doc.text(`Ganancia Potencial: $${totalGanancia.toFixed(2)}`, 14, finalY + 16);
        
        // Descargar
        const fecha = new Date().toISOString().split('T')[0];
        doc.save(`inventario_${fecha}.pdf`);
        
        // Cerrar modal
        const modal = document.getElementById('modal-dialogo');
        if (modal) modal.style.display = 'none';
        
        UI.showSuccess('Inventario exportado a PDF correctamente', { mode: 'both' });
        console.log('✅ PDF generado: inventario_' + fecha + '.pdf');
    } catch (error) {
        console.error('❌ Error:', error);
        UI.alertError('Error al exportar a PDF: ' + error.message);
    }
}

// ============================================================================
// IMPORTACIÓN DE INVENTARIO
// ============================================================================

/**
 * Descargar plantilla Excel
 */
function descargarPlantillaExcel() {
    try {
        console.log('📥 Descargando plantilla Excel...');
        
        // Datos de ejemplo
        const datos = [
            ['Código', 'Nombre', 'Cantidad', 'Costo', 'Precio', 'Fecha Caducidad', 'Categoría'],
            ['PROD001', 'Laptop Dell', 5, 800.00, 1200.00, '2026-12-31', 'Electrónica'],
            ['PROD002', 'Mouse Logitech', 20, 15.00, 35.00, '2026-06-30', 'Accesorios'],
            ['PROD003', 'Teclado Mecánico', 10, 60.00, 120.00, '2027-12-31', 'Accesorios'],
            ['', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '']
        ];
        
        // Crear workbook con XLSX
        const ws = XLSX.utils.aoa_to_sheet(datos);
        ws['!cols'] = [
            {wch: 12}, {wch: 25}, {wch: 12}, {wch: 12}, 
            {wch: 12}, {wch: 18}, {wch: 15}
        ];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Inventario');
        
        XLSX.writeFile(wb, 'plantilla_inventario.xlsx');
        
        UI.showSuccess('Plantilla Excel descargada correctamente', { mode: 'both' });
        console.log('✅ Plantilla Excel descargada');
    } catch (error) {
        console.error('❌ Error:', error);
        UI.alertError('Error al descargar plantilla Excel');
    }
}

/**
 * Descargar plantilla CSV
 */
function descargarPlantillaCSV() {
    try {
        console.log('📥 Descargando plantilla CSV...');
        
        const csv = `Código,Nombre,Cantidad,Costo,Precio,Fecha Caducidad,Categoría
PROD001,Laptop Dell,5,800.00,1200.00,2026-12-31,Electrónica
PROD002,Mouse Logitech,20,15.00,35.00,2026-06-30,Accesorios
PROD003,Teclado Mecánico,10,60.00,120.00,2027-12-31,Accesorios`;
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'plantilla_inventario.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        UI.showSuccess('Plantilla CSV descargada correctamente', { mode: 'both' });
        console.log('✅ Plantilla CSV descargada');
    } catch (error) {
        console.error('❌ Error:', error);
        UI.alertError('Error al descargar plantilla CSV');
    }
}

/**
 * Importar inventario desde archivo
 */
async function importarInventarioDesdeArchivo() {
    try {
        console.log('📤 Iniciando importación de inventario...');
        
        const fileInput = document.getElementById('archivo-importar');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            UI.showDialog({
                title: '⚠️ Selecciona un archivo',
                icon: '❌',
                message: 'Por favor selecciona un archivo Excel o CSV para importar.',
                buttons: [{ text: 'OK', action: 'close' }]
            });
            return;
        }
        
        const archivo = fileInput.files[0];
        console.log('📄 Archivo seleccionado:', archivo.name, `(${(archivo.size / 1024).toFixed(2)} KB)`);
        
        // Validar tamaño
        if (archivo.size > 5 * 1024 * 1024) { // 5 MB
            UI.alertError('El archivo es muy grande (máximo 5 MB)');
            return;
        }
        
        // Mostrar cargando
        UI.showDialog({
            title: '⏳ Procesando archivo',
            icon: '🔄',
            message: 'Leyendo archivo e identificando datos...',
            buttons: []
        });
        
        let datos = [];
        
        if (archivo.name.endsWith('.csv')) {
            datos = await leerArchivoCSV(archivo);
        } else if (archivo.name.endsWith('.xlsx') || archivo.name.endsWith('.xls')) {
            datos = await leerArchivoExcel(archivo);
        } else {
            UI.alertError('Formato de archivo no soportado. Use .csv, .xlsx o .xls');
            return;
        }
        
        console.log(`✅ Se leyeron ${datos.length} filas`);
        
        if (datos.length === 0) {
            UI.alertError('El archivo no contiene datos válidos');
            return;
        }
        
        // Mostrar vista previa
        mostrarVistaPreviaImportacion(datos, archivo.name);
        
    } catch (error) {
        console.error('❌ Error:', error);
        UI.alertError('Error al procesar archivo: ' + error.message);
    }
}

/**
 * Leer archivo CSV
 */
async function leerArchivoCSV(archivo) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const csv = e.target.result;
                const lineas = csv.split('\n').filter(l => l.trim() !== '');
                
                if (lineas.length < 2) {
                    resolve([]);
                    return;
                }
                
                const encabezados = lineas[0].split(',').map(h => h.trim());
                const datos = [];
                
                for (let i = 1; i < lineas.length; i++) {
                    const valores = lineas[i].split(',').map(v => v.trim());
                    const fila = {};
                    
                    encabezados.forEach((encabezado, index) => {
                        fila[encabezado] = valores[index] || '';
                    });
                    
                    if (Object.values(fila).some(v => v !== '')) {
                        datos.push(fila);
                    }
                }
                
                resolve(datos);
            } catch (error) {
                reject(error);
            }
        };
        
        reader.onerror = function() {
            reject(new Error('No se pudo leer el archivo'));
        };
        
        reader.readAsText(archivo);
    });
}

/**
 * Leer archivo Excel
 */
async function leerArchivoExcel(archivo) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const datos = new Uint8Array(e.target.result);
                const libro = XLSX.read(datos, { type: 'array' });
                const primera_hoja = libro.Sheets[libro.SheetNames[0]];
                const datos_json = XLSX.utils.sheet_to_json(primera_hoja);
                
                resolve(datos_json);
            } catch (error) {
                reject(error);
            }
        };
        
        reader.onerror = function() {
            reject(new Error('No se pudo leer el archivo Excel'));
        };
        
        reader.readAsArrayBuffer(archivo);
    });
}

/**
 * Mostrar vista previa de importación
 */
function mostrarVistaPreviaImportacion(datos, nombreArchivo) {
    console.log('👁️ Mostrando vista previa de', datos.length, 'registros');
    
    let htmlTabla = `<table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #ff9800; color: white;">`;
    
    const encabezados = Object.keys(datos[0] || {});
    encabezados.forEach(enc => {
        htmlTabla += `<th style="padding: 10px; text-align: left; border: 1px solid #ffb84d;">${enc}</th>`;
    });
    
    htmlTabla += `</tr></thead><tbody>`;
    
    // Mostrar máximo 10 filas en la previa
    const filasAMostrar = datos.slice(0, 10);
    filasAMostrar.forEach((fila, index) => {
        htmlTabla += `<tr style="background: ${index % 2 === 0 ? '#f9f9f9' : '#fff'};">`;
        encabezados.forEach(enc => {
            htmlTabla += `<td style="padding: 10px; border: 1px solid #ffb84d; word-break: break-word;">${fila[enc] || ''}</td>`;
        });
        htmlTabla += `</tr>`;
    });
    
    if (datos.length > 10) {
        htmlTabla += `<tr style="background: #fffbe6;">
            <td colspan="${encabezados.length}" style="padding: 10px; text-align: center; border: 1px solid #ffb84d;">
                <em>... y ${datos.length - 10} registros más</em>
            </td>
        </tr>`;
    }
    
    htmlTabla += `</tbody></table>`;
    
    UI.showDialog({
        title: '👁️ Vista Previa de Importación',
        icon: '📋',
        message: `<strong>${nombreArchivo}</strong> contiene <strong>${datos.length}</strong> registros<br><br>Verifica que los datos sean correctos:<br><br>${htmlTabla}<br><br>¿Deseas importar estos datos?`,
        buttons: [
            { text: 'Cancelar', action: 'close', type: 'normal' },
            { 
                text: 'Importar Ahora', 
                onClick: async () => {
                    await confirmarImportacion(datos);
                },
                type: 'success'
            }
        ]
    });
}

/**
 * Confirmar e importar los datos
 */
async function confirmarImportacion(datos) {
    try {
        console.log('📦 Confirmando importación de', datos.length, 'productos...');
        
        // Mostrar progreso
        UI.showDialog({
            title: '⏳ Importando Productos',
            icon: '🔄',
            message: 'Procesando y agregando productos a la base de datos...<br><br>Por favor espera...',
            buttons: []
        });
        
        let importados = 0;
        let errores = [];
        
        // Importar cada producto
        for (let i = 0; i < datos.length; i++) {
            const fila = datos[i];
            
            try {
                // Validar campos obligatorios
                if (!fila['Código'] || !fila['Nombre'] || !fila['Cantidad'] || !fila['Precio'] || !fila['Fecha Caducidad']) {
                    errores.push(`Fila ${i + 2}: Faltan campos obligatorios`);
                    continue;
                }
                
                // Buscar categoría
                let categorias = [];
                try {
                    const res = await CategoriasAPI.getAll();
                    if (Array.isArray(res)) categorias = res;
                    else if (res && res.success === true && Array.isArray(res.data)) categorias = res.data;
                    else categorias = [];
                } catch (e) {
                    categorias = [];
                }
                let categoria_id = 1; // Por defecto
                
                if (fila['Categoría']) {
                    const cat = (categorias || []).find(c => (c.nombre || '').toLowerCase() === String(fila['Categoría']).toLowerCase());
                    if (cat) {
                        categoria_id = cat.id;
                    }
                }
                
                // Crear producto
                const producto = {
                    codigo: String(fila['Código']).trim(),
                    nombre: String(fila['Nombre']).trim(),
                    categoria_id: categoria_id,
                    cantidad: parseInt(fila['Cantidad']) || 0,
                    costo: parseFloat(fila['Costo'] || 0),
                    precio: parseFloat(fila['Precio']),
                    fecha_caducidad: fila['Fecha Caducidad'],
                    fecha_registro: new Date().toISOString().split('T')[0]
                };
                
                // Enviar a API
                const resultado = await ProductosAPI.create(producto);
                
                if (resultado && resultado.success) {
                    importados++;
                    console.log(`✅ Producto ${importados}/${datos.length} importado`);
                } else {
                    errores.push(`Fila ${i + 2}: ${resultado?.error || 'Error desconocido'}`);
                }
                
            } catch (error) {
                errores.push(`Fila ${i + 2}: ${error.message}`);
            }
        }
        
        // Mostrar resultado
        let mensajeResultado = `<strong>✅ Importación completada</strong><br><br>
            <strong style="color: green;">✓ Productos importados: ${importados}</strong><br>`;
        
        if (errores.length > 0) {
            mensajeResultado += `<strong style="color: #ff9800;">⚠️ Errores: ${errores.length}</strong><br><br>
                <details style="text-align: left;">
                    <summary style="cursor: pointer; color: #ff9800; font-weight: bold;">Ver detalles de errores</summary>
                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 0.9rem; max-height: 200px; overflow-y: auto;">
${errores.join('\n')}
                    </pre>
                </details>`;
        }
        
        // Cerrar modal de progreso
        const modal = document.getElementById('modal-dialogo');
        if (modal) modal.style.display = 'none';
        
        // Mostrar resultado
        UI.showDialog({
            title: '📊 Resultado de Importación',
            icon: '✅',
            message: mensajeResultado,
            buttons: [
                { text: 'OK', action: 'close' },
                { 
                    text: 'Ver Productos', 
                    onClick: async () => {
                        try { const el = $id('archivo-importar'); if (el) el.value = ''; } catch(e){ safeWarn('clear archivo-importar failed', e); }
                        await cargarProductos();
                        UI.scrollToSection('productos');
                    },
                    type: 'success'
                }
            ]
        });
        
        console.log(`✅ Importación finalizada: ${importados} importados, ${errores.length} errores`);
        
    } catch (error) {
        console.error('❌ Error:', error);
        UI.alertError('Error durante la importación: ' + error.message);
    }
}

/**
 * Toggle tabla ejemplo - Mostrar u ocultar tabla de ejemplo
 */
function toggleTablaEjemplo() {
    const tabla = document.getElementById('tabla-ejemplo');
    const boton = document.getElementById('btn-tabla-ejemplo');
    
    if (tabla) {
        const isHidden = tabla.style.display === 'none';
        tabla.style.display = isHidden ? 'block' : 'none';
        
        // Actualizar texto del botón
        if (boton) {
            if (isHidden) {
                boton.innerHTML = '<i class="fas fa-eye-slash"></i> Ocultar Tabla de Ejemplo';
                console.log('✅ Tabla de ejemplo mostrada');
            } else {
                boton.innerHTML = '<i class="fas fa-eye"></i> Ver Tabla de Ejemplo';
                console.log('✅ Tabla de ejemplo oculta');
            }
        }
    }
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

/**
 * Inicializar la aplicación cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🚀 Inicializando aplicación...');
    
    try {
        // Inicializar navbar
        initNavbar();

        // Use top-level helper apiCallWithTimeout to wrap promises with a timeout.

        // Mostrar un loader no intrusivo (desactivado por preferencia del usuario)
        // try { UI.showLoader && UI.showLoader('Cargando datos...'); } catch(e){ /* noop */ }

        // Lanzar las cargas principales en paralelo, pero no bloquear la UI si tardan
        const loadTasks = [
            (async()=>{ try { await cargarCategorias(); } catch(e){ console.warn('cargarCategorias failed', e); } })(),
            (async()=>{ try { await cargarProductos(); } catch(e){ console.warn('cargarProductos failed', e); } })(),
            (async()=>{ try { await cargarClientes(); } catch(e){ console.warn('cargarClientes failed', e); } })(),
            (async()=>{ try { await cargarProveedores(); } catch(e){ console.warn('cargarProveedores failed', e); } })(),
            (async()=>{ try { await cargarVentas(); } catch(e){ console.warn('cargarVentas failed', e); } })(),
            (async()=>{ try { await cargarSalidas(); } catch(e){ console.warn('cargarSalidas failed', e); } })(),
        ];

        // Wait briefly for quick loads; if they take longer, hide loader and let them continue in background
        await Promise.race([Promise.allSettled(loadTasks), new Promise(res => setTimeout(res, 800))]);
        // try { UI.hideLoader && UI.hideLoader(); } catch(e){ /* noop */ }  // Loader no se muestra, así que no ocultarlo

        // When all loads finish, notify user (non-blocking)
        Promise.allSettled(loadTasks).then(results => {
            const failed = results.filter(r => r.status === 'rejected');
            if (failed.length) { try { UI.toast && UI.toast('Cargado con ' + failed.length + ' errores (ver consola)', 'warn', 4800); } catch(e){ safeWarn('UI.toast failed (failed count)', e); } }
            else { try { UI.toast && UI.toast('Datos cargados', 'success', 1600); } catch(e){ safeWarn('UI.toast failed (success)', e); } }
        }).catch(e => console.warn('loadTasks finish handler failed', e));

        console.log('✅ Aplicación inicializada (cargas en background si fue necesario)');
        
        // Verificación runtime: funciones globales de botones flotantes y calculadora
        console.log('🔧 Verificando funciones globales:');
        console.log('  toggleCalculator:', typeof window.toggleCalculator === 'function' ? '✅ Disponible' : '❌ NO disponible');
        console.log('  toggleAIChat:', typeof window.toggleAIChat === 'function' ? '✅ Disponible' : '❌ NO disponible');
        console.log('  toggleAIEnhancements:', typeof window.toggleAIEnhancements === 'function' ? '✅ Disponible' : '❌ NO disponible');
        console.log('  sendAIMessage:', typeof window.sendAIMessage === 'function' ? '✅ Disponible' : '❌ NO disponible');
        console.log('🧮 Verificando funciones calculadora:');
        console.log('  calcPress:', typeof window.calcPress === 'function' ? '✅ Disponible' : '❌ NO disponible');
        console.log('  calcEquals:', typeof window.calcEquals === 'function' ? '✅ Disponible' : '❌ NO disponible');
        console.log('  calcClear:', typeof window.calcClear === 'function' ? '✅ Disponible' : '❌ NO disponible');
        
        
        // Mostrar mensaje de bienvenida (omitido en localhost a menos que se indique explicitamente)
        // try {
        //     const isLocal = (location.hostname === 'localhost' || location.hostname === '127.0.0.1');
        //     const force = window.CONFIG && window.CONFIG.showInitToast === true;
        //     if (!isLocal || force) UI.showSuccess('Sistema cargado exitosamente', { mode: 'both', autoClose: 2500 });
        //     else console.info('Init toast suppressed on localhost');
        // } catch(e) { try{ UI.showSuccess('Sistema cargado exitosamente', { mode: 'both', autoClose: 2500 }); } catch(err){ /* noop */ } }
        
    } catch (error) {
        console.error('❌ Error en inicialización:', error);
        
        // Cerrar diálogo de carga
        const modal = document.getElementById('modal-dialogo');
        if (modal) modal.style.display = 'none';
        
        UI.showDialog({
            title: '❌ Error de Inicialización',
            icon: '⚠️',
            message: "No se pudo inicializar el sistema correctamente.<br><br>" + error.message,
            buttons: [{ text: 'OK', action: 'close' }]
        });
    }
});

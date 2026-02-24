/* js/ui.js - UI helpers: dialogs, toasts and utilities */
(function(){
    if (window.DEBUG) console.log('ui.js: load start', new Date().toISOString());
    // Ensure UI namespace exists early to avoid race-condition errors
    window.UI = window.UI || {}; 
    if (typeof UI === 'undefined') { UI = window.UI; }
    // Toast de éxito estándar
    window.UI.success = function(message, duration=4000) {
        window.UI.toast && window.UI.toast(message, 'success', duration);
    };
(function(){
    let modal = document.getElementById('modal-dialogo');
    let modalTitle = document.getElementById('modal-dialogo-titulo');
    let modalIcon = document.getElementById('modal-dialogo-icono');
    let modalMessage = document.getElementById('modal-dialogo-mensaje');
    let modalButtons = document.getElementById('modal-dialogo-botones');
    let modalInputContainer = document.getElementById('modal-dialogo-input-container');
    let modalInput = document.getElementById('modal-dialogo-input');
    let modalCloseBtn = document.getElementById('cerrar-modal-dialogo');
    if (modalCloseBtn) modalCloseBtn.addEventListener('click', function(){ if (typeof window.UI !== 'undefined') window.UI.closeDialog(); else if (modal) modal.style.display='none'; });

    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
            try { modal.removeAttribute('data-type'); } catch(e){ safeWarn('ui.closeModal.removeDataType', e); }
            try { delete modal.dataset.targetType; delete modal.dataset.targetId; } catch(e){ safeWarn('ui.closeModal.deleteDataset', e); }
            try { modal.removeAttribute('data-blocking'); } catch(e){ safeWarn('ui.closeModal.removeBlocking', e); }
        }
        // After closing, show next queued dialog (if any)
        try { setTimeout(showNextDialog, 40); } catch(e){ safeWarn('ui.closeModal.showNext', e); }
    }

    function openModal() {
        if (modal) modal.style.display = 'flex';
    }

    // Dialog queue to prevent multiple modals stacking on top of each other
    const dialogQueue = [];
    function showNextDialog(){
        try {
            if (!modal) return;
            if (modal.style.display === 'flex') return; // still open
            const next = dialogQueue.shift();
            if (next) {
                // Slight delay to allow any CSS transitions to finish
                setTimeout(function(){ try { window.UI.showDialog(next); } catch(e){ safeWarn('ui.showNextDialog.windowUIshowDialog', e); } }, 60);
            }
        } catch(e){ console.warn('showNextDialog failed', e); }
    }

    function createButton(btn) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = btn.class || 'btn';
        b.textContent = btn.text || 'OK';
        // Mark buttons created by our helper so delegated handlers skip them
        b.dataset.hasHandler = '1';
        b.addEventListener('click', function() {
            try {
                if (btn.action === 'close') closeModal();
                else if (typeof btn.onClick === 'function') btn.onClick();
                // Backward-compat: allow `action` to be a function (some callers use `action: async () => {}`)
                else if (typeof btn.action === 'function') btn.action();
                else if (btn.action && window[btn.action] && typeof window[btn.action] === 'function') window[btn.action](btn.payload);
            } catch (e) {
                console.error('Error button action', e);
            }
        });
        return b;
    }

    // UI namespace initialized at top; define API methods below
    // Ensure basic loader functions exist so other modules can call UI.showLoader/hideLoader
    window.UI._ensureLoaderElement = function() {
        try {
            let el = document.getElementById('app-shim-loader');
            if (!el) {
                el = document.createElement('div');
                el.id = 'app-shim-loader';
                el.style.display = 'none';
                el.style.position = 'fixed';
                el.style.left = '0';
                el.style.top = '0';
                el.style.right = '0';
                el.style.bottom = '0';
                el.style.alignItems = 'center';
                el.style.justifyContent = 'center';
                el.style.background = 'rgba(0,0,0,0.36)';
                el.style.zIndex = '99998';
                el.innerHTML = '<div style="background:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);font-weight:600;color:#111; display:flex; align-items:center; gap:10px;">Cargando</div>';
                document.body.appendChild(el);
            }
            return el;
        } catch (e) { console.warn('ensureLoaderElement failed', e); return null; }
    };

    window.UI.showLoader = window.UI.showLoader || function(message) {
        try {
            const el = window.UI._ensureLoaderElement();
            if (!el) return;
            const inner = el.firstElementChild;
            if (inner) inner.textContent = message || 'Cargando...';
            el.style.display = 'flex';
        } catch (e) { console.warn('showLoader failed', e); }
    };

    window.UI.hideLoader = window.UI.hideLoader || function() {
        try { const el = document.getElementById('app-shim-loader'); if (el) el.style.display = 'none'; } catch(e){ console.warn('hideLoader failed', e); }
    };

    window.UI.showDialog = function(opts = {}) {
        // If a dialog is already visible and this call isn't forced, queue it to avoid overlap
        try {
            if (modal && modal.style.display === 'flex') {
                if (!opts || !opts.forceShow) {
                    dialogQueue.push(opts || {});
                    return {
                        close: function() { const idx = dialogQueue.indexOf(opts); if (idx >= 0) dialogQueue.splice(idx,1); }
                    };
                }
                // if opts.forceShow === true, fall through and replace current dialog
            }
        } catch(e){ console.warn('dialog queue check failed', e); }

        // Si no existe el modal, créalo dinámicamente para evitar alert nativo
        if (!modal) {
            console.warn('Modal element not found, creating dynamically');
            const modalDiv = document.createElement('div');
            modalDiv.id = 'modal-dialogo';
            modalDiv.style.display = 'flex';
            // Create modal structure using DOM APIs (avoid injecting raw HTML block)
            var content = document.createElement('div');
            content.className = 'modal-content';

            var iconSpan = document.createElement('span');
            iconSpan.id = 'modal-dialogo-icono';
            content.appendChild(iconSpan);

            var titleH2 = document.createElement('h2');
            titleH2.id = 'modal-dialogo-titulo';
            content.appendChild(titleH2);

            var msgDiv = document.createElement('div');
            msgDiv.id = 'modal-dialogo-mensaje';
            content.appendChild(msgDiv);

            var inputContainer = document.createElement('div');
            inputContainer.id = 'modal-dialogo-input-container';
            inputContainer.style.display = 'none';
            var inputEl = document.createElement('input');
            inputEl.id = 'modal-dialogo-input';
            inputEl.type = 'text';
            inputContainer.appendChild(inputEl);
            content.appendChild(inputContainer);

            var buttonsDiv = document.createElement('div');
            buttonsDiv.id = 'modal-dialogo-botones';
            content.appendChild(buttonsDiv);

            var closeBtn = document.createElement('button');
            closeBtn.id = 'cerrar-modal-dialogo';
            closeBtn.style.position = 'absolute';
            closeBtn.style.top = '10px';
            closeBtn.style.right = '10px';
            closeBtn.innerHTML = '\u00D7';
            content.appendChild(closeBtn);

            modalDiv.appendChild(content);
            document.body.appendChild(modalDiv);
            // Re-query references so modal variables point to created elements
            modal = document.getElementById('modal-dialogo');
            modalTitle = document.getElementById('modal-dialogo-titulo');
            modalIcon = document.getElementById('modal-dialogo-icono');
            modalMessage = document.getElementById('modal-dialogo-mensaje');
            modalButtons = document.getElementById('modal-dialogo-botones');
            modalInputContainer = document.getElementById('modal-dialogo-input-container');
            modalInput = document.getElementById('modal-dialogo-input');
            modalCloseBtn = document.getElementById('cerrar-modal-dialogo');
            if (modalCloseBtn) modalCloseBtn.addEventListener('click', function(){ if (typeof window.UI !== 'undefined') window.UI.closeDialog(); else if (modal) modal.style.display='none'; });
            // continue execution without reloading
        }
        // Forzar display:block/flex si algún CSS lo oculta
        modal.style.display = 'flex';
        // Tipo y icono por defecto según tipo
        const type = opts.type || 'info';
        const icons = { success: '✅', error: '❌', warn: '⚠️', info: 'ℹ️', confirm: '❓' };
        modalTitle.textContent = opts.title || (type === 'success' ? 'Éxito' : (type === 'error' ? 'Error' : (opts.title || '')));
        modalIcon.textContent = opts.icon || icons[type] || '';
        modalMessage.innerHTML = opts.html || (opts.message || '');
        // dataset para estilos según tipo (success/error/info/confirm)
        try { modal.setAttribute('data-type', type); } catch(e){ safeWarn('ui.showDialog.setDataType', e); }
        modalButtons.innerHTML = '';
        modalInputContainer.style.display = 'none';

        if (opts.input) {
            modalInputContainer.style.display = 'flex';
            modalInput.value = opts.input.value || '';
            setTimeout(()=> modalInput.focus(), 80);
        } else {
            // foco al primer botón después de abrir
            setTimeout(()=> {
                const btn = modalButtons.querySelector('button');
                if (btn) btn.focus();
            }, 80);
        }

        const defaultButtons = type === 'confirm' ? [
            { text: 'Cancelar', class: 'btn', action: 'close', onClick: () => { if (typeof opts.onCancel === 'function') opts.onCancel(); } },
            { text: opts.confirmText || 'Aceptar', class: 'btn primary', action: 'close', onClick: () => { if (typeof opts.onConfirm === 'function') opts.onConfirm(); } }
        ] : [{ text: opts.okText || 'Cerrar', class: 'btn primary', action: 'close' }];

        const buttons = opts.buttons && opts.buttons.length ? opts.buttons : defaultButtons;
        buttons.forEach(btn => modalButtons.appendChild(createButton(btn)));

        // show & focus
        openModal();
        if (typeof opts.onOpen === 'function') opts.onOpen();

        // Auto-close support
        if (opts.autoClose && Number(opts.autoClose) > 0) {
            setTimeout(()=> { try { closeModal(); if (typeof opts.onAutoClose === 'function') opts.onAutoClose(); } catch(e){ safeWarn('ui.showDialog.autoClose', e); } }, Number(opts.autoClose));
        }

        // Key handling (ESC to close)
        function keyHandler(e){
            if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', keyHandler); }
        }
        document.addEventListener('keydown', keyHandler);

        return {
            close: closeModal
        };
    };

    window.UI.closeDialog = closeModal;

    // Toast manager: use a fixed container and a small queue to avoid toasts overlapping
    window.UI._ensureToastContainer = function() {
        try {
            let container = document.getElementById('app-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'app-toast-container';
                container.style.position = 'fixed';
                container.style.right = '12px';
                container.style.bottom = '12px';
                container.style.display = 'flex';
                // Stack top-to-bottom but keep items pinned to the bottom of the page
                container.style.flexDirection = 'column';
                container.style.justifyContent = 'flex-end';
                container.style.alignItems = 'flex-end';
                container.style.gap = '10px';
                container.style.zIndex = '99999';
                container.style.maxWidth = 'min(480px, calc(100vw - 48px))';
                container.style.pointerEvents = 'none'; // let individual toasts handle pointer events
                container.setAttribute('aria-live','polite');
                document.body.appendChild(container);
            }
            return container;
        } catch(e) { console.warn('ensureToastContainer failed', e); return null; }
    };

    window.UI._toastQueue = window.UI._toastQueue || [];
    window.UI._toastVisible = window.UI._toastVisible || 0;
    // Default to 1 so toasts appear sequentially (one at a time). Can be overridden by setting window.UI._maxVisibleToasts
    window.UI._maxVisibleToasts = window.UI._maxVisibleToasts || 1;

    window.UI.toast = function(message, type='info', duration=4000, actionLabel, actionCallback) {
        try {
            // Suppress non-error toasts on local development hosts when configured
            try {
                const isLocal = (location.hostname === 'localhost' || location.hostname === '127.0.0.1');
                const suppress = (window.CONFIG && window.CONFIG.suppressDevToasts === true);
                if (isLocal && suppress && type !== 'error') return null;
            } catch(e) { safeWarn('ui.toast.devSuppressCheck', e); }

            const container = window.UI._ensureToastContainer();
            if (!container) { console.warn('Toast container unavailable, fallback to console.'); if (type === 'error') console.error(message); else console.info(message); return null; }

            const show = function() {
                // Inject styles once
                if (!document.getElementById('app-toast-styles')) {
                    const style = document.createElement('style');
                    style.id = 'app-toast-styles';
                    style.innerHTML = `
#app-toast-container { display:flex; flex-direction:column-reverse; gap:10px; align-items:flex-end; position:fixed; right:12px; bottom:12px; z-index:99999; }
.app-toast { display:flex; align-items:stretch; gap:10px; min-width:260px; max-width:480px; border-radius:12px; overflow:hidden; font-weight:600; color:#fff; box-shadow:0 10px 30px rgba(0,0,0,0.12); transform:translateY(6px); opacity:0; transition:opacity .28s ease, transform .28s ease; }
.app-toast .toast-badge { background:#2f83ff; color:#fff; padding:10px 14px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; }
.app-toast .toast-main { padding:12px 14px; display:flex; align-items:center; gap:12px; flex:1; color:#fff; }
.app-toast.success .toast-main { background:linear-gradient(90deg,#16b78d,#0ea36e); }
.app-toast.info .toast-main { background:linear-gradient(90deg,#2f83ff,#1b6bf0); }
.app-toast.warn .toast-main { background:linear-gradient(90deg,#ffb34d,#ff9a08); color:#111; }
.app-toast.error .toast-main { background:linear-gradient(90deg,#ff6b6b,#ff4c4c); }
.app-toast .toast-content { flex:1; }
.app-toast .toast-actions { display:flex; gap:8px; align-items:center; }
.app-toast .toast-action, .app-toast .toast-close { background:transparent; border:0; color:inherit; cursor:pointer; font-weight:700; padding:6px 8px; border-radius:6px; }
.app-toast .toast-close { font-size:18px; }
`; document.head.appendChild(style); }

                const el = document.createElement('div');
                el.className = 'app-toast ' + (type || 'info');
                el.setAttribute('role','status');
                el.setAttribute('aria-live','polite');

                // Support message as string OR object with { text, badge }
                let messageText = '';
                let badgeLabel = null;
                if (message && typeof message === 'object') { messageText = message.text || message.message || ''; badgeLabel = message.badge || message.label || null; }
                else messageText = String(message || '');

                const badgeEl = badgeLabel ? document.createElement('div') : null;
                if (badgeEl) { badgeEl.className = 'toast-badge'; badgeEl.textContent = badgeLabel; el.appendChild(badgeEl); }

                const main = document.createElement('div');
                main.className = 'toast-main';
                main.innerHTML = `<div class="toast-content">${messageText}</div>`;

                // Actions container
                const actions = document.createElement('div'); actions.className = 'toast-actions';
                if (actionLabel && typeof actionCallback === 'function') {
                    const action = document.createElement('button');
                    action.className = 'toast-action';
                    action.type = 'button';
                    action.setAttribute('aria-label', actionLabel);
                    action.title = actionLabel;
                    action.textContent = actionLabel;
                    action.addEventListener('click', function(e){ try { actionCallback(); } catch(err){ console.error(err); } hide(el); });
                    action.addEventListener('keyup', function(e){ if (e.key === 'Enter' || e.key === ' ') { action.click(); } });
                    actions.appendChild(action);
                }

                const closeX = document.createElement('button');
                closeX.className = 'toast-close';
                closeX.setAttribute('aria-label','Cerrar');
                closeX.innerHTML = '&times;';
                closeX.addEventListener('click', ()=> { hide(el); });
                actions.appendChild(closeX);

                main.appendChild(actions);
                el.appendChild(main);

                // Force alignment and sizing per-toast to avoid overlaps
                el.style.alignSelf = 'flex-end';
                el.style.width = '100%';
                el.style.boxSizing = 'border-box';
                el.style.marginTop = '8px';
                el.style.pointerEvents = 'auto';
                // ensure newer toasts appear above older ones
                try { el.style.zIndex = String(100000 + (Date.now() % 1000)); } catch(e){ safeWarn('ui.toast.setZIndex', e); }

                container.appendChild(el);
                // Stagger show slightly based on currently visible toasts to avoid animation collisions
                const showDelay = Math.min(20 + (window.UI._toastVisible * 70), 420);
                setTimeout(()=> { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; }, showDelay);
                window.UI._toastVisible++;

                const timeoutId = setTimeout(()=> { hide(el); }, duration);

                function hide(node) {
                    try {
                        clearTimeout(timeoutId);
                        node.style.opacity = '0'; node.style.transform = 'translateY(6px)';
                        setTimeout(()=> {
                            try { node.remove(); } catch(e){ safeWarn('ui.toast.nodeRemove', e); }
                            window.UI._toastVisible--;
                            if (window.UI._toastQueue.length) {
                                // Schedule next toast slightly after the hide animation completes
                                const nextShow = window.UI._toastQueue.shift();
                                setTimeout(()=>{ try { nextShow(); } catch(e){ console.warn('next toast show failed', e); } }, 140);
                            }
                        }, 320);
                    } catch(e){ console.warn('hide toast error', e); }
                }

                return el;
            };

            // If more than max visible toasts, queue the toast
            if (window.UI._toastVisible >= window.UI._maxVisibleToasts) {
                window.UI._toastQueue.push(show);
                return { queued: true, cancel: function(){ const idx = window.UI._toastQueue.indexOf(show); if (idx >= 0) window.UI._toastQueue.splice(idx,1); } };
            }
            return show();
        } catch(e){ console.warn('Toast error', e); }
    };

    window.UI.confirm = function(message, opts={}) {
        return new Promise(resolve => {
            window.UI.showDialog(Object.assign({
                title: opts.title || 'Confirmar',
                type: 'confirm',
                message: message,
                confirmText: opts.confirmText || 'Aceptar',
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false)
            }, opts));
        });
    };

    // Convenience: alert helpers (success / error)
    window.UI.alertSuccess = function(message, opts={}){
        const options = Object.assign({ type: 'success', title: opts.title || '✅ Éxito', html: message, autoClose: opts.autoClose || 3000 }, opts);
        return window.UI.showDialog(options);
    };

    // Mostrar éxito con opciones: 'toast' | 'dialog' | 'both'
    window.UI.showSuccess = function(message, opts={}){
        const mode = opts.mode || (window.CONFIG && window.CONFIG.uiSuccessMode) || 'both';
        const toastDuration = opts.duration || 3500;
        const actionLabel = opts.actionLabel;
        const actionCallback = opts.actionCallback;

        if (mode === 'toast' || mode === 'both') {
            window.UI.toast(message, 'success', toastDuration, actionLabel, actionCallback);
        }
        if (mode === 'dialog' || mode === 'both') {
            const dialogOpts = Object.assign({ type: 'success', title: opts.title || 'Éxito', html: message, autoClose: opts.autoClose || 3000 }, opts.dialog || {});
            window.UI.showDialog(dialogOpts);
        }
        return true;
    };

    window.UI.alertError = function(message, opts={}){
        const options = Object.assign({ type: 'error', title: opts.title || '❌ Error', html: message }, opts);
        return window.UI.showDialog(options);
    };

    // Helper para mostrar errores de API con opción de ver RAW
    window.UI.handleAPIError = function(res, opts={}){
        try {
            const title = opts.title || 'Error de servidor';
            const message = (res && res.error) ? res.error : (typeof res === 'string' ? res : (res && res.message ? res.message : 'Error desconocido'));
            const raw = res && res.raw ? res.raw : null;

            if (raw) {
                // Mostrar diálogo con botón para ver RAW completo
                return window.UI.showDialog({
                    type: 'error',
                    title: title,
                    html: `<p style="text-align:left;">${message}</p><hr><small>Preview:</small><pre style="background:#111;color:#fff;padding:8px;border-radius:6px;max-height:260px;overflow:auto;">${(String(raw).replace(/</g,'&lt;')).slice(0,2000)}</pre>`,
                    buttons: [
                        { text: 'Copiar RAW', class: 'btn', onClick: ()=>{ try { navigator.clipboard.writeText(String(raw)); UI.toast('RAW copiado al portapapeles', 'info', 3500); } catch(e){ UI.toast('No se pudo copiar', 'error', 3500); } } },
                        { text: 'Cerrar', class: 'btn primary', action: 'close' }
                    ]
                });
            }

            return window.UI.alertError(message, opts);
        } catch (e) {
            console.error('handleAPIError failed', e);
            return window.UI.alertError('Error al mostrar el error: ' + (e.message || e));
        }
    };

    // Convenience to show a section info modal
    window.UI.showSectionDialog = function(sectionId, opts={}){
        const title = opts.title || (sectionId.replace(/-/g,' ').replace(/\b\w/g, c=>c.toUpperCase()));
        const message = opts.message || `Información y acciones para <strong>${title}</strong>.`;
        window.UI.showDialog({
            title: opts.title || title,
            icon: opts.icon || 'ℹ️',
            html: message,
            buttons: [
                { text: 'Abrir sección', class: 'btn primary', onClick: ()=>{ document.getElementById(sectionId) && document.getElementById(sectionId).scrollIntoView({behavior:'smooth'}); if(typeof resaltarSeccion==='function'){resaltarSeccion(sectionId);} } },
                { text: 'Cerrar', class: 'btn', action: 'close' }
            ]
        });
    };

    // Marca que la implementación completa del UI está cargada
    try {
        window.UI._isEnhanced = true;
        // Si el shim fue usado temporalmente, avisar (no intrusivo)
        if (window.UI._shimUsed) {
            window.UI._wasShim = true;
            window.UI._shimUsed = false;
            // Mostrar un toast informativo
            try { window.UI.toast && window.UI.toast('UI mejorada activada (se usó shim temporal)', 'info', 4200); } catch(e){ safeWarn('ui.shim.toast', e); }
            console.info('UI enhanced loaded after shim');
            // Prompt de consentimiento y envío de telemetría anónima (si aplica)
            try { window.UI.promptTelemetryConsentIfNeeded && window.UI.promptTelemetryConsentIfNeeded(); } catch(e) { console.warn('Telemetry prompt failed', e); }
        } else {
            console.info('UI enhanced loaded');
        }
    } catch (e) { console.warn('UI enhancement flagging failed', e); }

    // API error handlers
    window.UI.handleAPIError = function(error, context) {
        let msg = 'Error de API';
        if (typeof error === 'string') msg = error;
        else if (error && error.error) msg = error.error;
        else if (error && error.message) msg = error.message;
        else if (error) msg = JSON.stringify(error);
        if (window.UI.toast) window.UI.toast(msg, 'error', 6000);
        else console.error('API Error:', error);
    };

    window.UI.alertError = function(msg) {
        if (window.UI.toast) window.UI.toast(msg, 'error', 6000);
        else alert(msg);
    };

    // Bloqueo visual para tareas largas: mostrar solo diálogos y un modal bloqueante
    window.UI.showBlockingDialog = function(opts) {
        try {
            const title = (opts && opts.title) || 'Procesando...';
            const message = (opts && opts.message) || '';
            // Clear queued dialogs so blocking dialog takes precedence
            try { dialogQueue.length = 0; } catch(e) { safeWarn('ui.showBlockingDialog.clearQueue', e); }
            // Reusar modal existente `modal-dialogo` y configurarlo en modo bloqueante
            const modal = document.getElementById('modal-dialogo');
            const content = document.getElementById('modal-dialogo-mensaje');
            const titleEl = document.getElementById('modal-dialogo-titulo');
            const iconEl = document.getElementById('modal-dialogo-icono');
            const botones = document.getElementById('modal-dialogo-botones');
            if (!modal || !content || !titleEl || !botones) return console.warn('blocking modal elements missing');
            titleEl.textContent = title;
            iconEl.textContent = (opts && opts.icon) || '⏳';
            content.innerHTML = message;
            botones.innerHTML = '';
            // Mark modal as blocking and ensure it sits above other UI
            try { modal.setAttribute('data-blocking','1'); modal.style.zIndex = '99999'; } catch(e){ safeWarn('ui.showBlockingDialog.setBlocking', e); }
            // Botón opcional de cancelar si se permite
            if (opts && opts.allowCancel) {
                const btn = document.createElement('button'); btn.className='btn'; btn.textContent='Cancelar'; btn.onclick = function(){ window.UI.closeBlockingDialog(); if (typeof opts.onCancel === 'function') opts.onCancel(); };
                botones.appendChild(btn);
            }
            // Mantener la UI minimalista: ocultar el resto si requested
            if (opts && opts.onlyDialogs) window.UI._applyOnlyDialogs(true);
            modal.style.display = 'flex';
            document.body.classList.add('blocking-dialog-active');
        } catch (e) { console.warn('showBlockingDialog failed', e); }
    };

    window.UI.updateBlockingDialog = function(messageHtml) { try { const content = document.getElementById('modal-dialogo-mensaje'); if (content) content.innerHTML = messageHtml; } catch(e){console.warn('updateBlockingDialog failed', e);} };
    window.UI.closeBlockingDialog = function() { try { const modal = document.getElementById('modal-dialogo'); if (!modal) return; modal.style.display='none'; document.body.classList.remove('blocking-dialog-active'); window.UI._applyOnlyDialogs(false); try { modal.removeAttribute('data-blocking'); } catch(e){ safeWarn('ui.closeBlockingDialog.removeBlocking', e); } try { setTimeout(showNextDialog, 40); } catch(e){ safeWarn('ui.closeBlockingDialog.showNext', e); } } catch(e){console.warn('closeBlockingDialog failed', e);} };

    window.UI._applyOnlyDialogs = function(enable) {
        try {
            const hideEls = ['.navbar','.main-sections','#logo-box-container','#floating-buttons-wrapper'];
            hideEls.forEach(sel => { document.querySelectorAll(sel).forEach(el => { el.style.display = enable ? 'none' : ''; }); });
        } catch(e){console.warn('applyOnlyDialogs failed', e);}    
    };

    window.UI.startDBInit = async function() {
        try {
            window.UI.showBlockingDialog({ title: 'Inicializando base de datos', message: 'Conectando y ejecutando esquema...<br/><small>Esto puede tardar hasta 30 segundos.</small>', icon: '🔧', onlyDialogs: true });
            // Llamar al setup-database.php y mostrar el HTML de progreso/resultado dentro del modal
            const dbUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('setup-database.php') : (window.APP_BASE ? (window.APP_BASE + 'setup-database.php') : '/setup-database.php');
            const res = await fetch(dbUrl);
            if (!res.ok) {
                const text = await res.text().catch(()=>null);
                window.UI.updateBlockingDialog('<p style="color:#c00;">Error al inicializar: HTTP ' + res.status + '</p>' + (text ? '<pre style="max-height:240px;overflow:auto">'+escapeHtml(text)+'</pre>' : ''));
                return;
            }
            const html = await res.text();
            // Monospace preview dentro del modal
            window.UI.updateBlockingDialog(html);
            // Añadir botón Cerrar en modal
            const botones = document.getElementById('modal-dialogo-botones'); if (botones) { botones.innerHTML = ''; const b = document.createElement('button'); b.className='btn primary'; b.textContent='Cerrar'; b.onclick = function(){ UI.closeBlockingDialog(); }; botones.appendChild(b); }
        } catch (e) { console.error('startDBInit failed', e); window.UI.updateBlockingDialog('<p style="color:#c00;">Error inesperado: '+(e.message||e)+'</p>'); }
    };

    function escapeHtml(unsafe) {
        return String(unsafe).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }

    // Bindings para la sección de ajustes y botones de telemetría y mantenimiento
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const updateStatus = function() { const el = document.getElementById('telemetry-status'); if (el) el.textContent = UI.getTelemetryStatus(); };
            updateStatus();

            const btnCheck = document.getElementById('btn-check-telemetry'); if (btnCheck) btnCheck.addEventListener('click', function(){ UI.toast('Estado de telemetría: ' + UI.getTelemetryStatus(), 'info', 4000); });
            const btnRevoke = document.getElementById('btn-revoke-telemetry'); if (btnRevoke) btnRevoke.addEventListener('click', function(){ UI.clearTelemetryConsent(); updateStatus(); });
            const btnOptIn = document.getElementById('btn-optin-telemetry'); if (btnOptIn) btnOptIn.addEventListener('click', function(){ UI.setTelemetryConsent('granted'); });
            const btnOptOut = document.getElementById('btn-optout-telemetry'); if (btnOptOut) btnOptOut.addEventListener('click', function(){ UI.setTelemetryConsent('denied'); });

            const btnInit = document.getElementById('btn-init-db'); if (btnInit) btnInit.addEventListener('click', function(){ UI.showDialog({ title:'Inicializar BD', icon:'🔧', message:'¿Deseas inicializar la base de datos y datos principales? Esta operación puede tardar y bloqueará la UI temporalmente.', buttons:[ { text:'Cancelar', class:'btn', action:'close' }, { text:'Iniciar', class:'btn primary', onClick: ()=>{ UI.startDBInit(); } } ] }); });
            const btnOnly = document.getElementById('btn-only-dialogs'); if (btnOnly) btnOnly.addEventListener('click', function(){ const enabled = document.body.classList.toggle('only-dialogs'); UI.toast('Modo Solo Diálogos ' + (enabled ? 'activado' : 'desactivado'), 'info', 2400); UI._applyOnlyDialogs(enabled); });

            // AI consent bindings
            const aiStatus = document.getElementById('ai-consent-status'); if (aiStatus) aiStatus.textContent = (localStorage.getItem('ai_consent') || 'unset');
            const btnAiGrant = document.getElementById('btn-ai-consent-grant'); if (btnAiGrant) btnAiGrant.addEventListener('click', function(){ try{ localStorage.setItem('ai_consent','granted'); if (aiStatus) aiStatus.textContent = 'granted'; UI.toast('AI: almacenamiento permitido', 'success', 2200); } catch(e){ safeWarn('ui.aiConsent.grant', e); }});
            const btnAiRevoke = document.getElementById('btn-ai-consent-revoke'); if (btnAiRevoke) btnAiRevoke.addEventListener('click', function(){ try{ localStorage.removeItem('ai_consent'); if (aiStatus) aiStatus.textContent = 'unset'; UI.toast('AI: preferencia eliminada', 'info', 2200); } catch(e){ safeWarn('ui.aiConsent.revoke', e); } });
            const btnOpenAdmin = document.getElementById('btn-open-admin'); if (btnOpenAdmin) { if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') { btnOpenAdmin.style.display = 'inline-block'; btnOpenAdmin.addEventListener('click', function(){ const adminUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('admin/ai_review.php') : (window.APP_BASE ? (window.APP_BASE + 'admin/ai_review.php') : '/admin/ai_review.php'); window.open(adminUrl,'_blank'); }); } else { btnOpenAdmin.style.display = 'none'; } }
            const chkLocal = document.getElementById('chk-ai-local'); if (chkLocal) { chkLocal.checked = (localStorage.getItem('ai_mode') === 'local'); chkLocal.addEventListener('change', function(){ localStorage.setItem('ai_mode', chkLocal.checked ? 'local' : 'remote'); UI.toast('AI modo: ' + (chkLocal.checked ? 'Local' : 'Remoto'), 'info', 1800); }); }

            // AI notifications (SSE) toggle
            const notifBox = document.getElementById('ai-notif-settings');
            const chkNotif = document.getElementById('chk-ai-notif');
            const notifStatus = document.getElementById('ai-notif-status');
            if (notifBox && chkNotif && notifStatus) {
                notifBox.style.display = 'flex';
                const getStatus = () => (localStorage.getItem('ai_notifications') === 'disabled') ? 'Desactivadas' : 'Activadas';
                chkNotif.checked = (localStorage.getItem('ai_notifications') !== 'disabled');
                notifStatus.textContent = getStatus();
                chkNotif.addEventListener('change', function(){
                    localStorage.setItem('ai_notifications', chkNotif.checked ? 'enabled' : 'disabled');
                    notifStatus.textContent = getStatus();
                    UI.toast('Notificaciones AI ' + (chkNotif.checked ? 'activadas' : 'desactivadas'), 'info', 1800);
                });
            }

            // Hamburger menu behavior: toggle, close on outside click or Escape, focus management
            (function(){
                const btn = document.getElementById('nav-hamburger');
                const menu = document.getElementById('hamburger-menu');
                if (!btn || !menu) return;
                const setOpen = (open) => {
                    try {
                        if (open) {
                            menu.classList.add('show');
                            menu.setAttribute('aria-hidden','false');
                            try { btn.setAttribute('aria-expanded','true'); } catch(e){ safeWarn('ui.hamburger.setExpandedTrue', e); }
                            // focus first actionable item
                            const first = menu.querySelector('[role="menuitem"]'); if (first && typeof first.focus === 'function') first.focus();
                        } else {
                            // cerrar menú y devolver foco al botón hamburguesa
                            menu.classList.remove('show');
                            menu.setAttribute('aria-hidden','true');
                            try { btn.setAttribute('aria-expanded','false'); } catch(e){ safeWarn('ui.hamburger.setExpandedFalse', e); }
                            btn.focus();
                        }
                    } catch(e) { console.warn('Error toggling hamburger menu', e); }
                };

                // Delegated handler for modal buttons: soporta botones estáticos (markup) con atributos `data-action` o texto
                try {
                    const botones = document.getElementById('modal-dialogo-botones');
                    if (botones) {
                        botones.addEventListener('click', function(ev){
                            const target = ev.target && (ev.target.closest ? ev.target.closest('button') : ev.target);
                            if (!target || target.tagName !== 'BUTTON') return;
                            // Skip buttons that were created programmatically (they have a handler)
                            if (target.dataset && target.dataset.hasHandler) return;

                            // If button has explicit action attribute, use it
                            const act = (target.dataset && target.dataset.action) ? target.dataset.action : null;
                            const payload = (target.dataset && target.dataset.payload) ? target.dataset.payload : null;

                            try {
                                if (act === 'close') { window.UI.closeDialog && window.UI.closeDialog(); return; }
                                if (act && typeof window[act] === 'function') { window[act](payload); return; }

                                // Heuristics: button text 'eliminar' -> call eliminarRegistro si modal indica target
                                const txt = (target.textContent||'').trim().toLowerCase();
                                const modal = document.getElementById('modal-dialogo');
                                const targetType = modal && modal.dataset && modal.dataset.targetType ? modal.dataset.targetType : null;
                                const targetId = modal && modal.dataset && modal.dataset.targetId ? modal.dataset.targetId : null;

                                if ((txt === 'eliminar' || txt === 'aceptar' || txt === 'aceptar cambios') && targetType && targetId) {
                                    if (typeof eliminarRegistro === 'function') { eliminarRegistro(targetType, targetId); UI.closeDialog && UI.closeDialog(); }
                                    return;
                                }

                                // Default: if button text is 'cancelar' close
                                if (txt === 'cancelar' || txt === 'cerrar') { UI.closeDialog && UI.closeDialog(); return; }

                            } catch (e) { console.warn('modal delegated handler error', e); }
                        });
                    }
                } catch(e){ console.warn('modal delegated binding failed', e); }


                btn.addEventListener('click', function(e){ e.stopPropagation(); setOpen(!menu.classList.contains('show')); });
                // close when clicking outside
                document.addEventListener('click', function(e){ if (!menu.contains(e.target) && !btn.contains(e.target)) setOpen(false); });
                // close on Escape
                document.addEventListener('keydown', function(e){ if (e.key === 'Escape') setOpen(false); });
                // prevent clicks inside menu from closing automatically (allow buttons to work)
                menu.addEventListener('click', function(e){ e.stopPropagation(); });
            })();
        } catch (e) { console.warn('Telemetry settings binding failed', e); }
    });

})();

if (window.DEBUG) console.log('ui.js: load end', new Date().toISOString());

(function(){
    try {
        // Determine a reliable absolute URL for the ui.js self-check.
        // Prefer the script's real src if available, otherwise use <base> if present,
        // and finally fall back to a project-root absolute path.
        let src;
        if (document.currentScript && document.currentScript.src) {
            src = document.currentScript.src.split('?')[0];
        } else {
            const baseEl = document.querySelector('base');
            if (baseEl && baseEl.href) {
                src = new URL('js/ui.js', baseEl.href).toString();
                } else {
                // Fallback: known project root path used across this site — prefer APP_BASE when available
                if (window.APP_BASE) {
                    src = location.origin + window.APP_BASE.replace(/\/$/, '') + '/js/ui.js';
                } else {
                    src = location.origin + '/js/ui.js';
                }
            }
        }

        const url = src + '?t=' + Date.now();
        fetch(url, { cache: 'no-store' }).then(r => {
            if (!r.ok) { console.warn('ui.js: self-check fetch failed', r.status); return null; }
            return r.text();
        }).then(t => {
            if (!t) return;
            const hasMarker = String(t || '').trim().endsWith('// EOF - end of js/ui.js');
            if (hasMarker) {
                if (window.DEBUG) console.log('ui.js: served OK', t.length);
            } else {
                if (window.DEBUG) console.error('ui.js: served MISSING EOF marker, length', t.length);
                else console.warn('ui.js: served MISSING EOF marker', t.length);
            }
        }).catch(e => console.warn('ui.js: self-check failed', e));
    } catch(e){ safeWarn('ui.selfCheck', e); }
})();
})();
// EOF - end of js/ui.js

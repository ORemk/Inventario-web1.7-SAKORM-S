/* js/src/uiManager.js - Class-based UI manager (non-module, attaches to window.UIManager)
   This file provides a class implementation extracted from the original js/ui.js.
   It is intentionally verbose for clarity; later iterations can trim/optimize methods.
*/
(function(){
    class UIManager {
        constructor() {
            this._isEnhanced = false;
            this._shimUsed = false;
            this._wasShim = false;
            this._telemetryEndpoint = (window.CONFIG && window.CONFIG.apiBase) ? (window.CONFIG.apiBase.replace(/\/$/, '') + '/telemetry.php') : '/api/telemetry.php';
            // Ensure namespace
            window.UI = window.UI || this;
            // initialize minimal state
            this._initElements();
        }

        _initElements() {
            this.modal = document.getElementById('modal-dialogo');
            this.modalTitle = document.getElementById('modal-dialogo-titulo');
            this.modalIcon = document.getElementById('modal-dialogo-icono');
            this.modalMessage = document.getElementById('modal-dialogo-mensaje');
            this.modalButtons = document.getElementById('modal-dialogo-botones');
            this.modalInputContainer = document.getElementById('modal-dialogo-input-container');
            this.modalInput = document.getElementById('modal-dialogo-input');
        }

        // Simple helper to show toasts
        toast(message, type='info', duration=4000, actionLabel, actionCallback) {
            try {
                const el = document.createElement('div');
                el.className = 'app-toast ' + (type || 'info');
                el.setAttribute('role','status');
                el.setAttribute('aria-live','polite');
                el.innerHTML = `<div class="toast-content">${message}</div>`;
                if (actionLabel && typeof actionCallback === 'function') {
                    const action = document.createElement('button');
                    action.className = 'toast-action';
                    action.type = 'button';
                    action.setAttribute('aria-label', actionLabel);
                    action.title = actionLabel;
                    action.innerHTML = `<span class="toast-action-icon">🔍</span><span class="toast-action-label">${actionLabel}</span>`;
                    action.addEventListener('click', function(e){ try { actionCallback(); } catch(err){ console.error(err); } el.classList.remove('visible'); setTimeout(()=>el.remove(),360); });
                    action.addEventListener('keyup', function(e){ if (e.key === 'Enter' || e.key === ' ') { action.click(); } });
                    el.appendChild(action);
                }
                const closeX = document.createElement('button');
                closeX.className = 'toast-close';
                closeX.setAttribute('aria-label','Cerrar');
                closeX.innerHTML = '&times;';
                closeX.addEventListener('click', ()=> { el.classList.remove('visible'); setTimeout(()=>el.remove(),360); });
                el.appendChild(closeX);
                document.body.appendChild(el);
                setTimeout(()=> el.classList.add('visible'), 20);
                setTimeout(()=> { el.classList.remove('visible'); setTimeout(()=>el.remove(),360); }, duration);
                return el;
            } catch(e){ console.warn('Toast error', e); }
        }

        // showDialog simplified version
        showDialog(opts = {}){
            try{
                if (!this.modal) {
                    // create a minimal modal if not present
                    const modalDiv = document.createElement('div');
                    modalDiv.id = 'modal-dialogo';
                    modalDiv.style.display = 'flex';
                    modalDiv.innerHTML = `
                        <div class="modal-content">
                            <span id="modal-dialogo-icono"></span>
                            <h2 id="modal-dialogo-titulo"></h2>
                            <div id="modal-dialogo-mensaje"></div>
                            <div id="modal-dialogo-input-container" style="display:none;"><input id="modal-dialogo-input" type="text" /></div>
                            <div id="modal-dialogo-botones"></div>
                            <button id="cerrar-modal-dialogo" style="position:absolute;top:10px;right:10px;">&times;</button>
                        </div>
                    `;
                    document.body.appendChild(modalDiv);
                    // reinit
                    this._initElements();
                    // attach close
                    const closeBtn = document.getElementById('cerrar-modal-dialogo');
                    if (closeBtn) closeBtn.addEventListener('click', ()=> this.closeDialog());
                }

                this.modal.style.display = 'flex';
                const type = opts.type || 'info';
                const icons = { success: '✅', error: '❌', warn: '⚠️', info: 'ℹ️', confirm: '❓' };
                this.modalTitle.textContent = opts.title || (type === 'success' ? 'Éxito' : (type === 'error' ? 'Error' : (opts.title || '')));
                this.modalIcon.textContent = opts.icon || icons[type] || '';
                this.modalMessage.innerHTML = opts.html || (opts.message || '');
                try { this.modal.setAttribute('data-type', type); } catch(e){ safeWarn('uiManager.showDialog.setDataType', e); }
                this.modalButtons.innerHTML = '';
                if (opts.input) {
                    this.modalInputContainer.style.display = 'flex';
                    this.modalInput.value = opts.input.value || '';
                    setTimeout(()=> this.modalInput.focus(), 80);
                }
                const buttons = opts.buttons && opts.buttons.length ? opts.buttons : [{ text: opts.okText || 'Cerrar', class: 'btn primary', action: 'close' }];
                buttons.forEach(btn => this.modalButtons.appendChild(this._createButton(btn)));
                if (typeof opts.onOpen === 'function') opts.onOpen();
                return { close: ()=> this.closeDialog() };
            } catch(e){ console.warn('showDialog failed', e); }
        }

        _createButton(btn){
            const b = document.createElement('button');
            b.type = 'button';
            b.className = btn.class || 'btn';
            b.textContent = btn.text || 'OK';
            b.dataset.hasHandler = '1';
            b.addEventListener('click', ()=>{
                try {
                    if (btn.action === 'close') this.closeDialog();
                    else if (typeof btn.onClick === 'function') btn.onClick();
                    else if (typeof btn.action === 'function') btn.action();
                    else if (btn.action && window[btn.action] && typeof window[btn.action] === 'function') window[btn.action](btn.payload);
                } catch(e){ console.error('Error button action', e); }
            });
            return b;
        }

        closeDialog(){ try{ if (this.modal) this.modal.style.display='none'; } catch(e){console.warn('closeDialog failed', e);} }

        // simplified telemetry send
        _sendTelemetry(payload){
            try {
                fetch(this._telemetryEndpoint, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                }).then(res => { if (!res.ok) console.warn('Telemetry endpoint returned', res.status); return res.json().catch(()=>null); }).then(json => { if (json && json.success) console.info('Telemetry sent'); }).catch(err => console.warn('Telemetry send error', err));
            } catch(e){ console.warn('Telemetry send failed', e); }
        }

        // show blocking dialog (reused)
        showBlockingDialog(opts){
            try{
                const title = (opts && opts.title) || 'Procesando...';
                const message = (opts && opts.message) || '';
                if (!this.modal) return console.warn('blocking modal elements missing');
                const titleEl = this.modalTitle;
                const iconEl = this.modalIcon;
                const botones = this.modalButtons;
                titleEl.textContent = title;
                iconEl.textContent = (opts && opts.icon) || '⏳';
                this.modalMessage.innerHTML = message;
                botones.innerHTML = '';
                if (opts && opts.allowCancel) {
                    const btn = document.createElement('button'); btn.className='btn'; btn.textContent='Cancelar'; btn.onclick = function(){ window.UI.closeBlockingDialog(); if (typeof opts.onCancel === 'function') opts.onCancel(); };
                    botones.appendChild(btn);
                }
                if (opts && opts.onlyDialogs) this._applyOnlyDialogs(true);
                this.modal.style.display = 'flex';
                document.body.classList.add('blocking-dialog-active');
            } catch(e){ console.warn('showBlockingDialog failed', e); }
        }

        updateBlockingDialog(messageHtml){ try { if (this.modalMessage) this.modalMessage.innerHTML = messageHtml; } catch(e){console.warn('updateBlockingDialog failed', e);} }
        closeBlockingDialog(){ try { if (!this.modal) return; this.modal.style.display='none'; document.body.classList.remove('blocking-dialog-active'); this._applyOnlyDialogs(false); } catch(e){console.warn('closeBlockingDialog failed', e);} }

        _applyOnlyDialogs(enable){ try { const hideEls = ['.navbar','.main-sections','#logo-box-container','#floating-buttons-wrapper']; hideEls.forEach(sel => { document.querySelectorAll(sel).forEach(el => { el.style.display = enable ? 'none' : ''; }); }); } catch(e){console.warn('applyOnlyDialogs failed', e);} }

        // small convenience wrappers
        alertSuccess(message, opts={}){ return this.showDialog(Object.assign({ type: 'success', title: opts.title || '✅ Éxito', html: message, autoClose: opts.autoClose || 3000 }, opts)); }
        alertError(message, opts={}){ return this.showDialog(Object.assign({ type: 'error', title: opts.title || '❌ Error', html: message }, opts)); }
        confirm(message, opts={}){ return new Promise(resolve => this.showDialog(Object.assign({ title: opts.title || 'Confirmar', type: 'confirm', message: message, confirmText: opts.confirmText || 'Aceptar', onConfirm: ()=>resolve(true), onCancel: ()=>resolve(false) }, opts))); }

        // telemetry preferences
        getTelemetryStatus(){ try{ if (!window.localStorage) return 'unset'; const v = localStorage.getItem('telemetry_ui_shim'); return v ? v : 'unset'; } catch(e){ console.warn('getTelemetryStatus failed', e); return 'unset'; } }
        setTelemetryConsent(val){ try{ if (!window.localStorage) return; if (!['granted','denied'].includes(val)) return console.warn('Invalid telemetry consent value', val); localStorage.setItem('telemetry_ui_shim', val); if (val === 'granted') this._sendTelemetry({ event: 'telemetry_consent_granted', ts: new Date().toISOString(), source: 'settings_ui', url: window.location.pathname }); this.toast('Preferencia guardada: ' + val, 'success', 3000); const el = document.getElementById('telemetry-status'); if (el) el.textContent = this.getTelemetryStatus(); } catch(e){ console.warn('setTelemetryConsent failed', e); } }
        clearTelemetryConsent(){ try{ if (!window.localStorage) return; localStorage.removeItem('telemetry_ui_shim'); this.toast('Preferencia de telemetría eliminada. Se volverá a preguntar si aplica.', 'info', 4200); const el = document.getElementById('telemetry-status'); if (el) el.textContent = this.getTelemetryStatus(); } catch(e){ console.warn('clearTelemetryConsent failed', e); } }

        promptTelemetryConsentIfNeeded(){ try{ if (!window.localStorage) return; const key = 'telemetry_ui_shim'; const val = localStorage.getItem(key); const payloadBase = { event: 'ui_shim_used', ts: new Date().toISOString(), url: window.location.pathname, version: (window.CONFIG && window.CONFIG.version) || null }; if (val === 'granted') { this._sendTelemetry(payloadBase); return; } if (val === 'denied') return; this.showDialog({ title: 'Ayuda a mejorar la aplicación', icon: '📩', message: '¿Permites enviar un reporte anónimo (no incluye datos personales) para ayudarnos a mejorar la estabilidad y experiencia?', buttons: [ { text: 'No, gracias', class: 'btn', onClick: ()=>{ try{ localStorage.setItem(key, 'denied'); } catch(e){ safeWarn('uiManager.promptTelemetry.noThanks', e); } } }, { text: 'Enviar', class: 'btn primary', onClick: ()=>{ try{ localStorage.setItem(key, 'granted'); this._sendTelemetry(payloadBase); this.toast('Gracias — telemetría enviada', 'success', 3000); } catch(e){ safeWarn('uiManager.promptTelemetry.send', e); } } } ] }); } catch(e){ console.warn('promptTelemetryConsentIfNeeded failed', e); } }
    }

    // attach to window for backward compatibility
    if (!window.UIManager) window.UIManager = UIManager;
    if (!window.UI || (window.UI && window.UI._isEnhanced === false)) {
        try { window.UI = new UIManager(); } catch(e) { console.warn('Failed to initialize UIManager', e); }
    }
})();

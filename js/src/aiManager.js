// js/src/aiManager.js - Class-based AI assistant manager
(function(){
    'use strict';
    class AIManager {
        constructor(){
            this.body = (typeof document !== 'undefined') ? document.body : null;
            // runtime state
            this._ongoingController = null;
            this._lastPrompt = null;
            this._lastResponse = null;
            this._lastSentAt = 0;
            this._minDelayMs = 700; // minimal interval between messages

            // expose instance
            window.AI = window.AI || this;
            // bind public functions
            window.toggleAIChat = window.toggleAIChat || this.toggleAIChat.bind(this);
            window.toggleAIEnhancements = window.toggleAIEnhancements || this.toggleAIEnhancements.bind(this);
            window.sendAIMessage = window.sendAIMessage || this.sendAIMessage.bind(this);
            window.regenerateAIResponse = window.regenerateAIResponse || this.regenerateLastResponse.bind(this);

            // init small features
            try { this.initAINotifications(); this.ensureHelpButton(); } catch(e){ safeWarn('AIManager.constructor.init', e); }
        }

        // --- DOM helpers ---
        getChat(){ return document.getElementById('aiChatbot'); }
        getChatBody(){ return document.getElementById('aiChatBody'); }
        getInput(){ return document.getElementById('aiChatInput'); }

        shouldAutoScroll(container, threshold = 48){ if (!container) return true; return (container.scrollTop + container.clientHeight + threshold) >= container.scrollHeight; }
        autoScrollToBottom(container){ try { if (!container) return; if (typeof container.scrollTo === 'function') { container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' }); return; } container.scrollTop = container.scrollHeight; } catch(e){ safeWarn('AIManager.autoScrollToBottom', e); } }

        // simplified ensureChatScrollbar (keeps original behavior curtailed)
        ensureChatScrollbar(){ try { const chatBody = this.getChatBody(); if (!chatBody) return false; chatBody.style['-webkit-overflow-scrolling'] = 'touch'; chatBody.style.overflowY = 'auto'; chatBody.style.maxHeight = chatBody.style.maxHeight || '360px'; return true; } catch(e){ console.warn('ensureChatScrollbar failed', e); return false; } }

        // Render simple bot reply (kept minimal to avoid duplicating huge original logic)
        botReply(html){ try { const container = this.getChatBody(); if (!container) return; const msg = document.createElement('div'); msg.className = 'ai-chat-msg ai-chat-msg-bot'; msg.innerHTML = html || ''; container.appendChild(msg); if (this.shouldAutoScroll(container)) this.autoScrollToBottom(container); return msg; } catch(e){ console.warn('botReply failed', e); } }

        async sendToBackend(text, opts = {}){
            try {
                const session_id = await this.ensureSession();
                const consent = localStorage.getItem('ai_consent') === 'granted';
                const payload = { prompt: text, session_id: session_id, consent: consent, mode: localStorage.getItem('ai_mode') || null };

                // Support AbortController via opts.signal and a timeout fallback
                const controller = opts.signal ? null : new AbortController();
                const signal = opts.signal || (controller && controller.signal);
                const timeoutMs = typeof opts.timeout === 'number' ? opts.timeout : 25000;
                let timeoutId = null;
                if (controller) timeoutId = setTimeout(()=>{ try{ controller.abort(); }catch(e){ safeWarn('AIManager.timeout.abort', e); } }, timeoutMs);

                const fetchOpts = { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) };
                if (signal) fetchOpts.signal = signal;

                const url = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_assistant.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'api/ai_assistant.php');
                const res = await fetch(url, fetchOpts);
                if (controller && timeoutId) clearTimeout(timeoutId);
                if (!res.ok) return { error: 'backend_error', status: res.status };
                const json = await res.json().catch(()=>null);
                return json || null;
            } catch(e){
                if (e && e.name === 'AbortError') return { error: 'aborted' };
                console.warn('sendToBackend failed', e);
                return { error: 'exception', exception: (e && e.message) ? e.message : String(e) };
            }
        }

        async ensureSession(){ let s = localStorage.getItem('ai_session_id'); if (!s) { s = 's_' + Math.random().toString(36).slice(2) + Date.now().toString(36); localStorage.setItem('ai_session_id', s); } return s; }

        async sendFeedback(conversationId, rating){ try { const fbUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_feedback.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'api/ai_feedback.php'); const res = await fetch(fbUrl, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ conversation_id: conversationId, rating: rating, session_id: localStorage.getItem('ai_session_id') || null }) }); const json = await res.json().catch(()=>null); return json || null; } catch(e){ console.warn('sendFeedback failed', e); return null; } }

        // Streaming-enabled send: uses fetch stream reader and calls onChunk for each data event
        async sendToBackendStream(text, opts = {}){
            try {
                const payload = { prompt: text, session_id: await this.ensureSession(), consent: (localStorage.getItem('ai_consent') === 'granted'), mode: localStorage.getItem('ai_mode') || null };
                const controller = opts.signal ? { signal: opts.signal } : new AbortController();
                const fetchOpts = { method: 'POST', body: JSON.stringify(payload), headers: {'Content-Type':'application/json'} };
                if (controller.signal) fetchOpts.signal = controller.signal;

                const streamUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_assistant_stream.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'api/ai_assistant_stream.php');
                const res = await fetch(streamUrl, fetchOpts);
                if (!res.ok) return null;

                const reader = res.body.getReader();
                const decoder = new TextDecoder('utf-8');
                let done = false;
                let accumulated = '';
                while (!done) {
                    const { value, done: rdone } = await reader.read();
                    if (rdone) break;
                    const chunk = decoder.decode(value, { stream: true });
                    accumulated += chunk;
                    // parse SSE-like "data: ...\n\n" frames
                    const parts = accumulated.split(/\n\n/);
                    accumulated = parts.pop(); // remainder
                    for (let p of parts) {
                        const lines = p.split(/\n/);
                        for (let ln of lines) {
                            ln = ln.trim();
                            if (!ln) continue;
                            if (ln.indexOf('data:') === 0) {
                                const payloadText = ln.slice(5).trim();
                                if (payloadText === '[DONE]') {
                                    done = true; break;
                                }
                                // try parse JSON; if fails, treat as raw text
                                try {
                                    const parsed = JSON.parse(payloadText);
                                    if (parsed && parsed.text) {
                                        if (typeof opts.onChunk === 'function') opts.onChunk(parsed.text);
                                    } else {
                                        // fallback: forward entire string
                                        if (typeof opts.onChunk === 'function') opts.onChunk(payloadText);
                                    }
                                } catch(e) {
                                    if (typeof opts.onChunk === 'function') opts.onChunk(payloadText);
                                }
                            } else {
                                // not prefixed: forward
                                if (typeof opts.onChunk === 'function') opts.onChunk(ln);
                            }
                        }
                    }
                }
                // final flush of remainder if any
                if (accumulated && typeof opts.onChunk === 'function') opts.onChunk(accumulated);

                return { streamed: true, text: (accumulated || '') };
            } catch(e){
                if (e && e.name === 'AbortError') return { error: 'aborted' };
                console.warn('sendToBackendStream failed', e);
                return null;
            }
        }

        toggleAIChat(){ const chat = this.getChat(); if (!chat) return console.warn('AI Chat element not found'); const visible = chat.classList.contains('active'); chat.classList.toggle('active', !visible); chat.style.display = chat.classList.contains('active') ? 'block' : 'none'; if (chat.classList.contains('active')) { const input = this.getInput(); input && input.focus(); } }
        toggleAIEnhancements(){ if (!this.body) return; this.body.classList.toggle('ai-enhanced'); window.UI && UI.toast && UI.toast('Modo Futurista ' + (this.body.classList.contains('ai-enhanced') ? 'activado' : 'desactivado'), 'info'); }

        // Abort any ongoing AI backend request
        abortOngoingRequest(){ try { if (this._ongoingController && typeof this._ongoingController.abort === 'function') { this._ongoingController.abort(); } this._ongoingController = null; } catch(e){ console.warn('abortOngoingRequest failed', e); } }

        // Regenerate the last response using the same prompt
        async regenerateLastResponse(){ try { if (!this._lastPrompt) { UI && UI.toast && UI.toast('No hay prompt previo para regenerar','warn'); return; } UI && UI.toast && UI.toast('Regenerando respuesta...', 'info', 1400); // keep UI feedback simple
                // abort previous and call sendAIMessage with original prompt
                this.abortOngoingRequest(); await this.sendAIMessage(this._lastPrompt); } catch(e){ console.warn('regenerate failed', e); UI && UI.toast && UI.toast('No se pudo regenerar','error'); } }

        // main public send function (kept simpler than original but uses backend when available)
        async sendAIMessage(event){
            try {
                if (event && typeof event.preventDefault === 'function') event.preventDefault();
                let text = '';
                if (event && event.target && event.target.value) text = String(event.target.value).trim();
                else if (typeof event === 'string') text = event.trim();
                const inputEl = this.getInput(); if (!text && inputEl) text = inputEl.value && inputEl.value.trim(); if (!text) return false;

                // Simple rate-limit to avoid accidental spam
                const now = Date.now();
                if (now - (this._lastSentAt || 0) < (this._minDelayMs || 700)) {
                    UI && UI.toast && UI.toast('Espera un momento antes de enviar otra consulta.', 'warn');
                    return false;
                }
                this._lastSentAt = now;
                this._lastPrompt = text;

                // show user message
                const userMsg = document.createElement('div'); userMsg.className = 'ai-chat-msg ai-chat-msg-user'; userMsg.textContent = text; const bodyEl = this.getChatBody(); let typingEl = null; if (bodyEl) { const doScroll = this.shouldAutoScroll(bodyEl); bodyEl.appendChild(userMsg); typingEl = document.createElement('div'); typingEl.className = 'ai-chat-msg ai-chat-msg-bot ai-typing typing-dots'; typingEl.textContent = 'Escribiendo'; bodyEl.appendChild(typingEl); if (doScroll) this.autoScrollToBottom(bodyEl); }

                // Disable input while processing
                if (inputEl) { inputEl.disabled = true; inputEl.classList.add('disabled'); }

                // Abort any ongoing request
                try { if (this._ongoingController && typeof this._ongoingController.abort === 'function') { this._ongoingController.abort(); } } catch(e){ safeWarn('AIManager.abortOngoingRequest.innerAbort', e); }
                this._ongoingController = new AbortController();

                // Try streaming backend first (prefer streaming when available)
                const streamingRes = await this.sendToBackendStream(text, { signal: this._ongoingController.signal, timeout: 30000, onChunk: (chunkText) => {
                    try {
                        // Append chunk to typing/bot element in real-time
                        if (bodyEl && typingEl) {
                            if (!typingEl.__streamBuf) typingEl.__streamBuf = '';
                            typingEl.__streamBuf += chunkText;
                            typingEl.innerHTML = typingEl.__streamBuf;
                            if (this.shouldAutoScroll(bodyEl)) this.autoScrollToBottom(bodyEl);
                        }
                    } catch(e){ console.warn('onChunk handler failed', e); }
                }}).catch(()=>null);

                // remove typing
                if (bodyEl && typingEl) { try { bodyEl.removeChild(typingEl); } catch(e){ safeWarn('AIManager.removeTypingEl', e); } }
                try { this.ensureChatScrollbar(); } catch(e){ safeWarn('AIManager.ensureChatScrollbar.call', e); }

                // Re-enable input
                if (inputEl) { inputEl.disabled = false; inputEl.classList.remove('disabled'); inputEl.focus(); }

                if (streamingRes && streamingRes.streamed) {
                    // Completed streaming successfully and streamingRes.text contains the final text (if returned)
                    this._lastResponse = streamingRes.text || (typingEl && typingEl.__streamBuf) || '';
                    const bot = this.botReply(this._lastResponse);

                    // Add quick controls
                    try {
                        if (bot && bot.classList) {
                            const controls = document.createElement('div'); controls.className = 'ai-response-controls'; controls.style.marginTop = '8px';
                            const btnCopy = document.createElement('button'); btnCopy.className='btn small'; btnCopy.textContent='Copiar'; btnCopy.addEventListener('click', ()=>{ try { navigator.clipboard.writeText(this._lastResponse); UI && UI.toast && UI.toast('Respuesta copiada', 'success'); } catch(e){ UI && UI.toast && UI.toast('No se pudo copiar','error'); } });
                            const btnRegen = document.createElement('button'); btnRegen.className='btn small'; btnRegen.textContent='Regenerar'; btnRegen.addEventListener('click', ()=>{ this.regenerateLastResponse(); });
                            controls.appendChild(btnCopy); controls.appendChild(btnRegen);
                            bot.appendChild(controls);
                        }
                    } catch(e){ console.warn('add controls failed', e); }

                    return false;
                }

                // If streaming wasn't available or failed, fallback to normal non-streaming request
                const backend = await this.sendToBackend(text, { signal: this._ongoingController.signal, timeout: 25000 });

                if (!backend) {
                    this.botReply('El servicio de asistencia no respondió. Intenta de nuevo más tarde.');
                    return false;
                }

                if (backend.error) {
                    if (backend.error === 'aborted') {
                        this.botReply('<em>Solicitud cancelada.</em>');
                        return false;
                    }
                    this.botReply('<em>Error al contactar el asistente.</em>');
                    return false;
                }

                if (backend && backend.data && backend.data.reply) {
                    // Save last response for regenerate/copy
                    this._lastResponse = backend.data.reply;
                    const bot = this.botReply(backend.data.reply);

                    // If backend returned structured product results, render a small table and quick actions
                    try {
                        if (backend.data.products && Array.isArray(backend.data.products) && backend.data.products.length) {
                            const prodList = backend.data.products.slice(0,12);
                            const table = document.createElement('table');
                            table.style.width = '100%';
                            table.style.borderCollapse = 'collapse';
                            table.style.marginTop = '8px';
                            table.innerHTML = '<thead><tr><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Código</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Nombre</th><th style="text-align:right;padding:6px;border-bottom:1px solid #eee">Cant.</th><th style="text-align:right;padding:6px;border-bottom:1px solid #eee">Precio</th><th style="padding:6px;border-bottom:1px solid #eee"></th></tr></thead>';
                            const body = document.createElement('tbody');
                            prodList.forEach(p => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `<td style="padding:6px;border-top:1px solid #fafafa">${(p.codigo||'')}</td><td style="padding:6px;border-top:1px solid #fafafa">${(p.nombre||'')}</td><td style="padding:6px;text-align:right;border-top:1px solid #fafafa">${(p.cantidad||0)}</td><td style="padding:6px;text-align:right;border-top:1px solid #fafafa">${(typeof p.precio !== 'undefined' ? ('$'+parseFloat(p.precio).toFixed(2)) : 'N/A')}</td><td style="padding:6px;border-top:1px solid #fafafa"><button class="btn small ai-open-prod" data-id="${p.id}">Ver</button></td>`;
                                body.appendChild(tr);
                            });
                            table.appendChild(body);
                            if (bot) bot.appendChild(table);
                            // Attach delegated listeners for 'Ver' buttons
                            setTimeout(()=>{
                                const buttons = (bot && bot.querySelectorAll) ? bot.querySelectorAll('.ai-open-prod') : [];
                                buttons.forEach(b => {
                                    b.addEventListener('click', (ev) => {
                                        const id = ev.target && ev.target.dataset && ev.target.dataset.id ? ev.target.dataset.id : null;
                                        if (id && typeof window.editarProducto === 'function') {
                                                    try { window.editarProducto(parseInt(id)); window.toggleAIChat && window.toggleAIChat(); } catch(e){ console.warn('open product from AI failed', e); }
                                                } else if (id && typeof window.cargarProductos === 'function') {
                                                    try { window.cargarProductos(); } catch(e){ safeWarn('AIManager.openProd.cargarProductos', e); }
                                                }
                                    });
                                });
                            }, 80);
                        }
                    } catch(e){ console.warn('render backend products failed', e); }

                    // If suggest_save, offer quick action for admins/local
                    if (backend.data.suggest_save) {
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            // show a non-blocking toast with action to save as rule
                            try {
                                const _t = UI && UI.toast ? UI.toast('¿Guardar como regla?','info',8000,'Guardar', async ()=>{
                                    try {
                                        const pattern = (this._lastPrompt || '').slice(0,160).replace(/[^\w\s]/g,'').trim();
                                        const resp = (this._lastResponse || '').slice(0,2000);
                                        const promoteUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_promote.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'api/ai_promote.php');
                                        const r = await fetch(promoteUrl, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ pattern: pattern, response: resp, created_by: 'admin' }) });
                                        const jr = await r.json().catch(()=>null);
                                        if (jr && jr.success) UI && UI.toast && UI.toast('Regla guardada: ID ' + (jr.id||'n/a'),'success'); else UI && UI.showDialog && UI.showDialog({ type:'error', title:'Error', message: 'No se pudo guardar la regla.' });
                                    } catch(e){ UI && UI.showDialog && UI.showDialog({ type:'error', title:'Error', message: 'Excepción: ' + (e && e.message ? e.message : e) }); }
                                }) : null;
                            } catch(e){ console.warn('suggest_save toast failed', e); }
                        } else {
                            // For non-local hosts, signal admin panel
                            if (UI && UI.toast) UI.toast('Sugerencia: se detectó patrón repetible. Revisa el panel AI (admin local).', 'info', 6000);
                        }
                    }

                    // Add quick controls after bot response (copy / regenerate)
                    try {
                        if (bot && bot.classList) {
                            const controls = document.createElement('div'); controls.className = 'ai-response-controls';
                            controls.style.marginTop = '8px';
                            const btnCopy = document.createElement('button'); btnCopy.className='btn small'; btnCopy.textContent='Copiar'; btnCopy.addEventListener('click', ()=>{ try { navigator.clipboard.writeText(this._lastResponse); UI && UI.toast && UI.toast('Respuesta copiada', 'success'); } catch(e){ UI && UI.toast && UI.toast('No se pudo copiar','error'); } });
                            const btnRegen = document.createElement('button'); btnRegen.className='btn small'; btnRegen.textContent='Regenerar'; btnRegen.addEventListener('click', ()=>{ this.regenerateLastResponse(); });
                            controls.appendChild(btnCopy); controls.appendChild(btnRegen);
                            bot.appendChild(controls);
                        }
                    } catch(e){ console.warn('add controls failed', e); }

                    return false;
                }

                // Simple local fallbacks
                const low = text.toLowerCase();
                if (/\b(registrar|agregar)\b/.test(low) && /producto/.test(low)) {
                    this.botReply('<strong>Registrar un producto — pasos rápidos</strong>:<br/>1) Ve a Registrar Producto. 2) Completa Nombre, Categoría, Cantidad, Precio y Costo. 3) Agrega foto (opcional). 4) Presiona <strong>Agregar Producto</strong>.');
                    return false;
                }

                if (/\b(buscar|productos|listado|inventario|stock)\b/.test(low)) { this.botReply('<strong>Ver inventario</strong>: Ve a la sección Productos o Inventario. Usa filtros por nombre o categoría y ordena por stock.'); return false; }

                this.botReply('Lo siento, no entendí completamente. Puedo ayudarte a registrar productos, ventas o mostrar guías.');
                return false;
            } catch (err) {
                console.warn('sendAIMessage failed', err);
                window.UI && window.UI.toast && window.UI.toast('Error en asistente: ' + (err.message||err), 'error');
                // Re-enable input in error cases
                try { const inputEl = this.getInput(); if (inputEl) { inputEl.disabled = false; inputEl.classList.remove('disabled'); } } catch(e){ safeWarn('AIManager.inputReenable', e); }
                return false;
            }
        }

        initAINotifications(){
            try {
                const enabled = localStorage.getItem('ai_notifications') !== 'disabled';
                const consent = localStorage.getItem('ai_consent') === 'granted';
                if (!enabled || typeof EventSource === 'undefined' || !consent) return;
                const esUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_notifications.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'api/ai_notifications.php');
                const es = new EventSource(esUrl);
                es.addEventListener('promotion', (e)=>{ try { const d = JSON.parse(e.data); const pattern = (d.payload && d.payload.pattern) ? d.payload.pattern : ''; UI && UI.toast && UI.toast('Nueva regla promovida automáticamente', 'info', 6000); UI && UI.showDialog && UI.showDialog({ title: 'Nueva regla promovida', icon: '✨', message: 'Se creó la regla automática: <pre style="white-space:pre-wrap;">'+ (pattern) + '</pre>', buttons: [ { text: 'Ir al panel AI', class: 'btn primary', onClick: function(){ const adminUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('admin/ai_review.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'admin/ai_review.php'); window.open(adminUrl,'_blank'); } }, { text: 'Cerrar', class: 'btn', action: 'close' } ] }); } catch(err){ console.warn('invalid promotion payload', err); } });
                es.onerror = function(err){ console.warn('AI notifications connection error', err); try{ es.close(); }catch(e){ safeWarn('ai.notifications.es.close', e); } setTimeout(()=>{ try{ const mgr = window.AI || null; if (mgr && typeof mgr.initAINotifications === 'function') mgr.initAINotifications(); }catch(e){ safeWarn('ai.notifications.es.retry', e); } }, 5000); };
            } catch(e){ console.warn('initAINotifications failed', e); }
        }

        ensureHelpButton(){ try { const chat = this.getChat(); if (chat) { const hdr = chat.querySelector('.ai-chat-header') || chat; if (hdr && !hdr.querySelector('.ai-help-btn')) { const hb = document.createElement('button'); hb.type = 'button'; hb.className = 'ai-help-btn btn small'; hb.setAttribute('aria-label','Cómo usar el asistente'); hb.textContent = '¿Cómo usar?'; hb.addEventListener('click', ()=>{ UI && UI.showDialog && UI.showDialog({ title: 'Ayuda del asistente', icon: '💡', html: '<p>Usa <strong>Desglosar</strong> para dividir respuestas largas en partes y navega con <strong>Siguiente</strong>/<strong>Anterior</strong> o con las flechas del teclado.</p>', buttons: [ { text: 'Cerrar', class: 'btn', action: 'close' } ] }); }); hdr.appendChild(hb); } } } catch(e){ console.warn('AI help button creation failed', e); } }
    }

    if (!window.AIManager) window.AIManager = AIManager;
    if (!window.AI || (window.AI && typeof window.AI.sendAIMessage !== 'function')) {
        try { window.AI = new AIManager(); } catch(e) { console.warn('AIManager init failed', e); }
    }
})();

// ai.js - simplified and robust loader for AI features

// lightweight safe logger used across ai.* helpers
if (typeof window.safeWarn === 'undefined') {
  window.safeWarn = function(ctx, err){
    try { console.warn(ctx, err); } catch(_) { /* noop */ }
  };
}

(function(){
  // If a full AI manager is already present, do nothing
  if (window.AI && typeof window.AI.sendAIMessage === 'function') return;

  function installFallback(){
    // minimal, safe fallbacks
    window.toggleAIChat = window.toggleAIChat || function(){
      const chat = document.getElementById('aiChatbot'); if (!chat) return;
      const visible = chat.classList.contains('active');
      chat.classList.toggle('active', !visible);
      chat.style.display = chat.classList.contains('active') ? 'block' : 'none';
    };

    window.sendAIMessage = window.sendAIMessage || async function(evt){
      try{
        const text = (evt && evt.target && evt.target.value) ? String(evt.target.value).trim() : (typeof evt === 'string' ? evt : '');
        if (!text) return false;
        const bodyEl = (typeof getChatBody === 'function') ? getChatBody() : null;
        if (bodyEl) {
          const userMsg = document.createElement('div'); userMsg.className='ai-chat-msg ai-chat-msg-user'; userMsg.textContent=text; bodyEl.appendChild(userMsg);
        }
        // Simple local response
        const reply = 'Asistente: lo siento, el servicio AI no está disponible ahora. Intenta más tarde.';
        if (bodyEl) {
          const bot = document.createElement('div'); bot.className='ai-chat-msg ai-chat-msg-bot'; bot.innerHTML = reply; bodyEl.appendChild(bot);
        }
        return { data: { reply } };
      } catch (e){ console.warn('AI fallback failed', e); return false; }
    };

    try{ if (window.sendAIMessage && typeof window.sendAIMessage === 'function') window.sendAIMessage.__isAIFallback = true; } catch(e){ safeWarn('ai.installFallback.markFallback', e); }
  }

  // Provide fallbacks immediately
  installFallback();

  // Try to load the richer aiManager if available
  try {
  const s = document.createElement('script');
  // Resolve base path: prefer <base href> if present, otherwise derive from location.pathname
    (function(){
    function resolveBase(){
      try{
        const baseEl = document.querySelector('base');
        if (baseEl && baseEl.getAttribute('href')) return baseEl.getAttribute('href').replace(/\/$/, '');
      }catch(e){ safeWarn('ai.resolveBase.baseQuery', e); }
      // Fallback: remove filename segment when present
      const p = location.pathname || '/';
      const parts = p.split('/').filter(Boolean);
      if (parts.length && parts[parts.length-1].includes('.')) parts.pop();
      return '/' + parts.join('/');
    }
    const base = resolveBase();
    const prefix = (base.startsWith('http') ? base : (location.protocol + '//' + location.host + base));
    s.src = prefix.replace(/\/$/, '') + '/js/src/aiManager.js?t=' + Date.now();
  })();
    s.async = false; s.defer = false;
    s.onload = function(){ console.info('ai: loaded js/src/aiManager.js'); };
    s.onerror = function(){ console.warn('ai: failed to load js/src/aiManager.js'); installFallback(); };
    document.head.appendChild(s);
  } catch(e){ console.warn('ai loader failed', e); }
})();

// toggleAIEnhancements helper
if (typeof window.toggleAIEnhancements === 'undefined') {
  window.toggleAIEnhancements = function(){
    const body = document.body; if (!body) return;
    body.classList.toggle('ai-enhanced');
    window.UI && UI.toast && UI.toast('Modo Futurista ' + (body.classList.contains('ai-enhanced') ? 'activado' : 'desactivado'), 'info');
  };
}

// Lightweight SSE notifications initializer (non-blocking)
(function initAINotifications(){
  try {
    const enabled = localStorage.getItem('ai_notifications') !== 'disabled';
    const consent = localStorage.getItem('ai_consent') === 'granted';
    if (!enabled || typeof EventSource === 'undefined' || !consent) return;
    const esUrl = (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_notifications.php') : ((window.APP_BASE ? window.APP_BASE : '/') + 'api/ai_notifications.php');
    const es = new EventSource(esUrl);
    es.addEventListener('promotion', function(e){
      try {
        const d = JSON.parse(e.data || '{}');
        const _pattern = (d.payload && d.payload.pattern) ? d.payload.pattern : '';
        UI && UI.toast && UI.toast('Nueva regla promovida automáticamente', 'info', 6000);
      } catch(err){ console.warn('invalid promotion payload', err); }
    });
    es.onerror = function(err){ console.warn('AI notifications connection error', err); try{ es.close(); }catch(e){ safeWarn('ai.notifications.es.close', e); } };
  } catch(e){ safeWarn('ai.notifications.init', e); }
})();

// Add small help button if chat exists
try {
  const chat = (typeof getChat === 'function') ? getChat() : document.getElementById('aiChatbot');
  if (chat) {
    const hdr = chat.querySelector('.ai-chat-header') || chat;
    if (hdr && !hdr.querySelector('.ai-help-btn')) {
      const hb = document.createElement('button');
      hb.type='button'; hb.className='ai-help-btn btn small'; hb.setAttribute('aria-label','Cómo usar el asistente'); hb.textContent='¿Cómo usar?';
      hb.addEventListener('click', function(){
        UI && UI.showDialog && UI.showDialog({ title:'Ayuda del asistente', icon:'💡', message: 'Usa Desglosar para dividir respuestas largas y navega con Siguiente/Anterior.' });
      });
      hdr.appendChild(hb);
    }
  }
} catch(e){ console.warn('AI help button creation failed', e); }



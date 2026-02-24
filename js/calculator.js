// Calculator UI and toggle
(function(){
    let stack = '';
    let history = [];

    function getDisplay() { return document.getElementById('calcDisplay'); }
    function getModal() { return document.getElementById('calculatorModal'); }
    function updateDisplay() { const d = getDisplay(); if (d) d.value = stack || '0'; }

    if (typeof window.calcPress === 'undefined') {
        window.calcPress = function(v){
            stack = (stack === '0') ? String(v) : (stack || '') + String(v);
            updateDisplay();
        };
    }

    if (typeof window.calcClear === 'undefined') {
        window.calcClear = function(){ stack = ''; updateDisplay(); };
    }

    function renderHistory() {
        try {
            const list = document.getElementById('historyList');
            if (!list) return;
            list.innerHTML = history.map(h => {
                const div = document.createElement('div');
                div.textContent = h;
                div.style.padding = '6px 8px';
                div.style.background = '#fffbe6';
                div.style.borderRadius = '6px';
                div.style.color = '#ff6f61';
                div.style.fontWeight = '600';
                return div.outerHTML;
            }).join('');
        } catch(e){ /* noop */ }
    }

    if (typeof window.calcEquals === 'undefined') {
        window.calcEquals = function(){
            try {
                if (!/^[0-9+\-*/.\s]+$/.test(stack)) throw new Error('Input inválido');
                const res = Function('return ' + stack)();
                // push to history (most recent first)
                try { history.unshift(stack + ' = ' + String(res)); if (history.length > 20) history.length = 20; } catch(e){ void 0; }
                renderHistory();
                stack = String(res);
                updateDisplay();
            } catch (e) {
                console.error('Calc error', e);
                window.UI && window.UI.toast && window.UI.toast('Error en calculadora', 'error');
            }
        };
    }

    if (typeof window.toggleCalculator === 'undefined') {
        window.toggleCalculator = function(){
            const modal = getModal();
            if (!modal) return console.warn('Calculator modal no encontrado');
            const visible = modal.classList.contains('active');
            modal.classList.toggle('active', !visible);
            modal.style.display = modal.classList.contains('active') ? 'flex' : 'none';
            if (modal.classList.contains('active')) { stack = ''; updateDisplay(); }
        };
    }

    // Copy current display value to clipboard
    window.copiarResultado = function(){
        try {
            const d = getDisplay(); if (!d) return;
            const txt = (d.value || '').toString();
            if (!txt) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(function(){ window.UI && window.UI.toast && window.UI.toast('Resultado copiado', 'success'); }).catch(()=>{ window.UI && window.UI.toast && window.UI.toast('No se pudo copiar', 'error'); });
            } else {
                // fallback
                const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); window.UI && window.UI.toast && window.UI.toast('Resultado copiado', 'success'); } catch(e){ window.UI && window.UI.toast && window.UI.toast('No se pudo copiar', 'error'); } ta.remove();
            }
        } catch(e){ console.warn('copiarResultado failed', e); }
    };

    window.calcClearHistory = function(){
        try { history = []; renderHistory(); const el = document.getElementById('calcHistory'); if (el) el.style.display = 'none'; window.UI && window.UI.toast && window.UI.toast('Historial limpiado', 'info'); } catch(e){ void 0; }
    };

    window.toggleCalcHistory = function(){
        try {
            const el = document.getElementById('calcHistory'); if (!el) return;
            const btn = document.getElementById('btnToggleHistory');
            const isHidden = !el.classList.contains('open');

            if (isHidden) {
                // show with animation
                el.style.display = 'block';
                // allow the browser to paint before adding the class
                requestAnimationFrame(function(){
                    el.classList.add('open');
                    if (btn) { btn.classList.add('open'); btn.setAttribute('aria-pressed', 'true'); btn.setAttribute('aria-expanded', 'true'); }
                });
            } else {
                // hide with animation: remove class, then after transition hide from layout
                el.classList.remove('open');
                if (btn) { btn.classList.remove('open'); btn.setAttribute('aria-pressed', 'false'); btn.setAttribute('aria-expanded', 'false'); }
                // wait for CSS transition to complete before setting display none
                setTimeout(function(){ try { if (el && !el.classList.contains('open')) el.style.display = 'none'; } catch(e){ void 0; } }, 260);
            }
        } catch(e) { console.warn('toggleCalcHistory error', e); }
    };

    // Bind toolbar buttons if present once DOM is ready
    document.addEventListener('DOMContentLoaded', function(){
        try {
            const btnCopy = document.getElementById('btnCopyResult');
            const btnHist = document.getElementById('btnToggleHistory');
            const btnClear = document.getElementById('btnClearHistory');
            if (btnCopy && !btnCopy._bound) { btnCopy.addEventListener('click', function(){ window.copiarResultado(); }); btnCopy._bound = true; }
            if (btnHist && !btnHist._bound) { btnHist.addEventListener('click', function(){ window.toggleCalcHistory(); }); btnHist._bound = true; }
            if (btnClear && !btnClear._bound) { btnClear.addEventListener('click', function(){ window.calcClearHistory(); }); btnClear._bound = true; }
            renderHistory();
        } catch(e){ console.warn('calculator toolbar bind failed', e); }
    });
})();

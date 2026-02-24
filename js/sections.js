// js/sections.js - accordion + helpers
window.Sections = window.Sections || {};

// safeWarn fallback
function safeWarn(tag, e){ try{ console.warn(tag, e); }catch(_){ /* ignore */ } }

(function(){
	const STORAGE_KEY = 'sections_visibility_v1';
	function readState(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e){ safeWarn('sections.readState', e); return {}; } }
	function writeState(obj){ try { localStorage.setItem(STORAGE_KEY, JSON.stringify(obj)); } catch(e){ safeWarn('sections.writeState', e); } }

	function animateExpand(el){
		if (!el) return Promise.resolve();
		return new Promise((resolve)=>{
			if (el._animEnd){ el.removeEventListener('transitionend', el._animEnd); el._animEnd = null; }
			el.style.display = 'block';
			const target = el.scrollHeight;
			el.style.overflow = 'hidden';
			el.style.maxHeight = '0px';
			el.style.opacity = '0';
			void el.offsetHeight;
			const duration = 300;
			requestAnimationFrame(()=>{
				el.style.transition = 'max-height ' + duration + 'ms ease, opacity ' + (duration-60) + 'ms ease';
				el.style.maxHeight = target + 'px';
				el.style.opacity = '1';
			});
			el._animEnd = function(ev){ if (ev.propertyName !== 'max-height') return; cleanup(); };
			function cleanup(){ el.style.removeProperty('max-height'); el.style.removeProperty('transition'); el.style.removeProperty('overflow'); el._animEnd && el.removeEventListener('transitionend', el._animEnd); el._animEnd = null; resolve(); }
			el.addEventListener('transitionend', el._animEnd);
		});
	}

	function animateCollapse(el){
		if (!el) return Promise.resolve();
		return new Promise((resolve)=>{
			if (el._animEnd){ el.removeEventListener('transitionend', el._animEnd); el._animEnd = null; }
			const duration = 300;
			el.style.overflow = 'hidden';
			el.style.maxHeight = el.scrollHeight + 'px';
			void el.offsetHeight;
			requestAnimationFrame(()=>{
				el.style.transition = 'max-height ' + duration + 'ms ease, opacity ' + (duration-60) + 'ms ease';
				el.style.maxHeight = '0px';
				el.style.opacity = '0';
			});
			el._animEnd = function(ev){ if (ev.propertyName !== 'max-height') return; cleanup(); };
			function cleanup(){ el.style.removeProperty('max-height'); el.style.removeProperty('transition'); el.style.removeProperty('overflow'); el._animEnd && el.removeEventListener('transitionend', el._animEnd); el._animEnd = null; resolve(); }
			el.addEventListener('transitionend', el._animEnd);
		});
	}

	function findContent(id){
		if (!id) return null;
		return document.getElementById(id + '-content') || document.getElementById(id) || null;
	}

	// Public API: toggle by section id (without -content)
	window.Sections.toggle = function(sectionId){
		try{
			const content = findContent(sectionId);
			if (!content) return;
			const visual = content.closest('section') || content.closest('.section') || content;
			const isOpen = visual && visual.classList && visual.classList.contains('is-open');
			const btn = document.querySelector('.btn-toggle-section[aria-controls="' + (sectionId + '-content') + '"]') || document.querySelector('.btn-toggle-section[aria-controls="' + sectionId + '"]');

			if (isOpen){
				if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-chevron-down"></i> Mostrar'; btn.setAttribute('aria-expanded','false'); }
				animateCollapse(content).then(()=>{ visual.classList.remove('is-open'); if (btn) try{ btn.disabled = false; }catch(e){ /* ignore */ } }).catch(()=>{ if (btn) try{ btn.disabled = false; }catch(e){ /* ignore */ } });
				const s = readState(); s[sectionId + '-content'] = true; writeState(s);
			} else {
				if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar'; btn.setAttribute('aria-expanded','true'); }
				visual.classList.add('is-open');
				animateExpand(content).then(()=>{ if (btn) try{ btn.disabled = false; }catch(e){ /* ignore */ } }).catch(()=>{ if (btn) try{ btn.disabled = false; }catch(e){ /* ignore */ } });
				const s = readState(); s[sectionId + '-content'] = false; writeState(s);
			}
		} catch(e){ safeWarn('Sections.toggle', e); }
	};

	// Initialize: connect .btn-toggle-section buttons
	function init(){
		const saved = readState();
		document.querySelectorAll('.btn-toggle-section').forEach(btn => {
			const target = (btn.getAttribute('aria-controls') || btn.dataset.target || '').replace(/\s+$/,'');
			if (!target) return;
			const id = target.replace(/-content$/,'');
			const content = findContent(id);
			const visual = content ? (content.closest('section') || content.closest('.section') || content) : null;
			const key = id + '-content';
			const collapsed = !!saved[key];
			if (visual && content){
				if (collapsed){
					visual.classList.remove('is-open');
					content.style.maxHeight = '0px'; content.style.opacity = '0';
					btn.innerHTML = '<i class="fas fa-chevron-down"></i> Mostrar'; btn.setAttribute('aria-expanded','false');
				} else {
					visual.classList.add('is-open');
					content.style.opacity = '1'; content.style.maxHeight = 'none';
					btn.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar'; btn.setAttribute('aria-expanded','true');
				}
			}
			btn.addEventListener('click', function(e){ e.preventDefault(); window.Sections.toggle(id); });
			btn.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ' || e.code === 'Space'){ e.preventDefault(); window.Sections.toggle(id); } });
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

// Alternar filtros (botón Mostrar Filtros)
window.toggleFiltros = function() {
	const filtros = document.getElementById('filtros-contenedor');
	if (!filtros) return;
	const isVisible = filtros.style.display !== 'none';
	filtros.style.display = isVisible ? 'none' : 'block';
	const btn = document.getElementById('btn-toggle-filtros');
	if (btn) btn.textContent = isVisible ? 'Mostrar Filtros' : 'Ocultar Filtros';
};

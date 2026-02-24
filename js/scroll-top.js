// scroll-top.js - small helper (enhanced: animation, accessibility, color)
(function(){
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'scroll-top-btn';
    btn.innerHTML = '▲';
    btn.setAttribute('aria-label','Subir al inicio');
    btn.title = 'Ir arriba';
    // Fixed placement (small offset to corner)
    btn.style.position = 'fixed';
    btn.style.right = '12px';
    btn.style.bottom = '12px';
    btn.style.zIndex = 1000;

    btn.addEventListener('click', () => window.scrollTo({top:0, behavior:'smooth'}));
    btn.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); } });

    document.addEventListener('DOMContentLoaded', ()=>{
        document.body.appendChild(btn);
        const check = () => { btn.classList.toggle('visible', window.scrollY > 200); };
        window.addEventListener('scroll', check);
        // initial state
        check();
    });
})();

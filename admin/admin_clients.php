<?php
// admin/admin_clients.php
// Simple admin UI for managing clients and generating one-time keys.
session_start();
// Basic auth check: ensure logged-in user is admin
if (!isset($_SESSION['user']) || (empty($_SESSION['user']['role']) && empty($_SESSION['user']['is_admin']))) {
    // Redirect to admin login page (use relative path so it works under subfolder deployments)
    header('Location: ../login_admin_skm.html');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administrar Clientes</title>
    <link rel="stylesheet" href="/style.css">
    <style>
    :root{--bg:#f6f8fb;--card:#ffffff;--muted:#6b7280;--accent:#0ea5e9;--accent-2:#7c3aed}
    body{font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:#0f1724;padding:24px}
    .container{max-width:1100px;margin:0 auto}
    header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
    header h1{margin:0;font-size:1.8rem}
    .card{background:var(--card);border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06);padding:18px;margin-bottom:16px}
    form label{display:block;margin-bottom:6px;font-weight:600;color:var(--muted)}
    form input{width:100%;padding:10px 12px;border:1px solid #e6eef8;border-radius:8px;margin-bottom:10px;font-size:0.98rem}
    .row{display:flex;gap:12px}
    .btn{display:inline-block;padding:10px 12px;border-radius:10px;border:1px solid rgba(2,6,23,0.06);background:transparent;color:var(--accent-2);cursor:pointer}
    .btn-primary{background:linear-gradient(90deg,var(--accent-2),var(--accent));color:#fff;border:none}
    .muted{color:var(--muted)}
    #clients{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
    .client-card{background:linear-gradient(180deg,#fff,#fbfdff);border-radius:10px;padding:12px;border:1px solid #eef4fb}
    .client-card h3{margin:0 0 8px 0;font-size:1rem}
    .small{font-size:0.9rem;color:var(--muted)}
    #keys{margin-top:8px}
    pre.key-box{background:#0b1220;color:#9fe8ff;padding:10px;border-radius:8px;overflow:auto}
    /* modal */
    .modal-backdrop{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);z-index:12000}
    .modal-backdrop.active{display:flex}
    .modal{background:#fff;padding:16px;border-radius:10px;max-width:520px;width:100%;box-shadow:0 18px 60px rgba(2,6,23,0.12)}
    .flex-between{display:flex;justify-content:space-between;align-items:center}
    .muted-note{font-size:0.9rem;color:#56616b}
    @media(max-width:720px){ .row{flex-direction:column} }
    </style>
</head>
<body>
<main class="container">
    <header>
        <h1>Administrar Clientes</h1>
        <div class="flex-between">
            <button id="open-create" class="btn btn-primary">Registrar cliente</button>
        </div>
    </header>

    <section class="card">
        <div class="flex-between"><h2 style="margin:0">Clientes</h2><div class="muted-note">Lista de clientes y acciones</div></div>
        <div id="clients" style="margin-top:12px"></div>
    </section>

    <section class="card">
        <h2 style="margin:0">Claves Generadas</h2>
        <div id="keys" style="margin-top:12px"></div>
    </section>

    <section class="card">
        <h2 style="margin:0">Notificaciones Pendientes</h2>
        <div id="notifications" style="margin-top:12px"></div>
    </section>

    <!-- Create client modal -->
    <div id="modal-create" class="modal-backdrop" role="dialog" aria-hidden="true">
        <div class="modal" role="document">
            <div class="flex-between"><h3 style="margin:0">Registrar cliente</h3><button id="close-create" class="btn">✕</button></div>
            <form id="client-form" style="margin-top:12px">
                <label>Nombre<input name="name" required></label>
                <label>Email<input name="email" type="email"></label>
                <label>Teléfono<input name="phone"></label>
                <div style="display:flex;gap:8px;margin-top:8px;justify-content:flex-end">
                    <button type="button" id="cancel-create" class="btn">Cancelar</button>
                    <button type="submit" id="create-submit" class="btn btn-primary">Registrar</button>
                </div>
                <div id="create-status" class="muted" style="margin-top:8px;display:none"></div>
            </form>
        </div>
    </div>
    <!-- Key display modal -->
    <div id="modal-key" class="modal-backdrop" role="dialog" aria-hidden="true">
        <div class="modal" role="document">
            <div class="flex-between"><h3 style="margin:0">Clave generada</h3><button id="close-key" class="btn">✕</button></div>
            <div style="margin-top:12px">
                <div class="muted-note">Copia la clave y compártela con el cliente final. Esta clave solo se muestra una vez.</div>
                <pre id="key-value" class="key-box" style="margin-top:10px">-</pre>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
                    <button type="button" id="copy-key" class="btn">Copiar</button>
                    <button type="button" id="close-key-2" class="btn btn-primary">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

</main>
<script>
const apiBase = (()=>{
    try { return (new URL('.', location.href)).origin + '/api/admin/'; } catch(e) { return '/api/admin/'; }
})();

async function fetchClients(){
    const res = await fetch(apiBase + 'list_clients.php', { credentials: 'same-origin' });
    if(!res.ok) throw new Error('Error al obtener clientes');
    return res.json();
}

async function fetchKeys(client_id=0){
    const url = apiBase + 'list_keys.php' + (client_id?('?client_id='+client_id):'');
    const res = await fetch(url, { credentials: 'same-origin' });
    if(!res.ok) throw new Error('Error al obtener claves');
    return res.json();
}

function renderClients(list){
    const container = document.getElementById('clients');
    container.innerHTML='';
    if (!list || !list.length) {
        container.innerHTML = '<div class="muted">No se encontraron clientes.</div>';
        return;
    }
    list.forEach(c => {
        const el = document.createElement('div'); el.className='client-card';
        const approved = parseInt(c.approved || 0, 10) === 1;
        const statusHtml = approved ? '<span style="color:green;font-weight:600">Aprobado</span>' : '<span style="color:#d97706;font-weight:600">Pendiente</span>';
        el.innerHTML = `<h3>${escapeHtml(c.name || '—')}</h3>
            <div class="small">${escapeHtml(c.email || '')}</div>
            <div class="small" style="margin-bottom:8px">${escapeHtml(c.phone || '')}</div>
            <div class="small" style="margin-bottom:8px">Estado: ${statusHtml} ${c.approved_at ? '· ' + escapeHtml(c.approved_at) : ''}</div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
              ${approved ? '' : `<button data-id="${c.id}" class="btn approve">Aprobar</button>`}
              <button data-id="${c.id}" class="btn gen-key">Generar clave</button>
              <button data-id="${c.id}" class="btn show-keys">Ver claves</button>
            </div>`;
        container.appendChild(el);
    });
}

function renderKeys(list){
    const container = document.getElementById('keys');
    container.innerHTML='';
    if (!list || !list.length) { container.innerHTML = '<div class="muted">No hay claves generadas</div>'; return; }
    list.forEach(k => {
        const el = document.createElement('div'); el.className='card';
        el.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center"><div><strong>${escapeHtml(k.key)}</strong><div class="small">Cliente: ${escapeHtml(String(k.client_id||'N/A'))}</div></div><div class="small">${escapeHtml(k.created_at||'')}</div></div>`;
        container.appendChild(el);
    });
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

// Modal controls
const modal = document.getElementById('modal-create');
const openBtn = document.getElementById('open-create');
const closeBtn = document.getElementById('close-create');
const cancelCreate = document.getElementById('cancel-create');
openBtn && openBtn.addEventListener('click', ()=> { modal.classList.add('active'); modal.setAttribute('aria-hidden','false'); document.querySelector('#client-form [name=name]').focus(); });
closeBtn && closeBtn.addEventListener('click', ()=> { modal.classList.remove('active'); modal.setAttribute('aria-hidden','true'); });
cancelCreate && cancelCreate.addEventListener('click', ()=> { modal.classList.remove('active'); modal.setAttribute('aria-hidden','true'); });

document.getElementById('client-form').addEventListener('submit', async e=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const obj = Object.fromEntries(fd.entries());
    const status = document.getElementById('create-status');
    try {
        // Frontend pre-check: avoid duplicate client by email or exact name
        status.style.display = 'block'; status.textContent = 'Verificando existencia...';
        try {
            const listRes = await fetch(apiBase + 'list_clients.php', { credentials: 'same-origin' });
            if (listRes && listRes.ok) {
                const listJson = await listRes.json();
                const clients = listJson.clients || [];
                const email = (obj.email||'').trim().toLowerCase();
                const name = (obj.name||'').trim().toLowerCase();
                const dup = clients.find(c => (email && ((c.email||'').toLowerCase() === email)) || ((c.name||'').toLowerCase() === name));
                if (dup) {
                    status.textContent = 'Ya existe un cliente con el mismo nombre o correo.';
                    setTimeout(()=>{ if (status) status.style.display='none'; }, 3000);
                    return;
                }
            }
        } catch(ignored) { console.warn('client pre-check failed', ignored); }

        status.textContent = 'Registrando...';
        const res = await fetch(apiBase + 'register_client.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
        const json = await res.json();
        if (json && json.success) {
            status.textContent = 'Cliente creado.';
            modal.classList.remove('active'); modal.setAttribute('aria-hidden','true');
            await reloadAll();
        } else {
            status.textContent = (json && json.message) ? json.message : 'Error al crear cliente';
        }
    } catch(err){ status.textContent = 'Error de red: '+err.message; }
    setTimeout(()=>{ if (status) status.style.display='none'; }, 3000);
});

document.getElementById('clients').addEventListener('click', async e=>{
    const t = e.target;
    if(t.classList.contains('approve')){
        const id = t.dataset.id;
        t.disabled = true;
        try {
            const res = await fetch(apiBase + 'approve_client.php', { method: 'POST', credentials: 'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ client_id: id }) });
            const j = await res.json();
            if (j.success) { await reloadAll(); } else { showErrorAlert(j.message || JSON.stringify(j)); }
        } catch(err){ showErrorAlert('Error al aprobar cliente: '+err.message); }
        t.disabled = false;
        return;
    }
    if(t.classList.contains('gen-key')){
        const id = t.dataset.id;
        t.disabled = true;
        try {
            const res = await fetch(apiBase + 'generate_key.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:id})});
            const j = await res.json();
            if(j.success){
                showKeyModal(j.key);
                await reloadAll();
            } else {
                showErrorAlert(j.message || JSON.stringify(j));
            }
        } catch(err){ showErrorAlert('Error al generar clave: '+err.message); }
        t.disabled = false;
    } else if(t.classList.contains('show-keys')){
        const id = t.dataset.id;
        try { const keys = await fetchKeys(id); renderKeys(keys.keys || []); } catch(err){ alert('Error al obtener claves: '+err.message); }
    }
});

// Key modal helpers
function showKeyModal(key) {
    try {
        const modal = document.getElementById('modal-key');
        const val = document.getElementById('key-value');
        if (val) val.textContent = key || '-';
        if (modal) { modal.classList.add('active'); modal.setAttribute('aria-hidden','false'); }
    } catch(e){ console.warn('showKeyModal', e); alert('Clave: '+key); }
}
function hideKeyModal(){ try { const modal = document.getElementById('modal-key'); if (modal) { modal.classList.remove('active'); modal.setAttribute('aria-hidden','true'); } } catch(e){ console.warn('hideKeyModal', e); } }
document.getElementById('close-key') && document.getElementById('close-key').addEventListener('click', hideKeyModal);
document.getElementById('close-key-2') && document.getElementById('close-key-2').addEventListener('click', hideKeyModal);
document.getElementById('copy-key') && document.getElementById('copy-key').addEventListener('click', function(){
    try {
        const v = document.getElementById('key-value').textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(v).then(function(){ showToast('Clave copiada al portapapeles', 'success'); }).catch(function(){ prompt('Copiar clave:', v); });
        } else { prompt('Copiar clave:', v); }
    } catch(e){ console.warn(e); }
});

function showErrorAlert(msg){ try { alert(msg); } catch(e){ console.error(msg); } }

async function reloadAll(){
    try {
        const clients = await fetchClients(); renderClients(clients.clients || []);
        const keys = await fetchKeys(); renderKeys(keys.keys || []);
        await reloadNotifications();
    } catch(err){ console.error(err); const el = document.createElement('div'); el.className='card'; el.textContent = 'Error: '+err.message; document.body.prepend(el); }
}

async function fetchNotifications(){
    const res = await fetch(apiBase + 'pending_notifications.php', { credentials: 'same-origin' });
    if(!res.ok) throw new Error('Error al obtener notificaciones');
    return res.json();
}

function renderNotifications(list){
    const container = document.getElementById('notifications');
    container.innerHTML = '';
    if (!list || !list.length) { container.innerHTML = '<div class="muted">No hay notificaciones pendientes</div>'; return; }
    list.forEach(n => {
        const el = document.createElement('div'); el.className='card';
        el.style.padding = '10px';
        el.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center"><div><strong>${escapeHtml(n.name||'Sin nombre')}</strong><div class="small">${escapeHtml(n.email||'')}</div><div class="small">${escapeHtml(n.phone||'')}</div><div class="small">Creado: ${escapeHtml(n.created_at||'')}</div></div><div style="display:flex;flex-direction:column;gap:6px"><button data-idx="${n._idx}" class="btn send-email">Enviar email</button><button data-idx="${n._idx}" class="btn approve-notif">Aprobar y eliminar</button><button data-idx="${n._idx}" class="btn clear-notif">Marcar leída</button></div></div>`;
        container.appendChild(el);
    });
}

async function reloadNotifications(){
    try {
        const res = await fetchNotifications(); renderNotifications(res.notifications || []);
    } catch(err){ console.error('reloadNotifications', err); }
}

(async ()=>{ try{ await reloadAll(); } catch(err){ console.error(err); alert('Error: '+err.message); } })();

// Notifications event handlers
document.getElementById('notifications') && document.getElementById('notifications').addEventListener('click', async e=>{
    const t = e.target;
    if (t.classList.contains('send-email')){
        const idx = t.dataset.idx; t.disabled = true;
        try {
            const res = await fetch(apiBase + 'send_notification_email.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({idx:idx})});
            const j = await res.json();
            if (j.success) { alert('Email enviado (simulado)'); } else { alert('Error: '+(j.message||JSON.stringify(j))); }
        } catch(err){ alert('Error al enviar: '+err.message); }
        t.disabled = false; return;
    }
    if (t.classList.contains('clear-notif')){
        const idx = t.dataset.idx; t.disabled = true;
        try {
            const res = await fetch(apiBase + 'clear_notification.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({idx:idx})});
            const j = await res.json(); if (j.success) { await reloadNotifications(); } else { alert('Error: '+(j.message||JSON.stringify(j))); }
        } catch(err){ alert('Error al limpiar: '+err.message); }
        t.disabled = false; return;
    }
    if (t.classList.contains('approve-notif')){
        // Approve the client and remove the notification
        const idx = t.dataset.idx; t.disabled = true;
        try {
            // read notification to get client_id if present, otherwise approve by listing clients
            const pn = await (await fetch(apiBase + 'pending_notifications.php',{credentials:'same-origin'})).json();
            const note = (pn.notifications || []).find(x=>String(x._idx) === String(idx));
            if (note && note.client_id) {
                // call approve endpoint for that client id
                const appro = await fetch(apiBase + 'approve_client.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:note.client_id})});
                const aj = await appro.json(); if (!aj.success) alert('Aprobación: '+(aj.message||JSON.stringify(aj)));
            }
            // remove notification
            const res = await fetch(apiBase + 'clear_notification.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({idx:idx})});
            const j = await res.json(); if (j.success) { await reloadAll(); } else { alert('Error: '+(j.message||JSON.stringify(j))); }
        } catch(err){ alert('Error en aprobar: '+err.message); }
        t.disabled = false; return;
    }
});
</script>
</body>
</html>

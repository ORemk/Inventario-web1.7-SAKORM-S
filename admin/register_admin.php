<?php
// admin/register_admin.php
session_start();
// require session: only allow if logged in as admin (master check happens server-side in API)
if (!isset($_SESSION['user'])) {
    header('Location: /Sakorms.org/Inventory-web1.5/login_admin_skm.html');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registrar Administrador</title>
  <link rel="stylesheet" href="/Sakorms.org/Inventory-web1.5/style.css">
  <style>body{font-family:Inter,system-ui,Arial;background:#071226;color:#eaf4ff;padding:24px} .card{background:#081226;padding:18px;border-radius:10px;max-width:820px;margin:0 auto} label{display:block;margin-top:10px;color:#9fb2c8} input{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:#eaf4ff} .btn{margin-top:12px;padding:10px 14px;border-radius:8px;border:none;background:linear-gradient(90deg,#6c5ce7,#00d4ff);color:#031021;font-weight:700;cursor:pointer}</style>
</head>
<body>
  <main>
    <div class="card">
      <h1>Crear nuevo Administrador</h1>
      <p class="muted">Solo el administrador maestro puede crear administradores. Si no tienes permisos, contacta al superadmin.</p>
      <form id="admin-create-form">
        <label>Nombre(s)</label>
        <input id="nombre" required>
        <label>Apellido paterno</label>
        <input id="apellido_paterno" required>
        <label>Apellido materno</label>
        <input id="apellido_materno">
        <label>Usuario (username)</label>
        <input id="username" required>
        <label>Email</label>
        <input id="email" type="email">
        <label>Teléfono</label>
        <input id="phone">
        <label>Contraseña</label>
        <input id="password" type="password" required>
        <label>Confirmar contraseña</label>
        <input id="password_confirm" type="password" required>
        <div style="display:flex;gap:8px;align-items:center;">
          <button type="submit" class="btn">Crear administrador</button>
          <a href="/Sakorms.org/Inventory-web1.5/" style="margin-left:12px;color:#9fb2c8">Volver al inicio</a>
        </div>
        <div id="result" style="margin-top:12px;color:#ffdede"></div>
      </form>
    </div>
  </main>
  <script>
    const api = (document.baseURI ? new URL('api/admin/register_admin.php', document.baseURI).href : 'api/admin/register_admin.php');
    document.getElementById('admin-create-form').addEventListener('submit', async function(e){
      e.preventDefault();
      const nombre = document.getElementById('nombre').value.trim();
      const apellido_paterno = document.getElementById('apellido_paterno').value.trim();
      const apellido_materno = document.getElementById('apellido_materno').value.trim();
      const username = document.getElementById('username').value.trim();
      const email = document.getElementById('email').value.trim();
      const phone = document.getElementById('phone').value.trim();
      const password = document.getElementById('password').value;
      const password_confirm = document.getElementById('password_confirm').value;
      const resEl = document.getElementById('result'); resEl.textContent = ''; resEl.style.color = '#ffdede';
      if (!password || password !== password_confirm) { resEl.textContent = 'Las contraseñas no coinciden'; return; }
      const payload = { nombre: nombre || username, apellido_paterno, apellido_materno, username, email, phone, password };
      try {
        const r = await fetch(api, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const j = await r.json().catch(()=>({}));
        if (!r.ok || !j.success) { resEl.textContent = j.message || 'Error al crear administrador'; return; }
        resEl.style.color = '#c7ffd1'; resEl.textContent = j.message || 'Administrador creado';
        setTimeout(()=>{ try{ window.location.href = j.redirect || '/Sakorms.org/Inventory-web1.5/'; } catch(e){ console.warn('redirect failed', e); } }, 900);
      } catch (err) { resEl.textContent = 'Error de red: ' + (err.message || ''); }
    });
  </script>
</body>
</html>

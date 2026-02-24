<?php
// admin/admin_settings.php
session_start();
if (!isset($_SESSION['user']) || (empty($_SESSION['user']['role']) && empty($_SESSION['user']['is_admin']))) {
    header('Location: ../login_admin_skm.html'); exit;
}

$cfgFile = __DIR__ . '/../src/config/smtp.php';
$cfg = [];
if (is_file($cfgFile)) {
    $cfg = include $cfgFile;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configuración SMTP</title>
    <link rel="stylesheet" href="/style.css">
    <style>
    body{font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;padding:20px;background:#f6f8fb}
    .card{background:#fff;padding:16px;border-radius:10px;max-width:900px;margin:0 auto;box-shadow:0 8px 24px rgba(2,6,23,0.06)}
    label{display:block;margin-top:10px;font-weight:600}
    input[type=text],input[type=password],select{width:100%;padding:10px;border-radius:6px;border:1px solid #e6eef8}
    .row{display:flex;gap:12px}
    .btn{padding:8px 12px;border-radius:8px;border:none;background:#7c3aed;color:#fff;cursor:pointer}
    .muted{color:#6b7280}
    </style>
</head>
<body>
<main class="card">
    <h1>Configuración SMTP</h1>
    <p class="muted">Edita las credenciales SMTP para el envío de notificaciones. Protege este acceso.</p>
    <form id="smtp-form">
        <label>Activar SMTP
            <select name="enabled" id="enabled">
                <option value="0">Desactivado</option>
                <option value="1">Activado</option>
            </select>
        </label>
        <label>Host<input type="text" name="host" id="host" value="<?= htmlspecialchars($cfg['host'] ?? '') ?>"></label>
        <div class="row">
            <label style="flex:1">Puerto<input type="text" name="port" id="port" value="<?= htmlspecialchars($cfg['port'] ?? '') ?>"></label>
            <label style="flex:2">Encriptación
                <select name="encryption" id="encryption">
                    <option value="tls">tls</option>
                    <option value="ssl">ssl</option>
                    <option value="">ninguna</option>
                </select>
            </label>
        </div>
        <label>Usuario (username)<input type="text" name="username" id="username" value="<?= htmlspecialchars($cfg['username'] ?? '') ?>"></label>
        <label>Contraseña<input type="password" name="password" id="password" value="<?= htmlspecialchars($cfg['password'] ?? '') ?>"></label>
        <label>From Email<input type="text" name="from_email" id="from_email" value="<?= htmlspecialchars($cfg['from_email'] ?? '') ?>"></label>
        <label>From Name<input type="text" name="from_name" id="from_name" value="<?= htmlspecialchars($cfg['from_name'] ?? '') ?>"></label>
        <label>Admin Email<input type="text" name="admin_email" id="admin_email" value="<?= htmlspecialchars($cfg['admin_email'] ?? '') ?>"></label>

        <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
            <button type="button" id="save" class="btn">Guardar</button>
        </div>
        <div id="status" class="muted" style="margin-top:8px;display:none"></div>
    </form>
    <p class="muted" style="margin-top:12px">Nota: al guardar se escribirá el archivo <code>src/config/smtp.php</code> (se hará copia de seguridad).</p>
</main>
<script>
document.addEventListener('DOMContentLoaded', function(){
    // set select values
    document.getElementById('enabled').value = '<?= (!empty($cfg['enabled'])? '1':'0') ?>';
    document.getElementById('encryption').value = '<?= htmlspecialchars($cfg['encryption'] ?? '') ?>';

    document.getElementById('save').addEventListener('click', async function(){
        const btn = this; const status = document.getElementById('status');
        btn.disabled = true; status.style.display='block'; status.textContent='Guardando...';
        const payload = {
            enabled: document.getElementById('enabled').value,
            host: document.getElementById('host').value,
            port: document.getElementById('port').value,
            encryption: document.getElementById('encryption').value,
            username: document.getElementById('username').value,
            password: document.getElementById('password').value,
            from_email: document.getElementById('from_email').value,
            from_name: document.getElementById('from_name').value,
            admin_email: document.getElementById('admin_email').value
        };
        try {
            const res = await fetch('../api/admin/save_smtp_settings.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
            const j = await res.json();
                if (j.success) {
                // Clear password field for privacy
                try { document.getElementById('password').value = ''; } catch(e){ console.warn('clear password field', e); }

                // Interpret smtp test result if present
                if (typeof j.smtp_test !== 'undefined') {
                    if (j.smtp_test === true) {
                        status.textContent = 'Guardado correctamente. Prueba SMTP exitosa.';
                        if (typeof j.smtp_send_test !== 'undefined') {
                            if (j.smtp_send_test === true) status.textContent += ' Correo de prueba enviado correctamente.';
                            else if (j.smtp_send_test === false) status.textContent += ' Envío de prueba falló: ' + (j.smtp_send_error || 'Desconocido');
                        }
                    } else if (j.smtp_test === false) {
                        status.textContent = 'Guardado, pero prueba SMTP falló: ' + (j.smtp_error || 'Desconocido');
                        if (typeof j.smtp_send_test !== 'undefined' && j.smtp_send_test === false) status.textContent += ' Envío de prueba falló: ' + (j.smtp_send_error || 'Desconocido');
                    } else {
                        status.textContent = 'Guardado. Prueba SMTP no disponible.';
                    }
                } else {
                    status.textContent = 'Guardado correctamente.';
                }
            } else { status.textContent = 'Error: '+(j.message||JSON.stringify(j)); }
        } catch(err){ status.textContent = 'Error de red: '+err.message; }
        btn.disabled = false; setTimeout(()=>{ status.style.display='none'; },3000);
    });
});
</script>
</body>
</html>

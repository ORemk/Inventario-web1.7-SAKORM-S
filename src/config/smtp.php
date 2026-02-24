<?php
// src/config/smtp.php
// Plantilla de configuración SMTP.
// INSTRUCCIONES:
// 1) Haz una copia de este archivo y edítala con tus credenciales reales.
// 2) Establece 'enabled' => true para activar el envío por SMTP.
// 3) Protege este archivo (no lo subas a repositorios públicos).

return [
    // Activar sólo si ya rellenaste host/username/password correctamente
    'enabled' => false,

    // Servidor SMTP (p.ej. smtp.gmail.com o smtp.sendgrid.net)
    'host' => 'smtp.example.com',

    // Puerto: 587 para TLS, 465 para SSL, 25 para plain
    'port' => 587,

    // Credenciales SMTP
    'username' => 'no-reply@example.com',
    'password' => 'CHANGE_ME',

    // 'tls' o 'ssl' según tu proveedor
    'encryption' => 'tls',

    // Dirección y nombre desde los que se enviarán los correos
    'from_email' => 'no-reply@example.com',
    'from_name' => 'Sakorms Inventory',

    // Dirección de administrador por defecto (se puede sobrescribir al enviar)
    'admin_email' => 'admin@example.com'
];

/**
 * login.js - Funciones globales para login.html
 * Proporciona toggle para calculadora y otros controles
 */

/* eslint-disable no-empty, no-unused-vars, no-undef, no-inner-declarations */

/**
 * Construir URL de API correctamente para localhost
 * Calcula la ruta base dinámicamente según donde se cargue la página
 */
window.buildApiUrl = function(endpoint) {
    // Prefer resolving against document.baseURI when available (respects <base href>)
    try {
        if (typeof document !== 'undefined' && document.baseURI) {
            // Ensure endpoint is relative to base (remove leading slash)
            const ep = (endpoint || '').replace(/^\//, '');
            return new URL(ep, document.baseURI).href;
        }
    } catch (e) {
        safeWarn('buildApiUrl.fallback', e);
    }

    const protocol = window.location.protocol;
    const hostname = window.location.hostname;
    const pathname = window.location.pathname;
    const port = window.location.port ? ':' + window.location.port : '';
    
    // Si se abre desde file://, redirigir a localhost (fallback)
    if (protocol === 'file:') {
        console.warn('⚠️ ADVERTENCIA: Acceso desde file:// detectado');
        const baseUrl = (typeof window.buildApiUrl === 'function') ? (window.buildApiUrl('') || 'http://localhost/Sakorms.org/Inventory-web1.5/') : 'http://localhost/Sakorms.org/Inventory-web1.5/';
        return baseUrl + endpoint.replace(/^\//, '');
    }
    
    // Construir URL base desde la ubicación actual
    // Si la URL incluye un archivo (p.ej. login.html), quitarlo para formar la carpeta base
    const pathParts = pathname.split('/').filter(p => p);
    if (pathParts.length > 0) {
        let last = pathParts[pathParts.length - 1];
        // Remove query/hash from the last segment if present
        last = last.split('?')[0].split('#')[0];
        if (last.includes('.') && last.indexOf('.') > 0) {
            // remover segmento de archivo (index.html, login_admin_skm.html, etc.)
            pathParts.pop();
        }
    }
    const basePath = pathParts.length ? pathParts.join('/') + '/' : '';
    const baseUrl = `${protocol}//${hostname}${port}/${basePath}`;

    return baseUrl + endpoint.replace(/^\//, '');
};

// Helpers defensivos para acceso al DOM
function $id(id) { try { return document.getElementById(id); } catch(e) { return null; } }
function $val(id, fallback = '') { try { const el = document.getElementById(id); return (el && typeof el.value !== 'undefined') ? el.value : fallback; } catch(e) { return fallback; } }
function $trimVal(id, fallback = '') { try { const v = $val(id, fallback); return (typeof v === 'string') ? v.trim() : String(v); } catch(e) { return fallback; } }
function safeWarn(ctx, e){ try { console.warn(ctx, e); } catch(_){ /* noop */ } }

// Helpers seguros para mostrar/ocultar mensajes de error en formularios
function showErrorElement(el, message) {
    try {
        if (!el) { console.warn('showErrorElement: target missing', message); return; }
        el.textContent = message || '';
        el.style.display = 'block';
    } catch (e) { /* noop */ }
}
function hideErrorElement(el) {
    try { if (!el) return; el.style.display = 'none'; } catch(e) { /* noop */ }
}

// Fallback para generar ID a partir de string si no existe la función en otro módulo
if (typeof window.generateIdFromString === 'undefined') {
    window.generateIdFromString = async function(input, len = 20) {
        try {
            const s = String(input || '');
            if (window.crypto && crypto.subtle && crypto.subtle.digest) {
                const encoder = new TextEncoder();
                const data = encoder.encode(s);
                const hash = await crypto.subtle.digest('SHA-256', data);
                const bytes = Array.from(new Uint8Array(hash));
                const hex = bytes.map(b => ('00' + b.toString(16)).slice(-2)).join('');
                return hex.substr(0, len);
            }
            const b64 = btoa(unescape(encodeURIComponent(s))); // fallback
            return b64.replace(/=+$/, '').replace(/[^A-Za-z0-9]/g, '').substr(0, len);
        } catch (e) { return null; }
    };
}

/**
 * Redirigir a una página usando ruta relativa correcta
 * Maneja correctamente URLs en localhost y otros entornos
 */
window.redirectTo = function(page) {
    try {
        // Preferir document.baseURI (respeta <base href>) para resolver rutas relativas.
        const base = (document.baseURI || (document.querySelector('base') && document.querySelector('base').href) || (window.location.protocol + '//' + window.location.host + window.location.pathname));
        const resolved = new URL(page, base);
        window.location.href = resolved.href;
        return;
    } catch (err) {
        safeWarn('redirectTo.resolve', err);
        // Fallback: conservar comportamiento previo si URL no es resoluble
    }

    // Fallback legacy: construir desde la ubicación actual
    const protocol = window.location.protocol;
    const hostname = window.location.hostname;
    const pathname = window.location.pathname;
    const port = window.location.port ? ':' + window.location.port : '';
    if (protocol === 'file:') {
        const baseUrl = (typeof window.buildApiUrl === 'function') ? (window.buildApiUrl('') || 'http://localhost/Sakorms.org/Inventory-web1.5/') : 'http://localhost/Sakorms.org/Inventory-web1.5/';
        window.location.href = baseUrl + page.replace(/^\//, '');
        return;
    }
    const pathParts = pathname.split('/').filter(p => p);
    if (pathParts.length) {
        const last = pathParts[pathParts.length - 1];
        if (last.includes('.') && last.indexOf('.') > 0) pathParts.pop();
    }
    const basePath = pathParts.length ? pathParts.join('/') + '/' : '';
    const baseUrl = `${protocol}//${hostname}${port}/${basePath}`;
    window.location.href = baseUrl + page.replace(/^\//, '');
};

/**
 * Toggle para abrir/cerrar modal de calculadora
 * Usa clase .active para visibilidad con opacity/visibility
 */
window.toggleCalculator = function() {
    const modal = document.getElementById('calculatorModal');
    if (!modal) return;
    
    const isActive = modal.classList.contains('active');
    
    if (isActive) {
        // Cerrar modal
        modal.classList.remove('active');
    } else {
        // Abrir modal
        modal.classList.add('active');
        
        // Dar foco al display de calculadora
        const display = document.getElementById('calcDisplay');
        if (display) {
            setTimeout(() => display.focus(), 100);
        }
        
        // Mostrar historial si existe
        if (typeof Calculator !== 'undefined' && Calculator.showHistory) {
            Calculator.showHistory();
        }
    }
};

/**
 * Función para social login - Integración con redes sociales
 */
window.socialLogin = function(provider) {
    const socialConfig = {
        'google': {
            name: 'Google',
            clientId: 'TU_GOOGLE_CLIENT_ID',
            scope: 'profile email',
            redirectUri: window.location.origin + '/callback'
        },
        'facebook': {
            name: 'Facebook',
            appId: 'TU_FACEBOOK_APP_ID',
            scope: 'public_profile,email',
            redirectUri: window.location.origin + '/callback'
        },
        'twitter': {
            name: 'Twitter',
            clientId: 'TU_TWITTER_CLIENT_ID',
            redirectUri: window.location.origin + '/callback'
        },
        'instagram': {
            name: 'Instagram',
            appId: 'TU_INSTAGRAM_APP_ID',
            redirectUri: window.location.origin + '/callback'
        }
    };
    
    const config = socialConfig[provider];
    
    if (!config) {
        console.error('Proveedor no reconocido:', provider);
        return;
    }
    
    console.log(`🔐 Iniciando login con ${config.name}...`);
    
    // Mostrar mensaje al usuario
    showNotification(`Conectando con ${config.name}...`, 'info');
    
    try {
        switch(provider) {
            case 'google':
                initGoogleLogin(config);
                break;
            case 'facebook':
                initFacebookLogin(config);
                break;
            case 'twitter':
                initTwitterLogin(config);
                break;
            case 'instagram':
                initInstagramLogin(config);
                break;
        }
    } catch (error) {
        console.error('Error en login social:', error);
        showNotification(`Error al conectar con ${config.name}`, 'error');
    }
};

/**
 * Google Login Integration
 */
window.initGoogleLogin = function(config) {
    // Implementar con Google OAuth 2.0
    // Requiere configurar Google Cloud Console
    const params = new URLSearchParams({
        client_id: config.clientId,
        redirect_uri: config.redirectUri,
        response_type: 'code',
        scope: config.scope,
        access_type: 'offline',
        prompt: 'consent'
    });
    
    window.location.href = `https://accounts.google.com/o/oauth2/v2/auth?${params.toString()}`;
}

/**
 * Facebook Login Integration
 */
window.initFacebookLogin = function(config) {
    // Implementar con Facebook SDK
    if (typeof FB !== 'undefined') {
        FB.login(function(response) {
            if (response.authResponse) {
                handleSocialLoginResponse({
                    provider: 'facebook',
                    token: response.authResponse.accessToken,
                    userID: response.authResponse.userID
                });
            }
        }, {scope: config.scope});
    } else {
        // Fallback: redirigir a Facebook OAuth
        const params = new URLSearchParams({
            client_id: config.appId,
            redirect_uri: config.redirectUri,
            response_type: 'code',
            scope: config.scope
        });
        window.location.href = `https://www.facebook.com/v18.0/dialog/oauth?${params.toString()}`;
    }
}

/**
 * Twitter Login Integration
 */
window.initTwitterLogin = function(config) {
    // Implementar con Twitter OAuth 2.0
    const params = new URLSearchParams({
        client_id: config.clientId,
        redirect_uri: config.redirectUri,
        response_type: 'code',
        scope: 'tweet.read users.read',
        state: generateRandomState(),
        code_challenge: generateCodeChallenge(),
        code_challenge_method: 'S256'
    });
    
    window.location.href = `https://twitter.com/i/oauth2/authorize?${params.toString()}`;
}

/**
 * Instagram Login Integration
 */
window.initInstagramLogin = function(config) {
    // Instagram utiliza Facebook for Developers
    const params = new URLSearchParams({
        client_id: config.appId,
        redirect_uri: config.redirectUri,
        scope: 'user_profile,user_media',
        response_type: 'code'
    });
    
    window.location.href = `https://api.instagram.com/oauth/authorize?${params.toString()}`;
}

/**
 * Manejar respuesta de login social
 */
window.handleSocialLoginResponse = function(response) {
    console.log('✅ Login social exitoso:', response.provider);
    
    // Enviar token al servidor para validación y creación de sesión
    fetch(buildApiUrl('/api/social-login'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            provider: response.provider,
            token: response.token,
            userID: response.userID
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('¡Sesión iniciada correctamente!', 'success');
            // Redirigir al dashboard después de 1 segundo
            setTimeout(() => {
                redirectTo('index.html');
            }, 1000);
        } else {
            showNotification(data.message || 'Error en el login', 'error');
        }
    })
    .catch(error => {
        console.error('Error en social login:', error);
        showNotification('Error al procesar login social', 'error');
    });
}

/**
 * Generar estado aleatorio para PKCE
 */
window.generateRandomState = function() {
    return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

/**
 * Generar code challenge para PKCE
 */
window.generateCodeChallenge = function() {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
    let result = '';
    for (let i = 0; i < 128; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    return btoa(result).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

/**
 * Mostrar notificaciones
 */
window.showNotification = function(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#43cea2' : type === 'error' ? '#ff6f61' : '#ff9800'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10001;
        animation: slideIn 0.3s ease-out;
        font-size: 1rem;
        font-weight: 500;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    // Actualizar región ARIA para lectores de pantalla
    const aria = document.getElementById('aria-notify');
    if (aria) aria.textContent = message;
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
};

/**
 * Mostrar diálogo (fallback para login.html que no carga main.js)
 * Si UI está disponible, usa UI.showDialog; sino, usa una implementación simple
 */
window.showDialog = function(options = {}) {
    // Accept a simple string message for convenience
    if (typeof options === 'string' || options instanceof String) {
        options = { message: String(options) };
    }
    // Si UI está disponible (se carga js/ui.js), usarlo
    if (typeof UI !== 'undefined' && UI.showDialog) {
        return UI.showDialog(options || {});
    }
    
    // Fallback: mostrar como notificación
    const message = (options && (options.message || options.title)) || 'Información';
    const type = (options && options.icon) ? 'info' : 'success';
    showNotification(message, type);
};

/**
 * Mostrar formulario de login
 */
window.mostrarLogin = function() {
    switchForm('login-form');
};

/**
 * Mostrar formulario de registro
 */
window.mostrarRegistro = function() {
    switchForm('register-form');
};

/**
 * Mostrar formulario de recuperación
 */
window.mostrarRecuperar = function() {
    switchForm('recover-form');
};

// Helper para transicionar entre formularios con animación
function switchForm(showId) {
    const ids = ['login-form','register-form','recover-form'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (id === showId) {
            el.style.display = 'block';
            requestAnimationFrame(() => {
                el.classList.add('show');
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
                el.style.height = 'auto';
            });
        } else {
            // ocultar con animación
            el.classList.remove('show');
            el.style.opacity = '0';
            el.style.transform = 'translateY(8px)';
            // esperar la transición y luego ocultar completamente
            setTimeout(() => {
                if (el) el.style.display = 'none';
            }, 340);
        }
    });
}

/**
 * Validar y procesar login
 */
window.validarLogin = async function(event) {
    event.preventDefault();
    
    const email = $trimVal('email');
    const password = $val('password');
    const errorDiv = document.getElementById('login-error');
    
    // Validar que no estén vacíos
    if (!email || !password) {
        showErrorElement(errorDiv, 'Por favor completa todos los campos');
        return;
    }
    
    // Validar formato de email sólo si NO estamos en la página de administrador
    const isAdminPage = window.location.pathname && window.location.pathname.toLowerCase().includes('login_admin_skm');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!isAdminPage && !emailRegex.test(email)) {
        showErrorElement(errorDiv, 'Por favor ingresa un email válido');
        return;
    }
    
    try {
        hideErrorElement(errorDiv);
        showNotification('Validando credenciales...', 'info');
        
        // Llamada al servidor para validar login con manejo robusto de errores
        // Si estamos en la página de administrador, apuntar al endpoint admin
        const endpoint = isAdminPage ? '/api/admin/login_admin.php' : '/api/login.php';
        const response = await fetch(buildApiUrl(endpoint), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email: email, password: password })
        });

        // Si el servidor responde con error HTTP, mostrar contenido (HTML o texto) para depuración
        if (!response.ok) {
            const text = await response.text().catch(() => null);
            console.error('Login HTTP error:', response.status, response.statusText, text);
            showErrorElement(errorDiv, text ? `Error servidor: ${text}` : `Error servidor: ${response.status} ${response.statusText}`);
            return;
        }

        // Comprobar tipo de contenido antes de parsear como JSON
        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        let data = null;
        if (contentType.includes('application/json')) {
            data = await response.json().catch(async (err) => {
                console.error('Error parseando JSON de login:', err);
                const txt = await response.text().catch(() => null);
                showErrorElement(errorDiv, txt || 'Respuesta inválida del servidor');
                return null;
            });
            if (!data) return;
        } else {
            // Respuesta no-JSON (probablemente HTML de error). Mostrarla para ayudar a depurar 404/500.
            const txt = await response.text().catch(() => null);
            console.error('Respuesta inesperada (no JSON) en login:', contentType, txt);
            showErrorElement(errorDiv, txt || 'Respuesta no JSON del servidor');
            return;
        }

        if (data.success) {
            showNotification('¡Bienvenido! Redirigiendo...', 'success');
            setTimeout(() => {
                redirectTo('index.html');
            }, 1000);
        } else {
            showErrorElement(errorDiv, data.message || data.error || 'Error en las credenciales');
        }
    } catch (error) {
        console.error('Error en login:', error);
        showErrorElement(errorDiv, 'Error al procesar el login. Intenta más tarde.');
    }
};

/**
 * Registrar cuenta nueva
 */
window.registrarCuenta = async function(event) {
    event.preventDefault();
    
    const nombre = $trimVal('reg-nombre');
    const email = $trimVal('reg-email');
    const password = $val('reg-password');
    const errorDiv = document.getElementById('register-error');
    
    // Validaciones
    if (!nombre || !email || !password) {
        showErrorElement(errorDiv, 'Por favor completa todos los campos');
        return;
    }
    
    if (password.length < 6) {
        showErrorElement(errorDiv, 'La contraseña debe tener al menos 6 caracteres');
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showErrorElement(errorDiv, 'Por favor ingresa un email válido');
        return;
    }
    
    try {
        hideErrorElement(errorDiv);
        showNotification('Creando cuenta...', 'info');
        
        const payload = {
            name: nombre,
            email: email,
            password: password
        };

        // include access_key if present on the page
        let accessKeyValue = '';
        try {
            const ak = document.getElementById('access-key');
            if (ak && ak.value) accessKeyValue = ak.value.trim();
        } catch(e){ safeWarn('registerForm.accessKey', e); }

        // If an access key is provided, call the "with_key" endpoint to activate the key.
        // Otherwise call the permissive register endpoint which creates the client in pending state.
        let endpointUrl = '';
        if (accessKeyValue) {
            payload.access_key = accessKeyValue;
            endpointUrl = buildApiUrl('/api/register_client_with_key.php');
        } else {
            endpointUrl = buildApiUrl('/api/register_client.php');
        }

        const response = await fetch(endpointUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const txt = await response.text().catch(()=>null);
            console.error('Register HTTP error:', response.status, response.statusText, txt);
            let parsed = null;
            try { parsed = txt ? JSON.parse(txt) : null; } catch(e) { parsed = null; }

            // Handle common conflict: email already exists
            if (response.status === 409) {
                const msg = (parsed && parsed.message) ? parsed.message : (txt || 'Ya existe un cliente con ese email');
                // If UI module available, show a friendly dialog offering actions
                if (window.UI && window.UI.showDialog) {
                    window.UI.showDialog({
                        type: 'confirm',
                        title: 'Email ya registrado',
                        html: `<p>${msg}</p><p>¿Deseas iniciar sesión o recuperar la contraseña?</p>`,
                        buttons: [
                            { text: 'Iniciar sesión', class: 'btn primary', onClick: function(){ try{ mostrarLogin(); }catch(e){ safeWarn('login.js:onClick mostrarLogin', e); } } },
                            { text: 'Recuperar contraseña', class: 'btn', onClick: function(){ try{ mostrarRecuperar(); }catch(e){ safeWarn('login.js:onClick mostrarRecuperar', e); } } },
                            { text: 'Usar otro email', class: 'btn', action: 'close' }
                        ]
                    });
                } else {
                    showErrorElement(errorDiv, msg);
                }
                return;
            }

            // Generic error: prefer parsed message if available
            showErrorElement(errorDiv, (parsed && parsed.message) ? parsed.message : (txt || `Error servidor: ${response.status} ${response.statusText}`));
            return;
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        let data = null;
        if (contentType.includes('application/json')) {
            try {
                data = await response.json();
            } catch (err) {
                // The server claimed JSON but returned invalid JSON (often HTML error page).
                // Read raw text and show a cleaned message to help debugging.
                const txt = await response.text().catch(() => null);
                console.error('Error parseando JSON registro:', err, txt);
                const clean = txt ? txt.replace(/<[^>]*>/g, '').trim() : 'Respuesta inválida del servidor';
                showErrorElement(errorDiv, clean);
                return;
            }
        } else {
            const txt = await response.text().catch(()=>null);
            console.error('Respuesta no-JSON en registro:', contentType, txt);
            const clean = txt ? txt.replace(/<[^>]*>/g, '').trim() : 'Respuesta no válida del servidor';
            showErrorElement(errorDiv, clean);
            return;
        }

        if (data.success) {
            // If we called the permissive register endpoint (no access key), inform the user
            if (endpointUrl && endpointUrl.indexOf('/api/register_client.php') !== -1) {
                const dlg = {
                    title: 'Registro pendiente',
                    type: 'info',
                    html: '<p>Hemos recibido tu solicitud. Un administrador validará y aprobará tu cuenta pronto.</p>',
                    buttons: [
                        { text: 'Ir al login', class: 'btn primary', onClick: function(){ try{ mostrarLogin(); }catch(e){ safeWarn('login.js:onClick mostrarLogin', e); } } },
                        { text: 'Cerrar', class: 'btn', action: 'close' }
                    ]
                };
                if (window.UI && window.UI.showDialog) window.UI.showDialog(dlg); else showNotification('Registro recibido. Pendiente de aprobación.', 'info');
                // Do not redirect automatically; user should wait for approval
            } else {
                const dlg = {
                    title: 'Cuenta creada',
                    type: 'success',
                    html: '<p>Tu cuenta fue creada correctamente. ¿Deseas iniciar sesión ahora?</p>',
                    buttons: [
                        { text: 'Iniciar sesión', class: 'btn primary', onClick: function(){ try{ mostrarLogin(); }catch(e){ safeWarn('login.js:onClick mostrarLogin', e); } } },
                        { text: 'Ir al inicio', class: 'btn', onClick: function(){ redirectTo('index.html'); } }
                    ]
                };
                if (window.UI && window.UI.showDialog) {
                    window.UI.showDialog(dlg);
                } else {
                    showNotification('Cuenta creada. Redirigiendo...', 'success');
                    setTimeout(() => { redirectTo('index.html'); }, 1000);
                }
            }
        } else {
            showErrorElement(errorDiv, data.message || 'Error al crear la cuenta');
        }
    } catch (error) {
        console.error('Error al registrar:', error);
        showErrorElement(errorDiv, 'Error al crear la cuenta. Intenta más tarde.');
    }
};

/**
 * Recuperar contraseña
 */
window.recuperarCuenta = async function(event) {
    event.preventDefault();
    
    const email = $trimVal('rec-email');
    const errorDiv = document.getElementById('recover-error');
    
    if (!email) {
        showErrorElement(errorDiv, 'Por favor ingresa tu email');
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showErrorElement(errorDiv, 'Por favor ingresa un email válido');
        return;
    }
    
    try {
        hideErrorElement(errorDiv);
        showNotification('Enviando instrucciones de recuperación...', 'info');
        
        const response = await fetch(buildApiUrl('/api/recover'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: email
            })
        });
        
        const data = await response.json();

        if (data.success) {
            showNotification('Revisa tu email para recuperar tu contraseña', 'success');
            setTimeout(() => { mostrarLogin(); }, 2000);
        } else {
            showErrorElement(errorDiv, data.message || 'Error al procesar la solicitud');
        }
    } catch (error) {
        console.error('Error en recuperación:', error);
        showErrorElement(errorDiv, 'Error al procesar la solicitud. Intenta más tarde.');
    }
};

/**
 * Toggle AI Enhancements (modo visual / mejoras) —
 * This should NOT open the AI chat. Only toggle body class.
 */
if (typeof window.toggleAIEnhancements === 'undefined') {
    window.toggleAIEnhancements = function() {
        try {
            const body = document.body;
            if (!body) return false;
            const enabled = body.classList.toggle('ai-enhanced');
            if (window.UI && window.UI.toast) {
                window.UI.toast('Modo Futurista ' + (enabled ? 'activado' : 'desactivado'), 'info');
            }
            return false;
        } catch (e) { console.warn('toggleAIEnhancements failed', e); return false; }
    };
}

/**
 * Toggle AI Chat
 */
window.toggleAIChat = function() {
    const aiChatbot = document.getElementById('aiChatbot');
    if (aiChatbot) {
        aiChatbot.classList.toggle('active');
    }
};

/**
 * Send AI Message
 * Only define if not already provided by `js/ai.js` to avoid conflicts.
 */
if (typeof window.sendAIMessage === 'undefined') {
    window.sendAIMessage = async function(event) {
        event.preventDefault();

        const input = document.getElementById('aiChatInput');
        if (!input) {
            console.warn('sendAIMessage: input element not found');
            return;
        }
        const message = (input.value || '').trim();
        if (!message) return;

        const chatBody = document.getElementById('aiChatBody');
        if (!chatBody) {
            console.warn('sendAIMessage: chat body not found');
        }

        // Agregar mensaje del usuario (si existe el contenedor)
        if (chatBody) {
            const userMsg = document.createElement('div');
            userMsg.className = 'ai-chat-msg ai-chat-msg-user';
            userMsg.textContent = message;
            chatBody.appendChild(userMsg);
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        input.value = '';

        try {
            // Fallback simple: mostrar mensaje y esperar la respuesta del backend si existe
            const response = await fetch(buildApiUrl('/api/ai-chat'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            }).catch(()=>null);

            const data = response ? await response.json().catch(()=>null) : null;

            if (chatBody) {
                const botMsg = document.createElement('div');
                botMsg.className = 'ai-chat-msg ai-chat-msg-bot';
                botMsg.textContent = (data && (data.response || data.reply)) || 'No pude procesar tu pregunta';
                chatBody.appendChild(botMsg);
                chatBody.scrollTop = chatBody.scrollHeight;
            }
        } catch (error) {
            if (chatBody) {
                const botMsg = document.createElement('div');
                botMsg.className = 'ai-chat-msg ai-chat-msg-bot';
                botMsg.textContent = 'Error al procesar tu pregunta. Intenta más tarde.';
                chatBody.appendChild(botMsg);
            }
        }

        return false;
    };
}

/**
 * Inicialización al cargar el documento
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar calculadora si está disponible
    if (typeof Calculator !== 'undefined' && Calculator.init) {
        Calculator.init();
    }
    
    // Agregar estilos para animaciones si no existen
    if (!document.getElementById('login-animations-style')) {
        const style = document.createElement('style');
        style.id = 'login-animations-style';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
        // Crear región ARIA para notificaciones si no existe
        if (!document.getElementById('aria-notify')) {
            const aria = document.createElement('div');
            aria.id = 'aria-notify';
            aria.setAttribute('role', 'status');
            aria.setAttribute('aria-live', 'polite');
            aria.style.position = 'absolute';
            aria.style.left = '-9999px';
            aria.style.width = '1px';
            aria.style.height = '1px';
            aria.style.overflow = 'hidden';
            document.body.appendChild(aria);
        }

        // Bind automático para atributos data-onclick (mejora de usabilidad)
        // Safer: only allow simple function calls like "fnName()" or "fnName('arg')" to avoid executing arbitrary code
        (function(){
            function safeParseArgs(argsRaw) {
                if (!argsRaw) return [];
                try { return JSON.parse('[' + argsRaw + ']'); } catch (e) { /* ignore parse error */ }
                try {
                    const converted = argsRaw.replace(/'([^'\\]*(?:\\.[^'\\]*)*)'/g, function(_, s){ return '"' + s.replace(/"/g, '\\"') + '"'; });
                    return JSON.parse('[' + converted + ']');
                } catch (e) { /* ignore conversion parse error */ }
                return [ argsRaw.replace(/^\s*['"]?|['"]?\s*$/g, '') ];
            }

            document.querySelectorAll('[data-onclick]').forEach(function(el) {
                const code = (el.getAttribute('data-onclick') || '').trim();
                if (!code) return;
                // Match simple function call: name and optional args inside parentheses
                const m = code.match(/^([A-Za-z_$][0-9A-Za-z_$]*)\s*(?:\((.*)\))?\s*;?\s*$/);
                if (!m) {
                    console.warn('Skipping unsafe data-onclick:', code);
                    return;
                }
                const fnName = m[1];
                const argsRaw = m[2] || '';
                el.addEventListener('click', function(evt) {
                        try {
                            // Prevent default navigation for elements using data-onclick
                            try { evt.preventDefault(); } catch(e) { /* ignore */ }
                        const fn = window[fnName];
                        if (typeof fn !== 'function') {
                            console.warn('data-onclick function not found:', fnName);
                            return;
                        }
                        const args = safeParseArgs(argsRaw);
                        const result = fn.apply(this, args);
                        if (result === false) evt.preventDefault();
                    } catch (e) {
                        console.error('Error ejecutando safe data-onclick:', code, e);
                    }
                });
            });
        })();

        // Mejoras en la transición de formularios: añadir clases y animaciones
        ['login-form','register-form','recover-form'].forEach(function(id){
            const f = document.getElementById(id);
            if (f) {
                f.classList.add('form-panel');
                f.style.transition = 'opacity 0.32s ease, transform 0.32s ease, height 0.32s ease';
            }
        });
    
        // Log de inicialización
    console.log('✅ Login.js cargado correctamente');

        // Asegurar vista inicial: mostrar el formulario de login una sola vez al cargar
        try {
            setTimeout(function(){ if (typeof switchForm === 'function') switchForm('login-form'); }, 50);
        } catch(e) { /* silent */ }

    // Ensure register form helper exists and attach robust toggle handler
    window.prepareRegisterForm = function() {
        try {
            const dtEl = document.getElementById('reg-datetime');
            const dtDisplay = document.getElementById('reg-datetime-display');
            const updateDt = function() {
                const now = new Date();
                const iso = now.toISOString();
                if (dtEl) dtEl.value = iso;
                if (dtDisplay) dtDisplay.value = now.toLocaleString();
            };
            updateDt();
            if (dtEl && !dtEl._interval) dtEl._interval = setInterval(updateDt, 1000);

            const genBtn = document.getElementById('reg-admin-id-generate');
            const copyBtn = document.getElementById('reg-admin-id-copy');
            const adminIdEl = document.getElementById('reg-admin-id');
            if (genBtn && adminIdEl) {
                console.debug('[login.js] binding reg-admin-id-generate');
                genBtn.addEventListener('click', async function(){
                    try {
                        console.debug('[login.js] reg-admin-id-generate clicked');
                        const nombres = $trimVal('reg-nombres');
                        const apellidoP = $trimVal('reg-apellido-paterno');
                        const apellidoM = $trimVal('reg-apellido-materno');
                        const username = $trimVal('reg-username');
                        const email = $trimVal('reg-email');
                        const regDatetime = (dtEl && dtEl.value) || new Date().toISOString();
                        const input = [nombres, apellidoP, apellidoM, username, email, regDatetime].join('|');
                        const id = await generateIdFromString(input, 25).catch(()=>null);
                        if (id) adminIdEl.value = id;
                    } catch(e){console.warn('gen admin id failed', e)}
                });
            }
            if (copyBtn && adminIdEl) {
                console.debug('[login.js] binding reg-admin-id-copy');
                copyBtn.addEventListener('click', function(){
                    try {
                        console.debug('[login.js] reg-admin-id-copy clicked');
                        if (navigator.clipboard && adminIdEl.value) {
                            navigator.clipboard.writeText(adminIdEl.value);
                        } else if (adminIdEl.select) {
                            adminIdEl.select(); document.execCommand('copy');
                        }
                    } catch(e){ console.warn('copy admin id failed', e); }
                });
            }
        } catch(e) { console.warn('prepareRegisterForm error', e); }
    };

    // Robust toggle in case inline handler doesn't run or fails
    (function(){
        // If an inline handler already attached, don't attach again
        if (window.__registerToggleAttached) return;
        const btn = document.getElementById('show-register');
        const form = document.getElementById('register-form');
        if (!btn || !form) return;
        btn.addEventListener('click', function(e){
            e.preventDefault();
            try { console.debug('[login.js] register-toggle clicked'); } catch(e){ safeWarn('login.js:register-toggle click', e); }
            try {
                const isHidden = window.getComputedStyle(form).display === 'none' || form.style.display === 'none';
                try { console.debug('[login.js] register-toggle willShow=', isHidden); } catch(e){ safeWarn('login.js:register-toggle willShow', e); }
                if (isHidden) {
                    // prefer switchForm if available for animations
                    if (typeof switchForm === 'function') {
                        try { switchForm('register-form'); } catch(e){ form.style.display = 'block'; }
                    } else {
                        form.style.display = 'block';
                    }
                    try { console.debug('[login.js] calling prepareRegisterForm'); prepareRegisterForm(); } catch(e){ safeWarn('login.js:prepareRegisterForm', e); }
                    btn.textContent = 'Ocultar registro';
                    try { document.getElementById('reg-nombres').focus(); } catch(e){ safeWarn('login.js:focus reg-nombres', e); }
                } else {
                    if (typeof switchForm === 'function') {
                        try { switchForm('login-form'); } catch(e){ form.style.display = 'none'; }
                    } else {
                        form.style.display = 'none';
                    }
                    btn.textContent = 'Registrar administrador';
                    const dtEl = document.getElementById('reg-datetime'); if (dtEl && dtEl._interval) { clearInterval(dtEl._interval); dtEl._interval = null; }
                }
            } catch(err) {
                console.error('toggle register robust error', err);
                form.style.display = (form.style.display === 'none' ? 'block' : 'none');
            }
        });
        // mark as attached to avoid duplicate handlers from other scripts
        window.__registerToggleAttached = true;
    })();

    // Attach handlers for any toggle-pass buttons (show/hide password)
    (function(){
        try {
            const toggles = Array.from(document.querySelectorAll('button.toggle-pass'));
            toggles.forEach(function(btn){
                // don't attach twice
                if (btn.__toggle_pass_attached) return;
                const targetId = btn.getAttribute('data-target');
                if (!targetId) return;
                btn.addEventListener('click', function(){
                    try {
                        console.debug('[login.js] toggle-pass clicked, target=', targetId);
                        const inp = document.getElementById(targetId);
                        if (!inp) return;
                        if (inp.type === 'password') { inp.type = 'text'; btn.setAttribute('aria-label','Ocultar contraseña'); }
                        else { inp.type = 'password'; btn.setAttribute('aria-label','Mostrar contraseña'); }
                    } catch(e) { console.warn('toggle-pass click error', e); }
                });
                btn.__toggle_pass_attached = true;
            });
        } catch(e) { console.warn('attach toggle-pass failed', e); }
    })();

        // Toggle mostrar/ocultar contraseña
        const togglePwd = document.getElementById('toggle-password');
        if (togglePwd) {
            togglePwd.addEventListener('click', function() {
                const inp = document.getElementById('password');
                if (!inp) return;
                if (inp.type === 'password') {
                    inp.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    this.setAttribute('aria-label','Ocultar contraseña');
                    this.setAttribute('title','Ocultar contraseña');
                } else {
                    inp.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                    this.setAttribute('aria-label','Mostrar contraseña');
                    this.setAttribute('title','Mostrar contraseña');
                }
            });
        }
});

        // Bind logo modification controls: abrir file picker, previsualizar y guardar
        document.addEventListener('DOMContentLoaded', function(){
            try {
                const btnMod = document.getElementById('btn-modificar-login');
                const input = document.getElementById('logo-input-login');
                const btnGuardar = document.getElementById('btn-guardar-login');
                const btnCancelar = document.getElementById('btn-cancelar-login');
                const logoBox = document.getElementById('logo-box-login');
                const mainLogo = document.getElementById('main-logo-login');

                if (btnMod && input) {
                    btnMod.addEventListener('click', function(e){ e.preventDefault(); try{ input.click(); }catch(ex){ console.warn('open logo input failed', ex); } });
                }

                if (input) {
                    input.addEventListener('change', function(){
                        try {
                            const file = this.files && this.files[0];
                            if (!file) return;
                            const reader = new FileReader();
                            reader.onload = function(ev){
                                let img = document.getElementById('logo-preview-login');
                                if (!img) {
                                    img = document.createElement('img');
                                    img.id = 'logo-preview-login';
                                    img.style.maxWidth = '100%';
                                    img.style.maxHeight = '100%';
                                    img.style.borderRadius = '18px';
                                    img.style.objectFit = 'contain';
                                    img.style.position = 'relative';
                                    if (logoBox) logoBox.appendChild(img);
                                }
                                img.src = ev.target.result;
                                // ensure controls visible
                                const btns = document.getElementById('logo-btns-login');
                                if (btns) { btns.style.opacity = '1'; btns.style.pointerEvents = 'auto'; }
                            };
                            reader.readAsDataURL(file);
                        } catch(e){ console.warn('logo input change failed', e); }
                    });
                }

                if (btnGuardar) {
                    btnGuardar.addEventListener('click', function(e){
                        e.preventDefault();
                        try {
                            const img = document.getElementById('logo-preview-login');
                            if (!img) { showNotification('No hay imagen seleccionada', 'error'); return; }
                            // remove existing icon if present
                            try { if (mainLogo && mainLogo.parentNode) mainLogo.parentNode.removeChild(mainLogo); } catch(_){}
                            // ensure img fills the container
                            img.style.width = '100%'; img.style.height = '100%'; img.style.objectFit = 'cover';
                            const btns = document.getElementById('logo-btns-login'); if (btns) { btns.style.opacity = '0'; btns.style.pointerEvents = 'none'; }
                            showNotification('Logo actualizado', 'success');
                        } catch(e){ console.warn('guardar logo failed', e); showNotification('Error al guardar logo', 'error'); }
                    });
                }

                if (btnCancelar) {
                    btnCancelar.addEventListener('click', function(e){
                        e.preventDefault();
                        try {
                            const img = document.getElementById('logo-preview-login'); if (img && img.parentNode) img.parentNode.removeChild(img);
                            if (input) input.value = '';
                            const btns = document.getElementById('logo-btns-login'); if (btns) { btns.style.opacity = '0'; btns.style.pointerEvents = 'none'; }
                        } catch(e){ console.warn('cancelar logo failed', e); }
                    });
                }
            } catch(e){ console.warn('logo btns bind failed', e); }
        });

/**
 * Manejo de teclas para cerrar modales
 */
document.addEventListener('keydown', function(event) {
    // Cerrar modal con tecla Escape
    if (event.key === 'Escape') {
        const calcModal = document.getElementById('calculatorModal');
        
        if (calcModal && calcModal.classList.contains('active')) {
            window.toggleCalculator();
        }
    }
});

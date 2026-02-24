/* ARCHIVADO: script_v1.5.2_optimizado.js
   Archivo consolidado / duplicado. Se movió copia completa a
   `ARCHIVADOS/duplicados_20260204_085141/script_v1.5.2_optimizado.js`.
   Mensaje en consola para ayudar a detectar caché antigua.
*/
console.warn('⚠️ Archivo archivado. Use main.js y /js/ modules (archivo movido a ARCHIVADOS/duplicados_20260204_085141)');

/* EOF - script_v1.5.2_optimizado.js (placeholder) */
console.log('📦 Usando versión correcta: main.js + /js/ modules');


// ============================================================================
// UTILIDADES PARA ALMACENAMIENTO LOCAL (con fallback para Edge)
// ============================================================================

/**
 * Wrapper seguro para localStorage con soporte para Edge Tracking Prevention
 */
const SafeStorage = {
    /**
     * Obtiene un valor de localStorage con fallback seguro
     */
    getItem: function(key, defaultValue = null) {
        try {
            // Verificar que localStorage esté disponible
            if (typeof localStorage === 'undefined' || localStorage === null) {
                console.warn('⚠️ localStorage no disponible');
                return defaultValue;
            }
            
            const value = localStorage.getItem(key);
            return value ? value : defaultValue;
        } catch (error) {
            // Edge puede bloquear localStorage en Tracking Prevention
            console.warn('⚠️ No se puede acceder a localStorage:', error.message);
            return defaultValue;
        }
    },

    /**
     * Establece un valor en localStorage con fallback seguro
     */
    setItem: function(key, value) {
        try {
            if (typeof localStorage === 'undefined' || localStorage === null) {
                console.warn('⚠️ localStorage no disponible');
                return false;
            }
            
            localStorage.setItem(key, value);
            return true;
        } catch (error) {
            // Edge puede bloquear localStorage en Tracking Prevention
            console.warn('⚠️ No se puede escribir en localStorage:', error.message);
            return false;
        }
    },

    /**
     * Elimina un valor de localStorage con fallback seguro
     */
    removeItem: function(key) {
        try {
            if (typeof localStorage === 'undefined' || localStorage === null) {
                console.warn('⚠️ localStorage no disponible');
                return false;
            }
            
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.warn('⚠️ No se puede eliminar de localStorage:', error.message);
            return false;
        }
    },

    /**
     * Limpia todo localStorage con fallback seguro
     */
    clear: function() {
        try {
            if (typeof localStorage === 'undefined' || localStorage === null) {
                console.warn('⚠️ localStorage no disponible');
                return false;
            }
            
            localStorage.clear();
            return true;
        } catch (error) {
            console.warn('⚠️ No se puede limpiar localStorage:', error.message);
            return false;
        }
    }
};

// ============================================================================
// FUNCIONES GLOBALES - Toggle Calculator
// ============================================================================

/**
 * Toggle para abrir/cerrar modal de calculadora
 * Usa clase .active para visibilidad con opacity/visibility
 * 
 * @function
 * @returns {void}
 */
if (typeof window.toggleCalculator === 'undefined') {
    window.toggleCalculator = function() {
        console.log('📊 toggleCalculator() ejecutada');
        const modal = document.getElementById('calculatorModal');
        if (!modal) {
            console.error('❌ calculatorModal no encontrado en DOM');
            return;
        }
        
        const isActive = modal.classList.contains('active');
        
        if (isActive) {
            // Cerrar modal
            modal.classList.remove('active');
            console.log('✅ Calculadora cerrada');
            
            // Guardar estado
            SafeStorage.setItem('calc-state', 'closed');
        } else {
            // Abrir modal
            modal.classList.add('active');
            console.log('✅ Calculadora abierta');
            
            // Guardar estado
            SafeStorage.setItem('calc-state', 'open');
            
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
}

// ============================================================================
// FUNCIONES GLOBALES - Toggle AI Chat
// ============================================================================

/**
 * Toggle para abrir/cerrar chatbot AI
 * 
 * @function
 * @returns {void}
 */
if (typeof window.toggleAIChat === 'undefined') {
    window.toggleAIChat = function() {
        console.log('🤖 toggleAIChat() ejecutada');
        const chatbot = document.getElementById('aiChatbot');
        if (!chatbot) {
            console.error('❌ aiChatbot no encontrado en DOM');
            return;
        }
        
        const isActive = chatbot.classList.contains('active');
        
        if (isActive) {
            chatbot.classList.remove('active');
            console.log('✅ Chat IA cerrado');
            
            // Guardar estado
            SafeStorage.setItem('ai-chat-state', 'closed');
        } else {
            chatbot.classList.add('active');
            console.log('✅ Chat IA abierto');
            
            // Guardar estado
            SafeStorage.setItem('ai-chat-state', 'open');
            
            // Dar foco al input de mensajes
            const input = chatbot.querySelector('.ai-message-input');
            if (input) {
                setTimeout(() => input.focus(), 100);
            }
        }
    };
}

// ============================================================================
// MANEJO DE TECLAS GLOBALES
// ============================================================================

/**
 * Manejo de teclas para cerrar modales
 * - Escape: Cierra calculadora o chat IA
 */
if (typeof window.__keyboardHandlerInstalled === 'undefined') {
    window.__keyboardHandlerInstalled = true;
    
    document.addEventListener('keydown', function(event) {
        // Cerrar modal con tecla Escape
        if (event.key === 'Escape') {
            const calcModal = document.getElementById('calculatorModal');
            const aiChatbot = document.getElementById('aiChatbot');
            
            if (calcModal && calcModal.classList.contains('active')) {
                if (typeof window.toggleCalculator === 'function') {
                    window.toggleCalculator();
                }
            }
            
            if (aiChatbot && aiChatbot.classList.contains('active')) {
                if (typeof window.toggleAIChat === 'function') {
                    window.toggleAIChat();
                }
            }
        }
    });
}

// ============================================================================
// INICIALIZACIÓN AL CARGAR EL DOCUMENTO
// ============================================================================

/**
 * Evento DOMContentLoaded para verificaciones iniciales
 */
if (typeof window.__domInitialized === 'undefined') {
    window.__domInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 ====== DOMContentLoaded Event ======');
        
        // ===== VERIFICACIÓN 1: Protocolo =====
        if (window.location.protocol === 'file:') {
            console.error('❌ ERROR: Estás abriendo la página desde el sistema de archivos (file://)');
            console.error('⚠️ Esto causa errores CORS al intentar conectarse a los PHP endpoints');
            console.warn('');
            console.warn('📌 SOLUCIÓN: Accede vía HTTP en lugar de file://');
            console.warn('');
            console.log('Opciones para acceder correctamente:');
            console.log('1️⃣  http://localhost/Sakorms.org/Inventory-web1.5/');
            console.log('2️⃣  http://127.0.0.1/Sakorms.org/Inventory-web1.5/');
            console.log('3️⃣  http://localhost:80/Sakorms.org/Inventory-web1.5/ (si tienes XAMP en puerto 80)');
            console.log('4️⃣  http://localhost:8080/Sakorms.org/Inventory-web1.5/ (si tienes XAMP en puerto 8080)');
            console.warn('');
            console.log('Si XAMP está en otro puerto, verifica en XAMPP Control Panel');
            console.warn('');
            
            // Mostrar un dialogo visual
            const msg = document.createElement('div');
            msg.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 10000;">
                    <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                        <h2 style="color: #d32f2f; margin: 0 0 15px 0;">❌ Error de Configuración</h2>
                        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                            Estás abriendo la página desde el sistema de archivos, lo que causa errores CORS.
                        </p>
                        <p style="color: #666; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                            <strong>Debes acceder vía HTTP:</strong><br>
                            <code style="background: #f5f5f5; padding: 10px; border-radius: 5px; display: block; margin-top: 10px; font-family: monospace;">
                                ${ (window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('') : (location.protocol + '//' + location.host + '/') }
                            </code>
                        </p>
                        <p style="color: #999; font-size: 14px; margin: 0;">
                            Si tienes XAMP instalado, asegúrate de que está corriendo y accede usando la URL anterior.
                        </p>
                    </div>
                </div>
            `;
            document.body.appendChild(msg);
        }
        
        // ===== VERIFICACIÓN 2: Funciones Globales =====
        console.log('🔍 Verificando funciones globales:');
        console.log('  - window.toggleCalculator:', typeof window.toggleCalculator === 'function' ? '✅ Definida' : '❌ NO DEFINIDA');
        console.log('  - window.toggleAIChat:', typeof window.toggleAIChat === 'function' ? '✅ Definida' : '❌ NO DEFINIDA');
        
        // Fallback para funciones faltantes
        if (typeof window.toggleCalculator !== 'function') {
            console.error('❌ CRÍTICO: toggleCalculator no está disponible en window');
            window.toggleCalculator = function() {
                console.error('⚠️ toggleCalculator está siendo ejecutada sin estar adecuadamente inicializada');
            };
        }
        
        if (typeof window.toggleAIChat !== 'function') {
            console.error('❌ CRÍTICO: toggleAIChat no está disponible en window');
            window.toggleAIChat = function() {
                console.error('⚠️ toggleAIChat está siendo ejecutada sin estar adecuadamente inicializada');
            };
        }
        
        // ===== VERIFICACIÓN 3: Inicialización de Módulos =====
        console.log('🔍 Verificando módulos:');
        console.log('  - Calculator:', typeof Calculator !== 'undefined' ? '✅' : '⚠️ Aún no cargado');
        console.log('  - Sections:', typeof Sections !== 'undefined' ? '✅' : '⚠️ Aún no cargado');
        console.log('  - AI:', typeof AI !== 'undefined' ? '✅' : '⚠️ Aún no cargado');
        console.log('  - UI:', typeof UI !== 'undefined' ? '✅' : '⚠️ Aún no cargado');
        
        // Inicializar calculadora si está disponible
        if (typeof Calculator !== 'undefined' && typeof Calculator.init === 'function') {
            try {
                Calculator.init();
                console.log('✅ Calculadora inicializada');
            } catch (e) {
                console.warn('⚠️ Error inicializando calculadora:', e.message);
            }
        }
        
        // Restaurar estado previo de calculadora
        const calcState = SafeStorage.getItem('calc-state');
        if (calcState === 'open') {
            const modal = document.getElementById('calculatorModal');
            if (modal && !modal.classList.contains('active')) {
                setTimeout(() => {
                    if (typeof window.toggleCalculator === 'function') {
                        window.toggleCalculator();
                    }
                }, 500);
            }
        }
        
        // Restaurar estado previo del chat IA
        const aiState = SafeStorage.getItem('ai-chat-state');
        if (aiState === 'open') {
            const chatbot = document.getElementById('aiChatbot');
            if (chatbot && !chatbot.classList.contains('active')) {
                setTimeout(() => {
                    if (typeof window.toggleAIChat === 'function') {
                        window.toggleAIChat();
                    }
                }, 500);
            }
        }
        
        // Log de inicialización exitosa
        console.log('✅ Script.js inicialización completada (v1.5.2)');
        console.log('======================================');
    });
}

// ============================================================================
// VERIFICACIÓN FINAL: Asegurarse de que las funciones estén disponibles
// ============================================================================

/**
 * Verificación final de que las funciones estén disponibles globalmente
 * Se ejecuta después de un pequeño delay para garantizar que todo esté cargado
 */
if (typeof window.__finalCheckRun === 'undefined') {
    window.__finalCheckRun = true;
    
    setTimeout(function() {
        if (typeof window.toggleCalculator !== 'function') {
            console.error('❌ ALERTA: toggleCalculator aún no está disponible después de init');
        }
        
        if (typeof window.toggleAIChat !== 'function') {
            console.error('❌ ALERTA: toggleAIChat aún no está disponible después de init');
        }
        
        if (typeof window.toggleCalculator === 'function' && 
            typeof window.toggleAIChat === 'function') {
            console.log('✅ TODAS las funciones globales están disponibles');
        }
    }, 1000);
}

/**
 * NOTAS DE IMPLEMENTACIÓN:
 * 
 * v1.5.2 - Cambios Implementados:
 * ✅ Eliminado completamente el uso de campos privados (#btn)
 * ✅ Protección contra redeclaraciones de funciones usando typeof checks
 * ✅ SafeStorage wrapper para localStorage con fallback para Edge Tracking Prevention
 * ✅ Funciones definidas solo si no existen previamente
 * ✅ Manejo seguro de errores con try-catch en localStorage
 * ✅ Fallback handlers para si las funciones se llaman antes de estar inicializadas
 * ✅ Restauración de estado previo de modales al cargar la página
 * ✅ Verificación final de que todas las funciones estén disponibles
 * 
 * Compatibilidad:
 * ✅ Chrome, Firefox, Safari, Edge
 * ✅ Soporta Tracking Prevention de Edge
 * ✅ Fallback seguro si localStorage está bloqueado
 * ✅ No usa características experimentales
 */

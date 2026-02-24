// js/src/authManager.js - Class-based auth manager
(function(){
    class AuthManager {
        constructor(){
            this._user = null;
            this._loggedIn = false;
            // expose for backward compatibility
            window.Auth = window.Auth || this;
        }

        isLoggedIn(){ return !!this._loggedIn; }
        getUser(){ return this._user; }

        // simple login/logout helpers (no real auth, for UI only)
        login(user){ try { this._user = user || { name: 'Usuario' }; this._loggedIn = true; if (window.UI && typeof window.UI.toast === 'function') UI.toast('Sesión iniciada', 'success', 2200); } catch(e){ safeWarn('authManager.login', e); } }
        logout(){ try { this._user = null; this._loggedIn = false; if (window.UI && typeof window.UI.toast === 'function') UI.toast('Sesión cerrada', 'info', 1600); } catch(e){ safeWarn('authManager.logout', e); } }

        // restore state from localStorage (optional)
        restore(){ try { const raw = localStorage.getItem('auth_user'); if (raw) { this._user = JSON.parse(raw); this._loggedIn = true; } } catch(e){ safeWarn('authManager.restore', e); } }
        persist(){ try { if (this._user) localStorage.setItem('auth_user', JSON.stringify(this._user)); else localStorage.removeItem('auth_user'); } catch(e){ safeWarn('authManager.persist', e); } }
    }

    if (!window.AuthManager) window.AuthManager = AuthManager;
    if (!window.Auth || typeof window.Auth.isLoggedIn !== 'function') {
        try { window.Auth = new AuthManager(); } catch(e){ console.warn('AuthManager init failed', e); }
    }
})();

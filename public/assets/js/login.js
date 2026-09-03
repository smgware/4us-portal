(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function baseUrl() {
        if (typeof window.APP_BASE_URL === 'string') {
            return window.APP_BASE_URL.replace(/\/$/, '');
        }
        return '';
    }

    function showAlert(selector, type, message) {
        const el = document.querySelector(selector);
        if (!el) return;
        el.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-warning');
        el.classList.add('alert-' + type);
        el.textContent = message || 'Ismeretlen hiba történt.';
    }

    function hideAlert(selector) {
        const el = document.querySelector(selector);
        if (!el) return;
        el.classList.add('d-none');
        el.classList.remove('alert-danger', 'alert-success', 'alert-warning');
        el.textContent = '';
    }

    function readLoginParams(authContent) {
        const params = new URLSearchParams(window.location.search);

        // Támogatjuk ezt is: /login/&page=passwordreset&token=...
        const href = window.location.href;
        const ampIndex = href.indexOf('&page=');
        if (!params.get('page') && ampIndex !== -1) {
            const raw = href.substring(ampIndex + 1);
            const legacyParams = new URLSearchParams(raw);
            legacyParams.forEach(function (value, key) {
                params.set(key, value);
            });
        }

        return {
            page: params.get('page') || authContent.dataset.page || 'login',
            token: params.get('token') || authContent.dataset.token || ''
        };
    }

    async function fetchHtml(url, options) {
        const response = await fetch(url, Object.assign({
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }, options || {}));

        const text = await response.text();

        if (!response.ok) {
            throw new Error(text || 'HTTP hiba: ' + response.status);
        }

        return text;
    }

    async function fetchJson(url, formData) {
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        let data = null;
        const text = await response.text();
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            data = { success: false, message: text || 'Érvénytelen szerver válasz.' };
        }

        if (!response.ok) {
            const message = data && data.message ? data.message : 'HTTP hiba: ' + response.status;
            const error = new Error(message);
            error.data = data;
            throw error;
        }

        return data;
    }

    async function loadAuthPage(authContent, page, token) {
        let url = baseUrl() + '/auth/login/index';

        if (page === 'passwordreset') {
            url = baseUrl() + '/auth/passwordreset';
            if (token) {
                url += '?token=' + encodeURIComponent(token);
            }
        }

        authContent.innerHTML = '<div class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Betöltés...</div>';

        try {
            authContent.innerHTML = await fetchHtml(url);
        } catch (error) {
            authContent.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message || 'Nem sikerült betölteni az űrlapot.') + '</div><a href="#" class="forgot-link" data-auth-page="login">Vissza a bejelentkezéshez</a>';
        }
    }

    onReady(function () {
        if (window.__SIGMA_LOGIN_JS_LOADED__) return;
        window.__SIGMA_LOGIN_JS_LOADED__ = true;

        const authContent = document.getElementById('authContent');
        if (!authContent) return;

        const initial = readLoginParams(authContent);
        loadAuthPage(authContent, initial.page, initial.token);

        document.addEventListener('click', function (event) {
            const link = event.target.closest('[data-auth-page]');
            if (!link) return;

            event.preventDefault();
            const page = link.dataset.authPage || 'login';
            const url = page === 'passwordreset'
                ? baseUrl() + '/login?page=passwordreset'
                : baseUrl() + '/login';

            window.history.pushState({}, '', url);
            loadAuthPage(authContent, page, '');
        });

        window.addEventListener('popstate', function () {
            const params = readLoginParams(authContent);
            loadAuthPage(authContent, params.page, params.token);
        });

        document.addEventListener('submit', async function (event) {
            if (event.target && event.target.id === 'loginForm') {
                event.preventDefault();

                const btn = document.getElementById('loginBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Belépés...';
                }
                hideAlert('#loginAlert');

                const formData = new FormData(event.target);

                try {
                    const res = await fetchJson(baseUrl() + '/auth/login', formData);
                    if (res.success) {
                        showAlert('#loginAlert', 'success', res.message);
                        window.location.href = res.redirect;
                    }
                } catch (error) {
                    showAlert('#loginAlert', 'danger', error.message || 'Sikertelen bejelentkezés.');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Bejelentkezés';
                    }
                }
            }

            if (event.target && event.target.id === 'passwordResetRequestForm') {
                event.preventDefault();

                const btn = document.getElementById('passwordResetRequestBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Küldés...';
                }
                hideAlert('#passwordResetAlert');

                try {
                    const res = await fetchJson(baseUrl() + '/auth/passwordreset/save', new FormData(event.target));
                    showAlert('#passwordResetAlert', res.success ? 'success' : 'danger', res.message);
                } catch (error) {
                    showAlert('#passwordResetAlert', 'danger', error.message || 'Nem sikerült elküldeni a visszaállító linket.');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Visszaállító link küldése';
                    }
                }
            }

            if (event.target && event.target.id === 'passwordResetNewPasswordForm') {
                event.preventDefault();

                const btn = document.getElementById('passwordResetSaveBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mentés...';
                }
                hideAlert('#passwordResetAlert');

                try {
                    const res = await fetchJson(baseUrl() + '/auth/passwordreset/save', new FormData(event.target));
                    showAlert('#passwordResetAlert', res.success ? 'success' : 'danger', res.message);

                    if (res.success) {
                        window.setTimeout(function () {
                            window.history.pushState({}, '', baseUrl() + '/login');
                            loadAuthPage(authContent, 'login', '');
                        }, 1200);
                    }
                } catch (error) {
                    showAlert('#passwordResetAlert', 'danger', error.message || 'Nem sikerült menteni az új jelszót.');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Jelszó mentése';
                    }
                }
            }
        });
    });
})();

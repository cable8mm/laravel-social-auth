(function () {
    window.SocialAuth = window.SocialAuth || {};

    Object.assign(window.SocialAuth, {
        googleInitialized: false,
        googleSdkPromise: null,
        appleInitialized: false,
        appleInitializing: false,

        async fetchNonce(element = document.querySelector('[data-provider="google"][data-nonce-url]')) {
            const nonceUrl = element?.dataset.nonceUrl;
            if (!nonceUrl) {
                throw new Error('Google nonce URL is missing');
            }

            const url = new URL(nonceUrl, window.location.origin);
            url.searchParams.set('redirect', element?.dataset.intendedUrl || window.location.pathname + window.location.search);
            url.searchParams.set('context', element?.dataset.context || 'login');
            url.searchParams.set('provider', element?.dataset.provider || 'google');
            const res = await fetch(url, { credentials: 'same-origin' });
            const data = await res.json();
            return data.nonce;
        },

        async fetchState(element = document.querySelector('[data-provider="kakao"], [data-provider="naver"]')) {
            const url = new URL(element?.dataset.stateUrl, window.location.origin);
            url.searchParams.set('redirect', element?.dataset.intendedUrl || window.location.pathname + window.location.search);
            url.searchParams.set('context', element?.dataset.context || 'login');
            url.searchParams.set('provider', element?.dataset.provider || '');
            const res = await fetch(url, { credentials: 'same-origin' });
            const data = await res.json();
            return data.state;
        },

        async postCallback(provider, payload, context = 'login') {
            const element = document.querySelector(`[data-provider="${provider}"][data-context="${context}"]`)
                || document.querySelector(`[data-provider="${provider}"]`);
            const res = await fetch(element?.dataset.callbackUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            } else if (data.status === 'connected' || data.status === 'logged_in') {
                window.location.reload();
            } else if (data.error) {
                alert(data.error);
            }
            return data;
        },

        async loginKakao(context) {
            if (typeof Kakao === 'undefined') {
                console.error('Kakao SDK not loaded');
                return;
            }
            const element = document.querySelector(`[data-provider="kakao"][data-context="${context}"]`);
            if (!element) return;

            if (!Kakao.isInitialized()) {
                Kakao.init(element.dataset.clientId);
            }

            const state = await this.fetchState(element);
            Kakao.Auth.authorize({
                redirectUri: element.dataset.callbackUrl,
                state,
                throughTalk: true,
            });
        },

        async loginNaver(context) {
            const element = document.querySelector(`[data-provider="naver"][data-context="${context}"]`);
            if (!element || typeof naver === 'undefined' || !naver.LoginWithNaverId) return;

            const state = await this.fetchState(element);
            const naverLogin = new naver.LoginWithNaverId({
                clientId: element.dataset.clientId,
                callbackUrl: element.dataset.callbackUrl,
                isPopup: false,
                loginButton: null,
            });

            naverLogin.generateState = () => {
                naverLogin.state = state;
                return state;
            };

            naverLogin.init();
            naverLogin.authorize();
        },

        async initApple(context) {
            if (typeof AppleID === 'undefined' || !AppleID.auth) {
                console.error('Apple JS SDK not loaded');
                return;
            }

            if (this.appleInitialized || this.appleInitializing) return;

            const element = document.querySelector(`[data-provider="apple"][data-context="${context}"]`);
            if (!element) return;

            this.appleInitializing = true;

            try {
                const [state, nonce] = await Promise.all([
                    this.fetchState(element),
                    this.fetchNonce(element),
                ]);

                AppleID.auth.init({
                    clientId: element.dataset.clientId,
                    scope: 'name email',
                    redirectURI: element.dataset.redirectUrl || element.dataset.callbackUrl,
                    state,
                    nonce,
                    usePopup: false,
                });

                this.appleInitialized = true;
            } finally {
                this.appleInitializing = false;
            }
        },

        async loadGoogleSdk(element) {
            if (typeof google !== 'undefined' && google.accounts?.id) return;
            if (this.googleSdkPromise) return this.googleSdkPromise;

            const src = element.dataset.googleSdkUrl;
            if (!src) return;

            this.googleSdkPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.defer = true;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });

            try {
                await this.googleSdkPromise;
            } catch (error) {
                this.googleSdkPromise = null;
                throw error;
            }
        },

        async initGoogle() {
            const elements = [...document.querySelectorAll('[data-provider="google"][data-client-id][data-nonce-url]')];
            const element = elements[0];
            if (!element || this.googleInitialized) return;

            try {
                await this.loadGoogleSdk(element);
            } catch (error) {
                console.error('Google GIS SDK failed to load', error);
                return;
            }

            if (typeof google === 'undefined' || !google.accounts?.id) {
                console.error('Google GIS SDK not available');
                return;
            }

            this.googleInitialized = true;
            try {
                const nonce = await this.fetchNonce(element);
                google.accounts.id.initialize({
                    client_id: element.dataset.clientId,
                    nonce,
                    callback: response => {
                        this.postCallback('google', { credential: response.credential }, element.dataset.context);
                    },
                });

                elements.forEach(item => {
                    const target = item.querySelector('.google-gis-button');
                    if (target) {
                        target.replaceChildren();
                        const options = {
                            theme: 'outline',
                            size: 'large',
                            text: item.dataset.context === 'register'
                                ? 'signup_with'
                                : item.dataset.context === 'connect'
                                    ? 'continue_with'
                                    : 'signin_with',
                            width: Number(item.dataset.googleButtonWidth || 280),
                        };

                        google.accounts.id.renderButton(target, options);
                    }
                });

                if (document.querySelector('[data-google-one-tap]')) {
                    google.accounts.id.prompt();
                }
            } catch (error) {
                this.googleInitialized = false;
                console.error('Google GIS initialization failed', error);
            }
        },

    });

    function bootSocialAuth() {
        if (document.querySelector('[data-provider="google"]')) {
            const googleTimer = setInterval(() => {
                if (typeof google !== 'undefined' && google.accounts) {
                    clearInterval(googleTimer);
                    window.SocialAuth.initGoogle();
                }
            }, 100);
            setTimeout(() => clearInterval(googleTimer), 5000);
        }

        if (document.querySelector('[data-provider="apple"]')) {
            const appleTimer = setInterval(() => {
                if (typeof AppleID !== 'undefined' && AppleID.auth) {
                    clearInterval(appleTimer);
                    const element = document.querySelector('[data-provider="apple"]');
                    window.SocialAuth.initApple(element.dataset.context);
                }
            }, 100);
            setTimeout(() => clearInterval(appleTimer), 5000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootSocialAuth);
    } else {
        bootSocialAuth();
    }
})();

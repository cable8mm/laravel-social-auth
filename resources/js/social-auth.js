(function () {
    window.SocialAuth = window.SocialAuth || {};

    Object.assign(window.SocialAuth, {
        googleInitialized: false,

        async fetchNonce(element = document.querySelector('[data-provider="google"]')) {
            const url = new URL(element?.dataset.nonceUrl, window.location.origin);
            url.searchParams.set('redirect', element?.dataset.intendedUrl || window.location.pathname + window.location.search);
            const res = await fetch(url, { credentials: 'same-origin' });
            const data = await res.json();
            return data.nonce;
        },

        async fetchState(element = document.querySelector('[data-provider="kakao"], [data-provider="naver"]')) {
            const url = new URL(element?.dataset.stateUrl, window.location.origin);
            url.searchParams.set('redirect', element?.dataset.intendedUrl || window.location.pathname + window.location.search);
            const res = await fetch(url, { credentials: 'same-origin' });
            const data = await res.json();
            return data.state;
        },

        async postCallback(provider, payload) {
            const element = document.querySelector(`[data-provider="${provider}"]`);
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

        async loginApple(context) {
            if (typeof AppleID === 'undefined' || !AppleID.auth) {
                console.error('Apple JS SDK not loaded');
                return;
            }

            const element = document.querySelector(`[data-provider="apple"][data-context="${context}"]`);
            if (!element) return;

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

            AppleID.auth.signIn();
        },

        initGoogle() {
            const elements = document.querySelectorAll('[data-provider="google"]');
            const element = elements[0];
            if (!element || typeof google === 'undefined' || !google.accounts?.id || this.googleInitialized) return;

            this.googleInitialized = true;
                this.fetchNonce(element).then(nonce => {
                google.accounts.id.initialize({
                    client_id: element.dataset.clientId,
                    nonce,
                    callback: response => {
                        this.postCallback('google', { credential: response.credential });
                    },
                });

                elements.forEach(item => {
                    const target = item.querySelector('.google-gis-button');
                    if (target) {
                        google.accounts.id.renderButton(target, { theme: 'outline', size: 'large', width: 280 });
                    }
                });

                if (document.querySelector('[data-google-one-tap]')) {
                    google.accounts.id.prompt();
                }
            });
        },

        initNaver(context) {
            const element = document.querySelector(`[data-provider="naver"][data-context="${context}"]`);
            if (!element || typeof naver === 'undefined') return;

                this.fetchState(element).then(state => {
                const naverLogin = new naver.LoginWithNaverId({
                    clientId: element.dataset.clientId,
                    callbackUrl: element.dataset.callbackUrl,
                    isPopup: false,
                    loginButton: { color: 'green', type: 3, height: 48 },
                });

                naverLogin.generateState = () => {
                    naverLogin.state = state;
                    return state;
                };

                naverLogin.init();
            });
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

        if (document.querySelector('[data-provider="naver"]')) {
            const naverTimer = setInterval(() => {
                if (typeof naver !== 'undefined' && naver.LoginWithNaverId) {
                    clearInterval(naverTimer);
                    document.querySelectorAll('[data-provider="naver"]').forEach(element => {
                        window.SocialAuth.initNaver(element.dataset.context);
                    });
                }
            }, 100);
            setTimeout(() => clearInterval(naverTimer), 5000);
        }

        if (document.querySelector('[data-provider="apple"]')) {
            const appleTimer = setInterval(() => {
                if (typeof AppleID !== 'undefined' && AppleID.auth) {
                    clearInterval(appleTimer);
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

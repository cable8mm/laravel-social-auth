{{-- Include this once in your layout after provider buttons --}}
@once
<script>
window.SocialAuth = window.SocialAuth || {
    async fetchNonce() {
        const res = await fetch(@json(route('social-auth.nonce')), { credentials: 'same-origin' });
        const data = await res.json();
        return data.nonce;
    },
    async fetchState() {
        const res = await fetch(@json(route('social-auth.state')), { credentials: 'same-origin' });
        const data = await res.json();
        return data.state;
    },
    async postCallback(provider, payload) {
        const url = document.querySelector(`[data-provider="${provider}"]`)?.dataset.callbackUrl;
        const res = await fetch(url, {
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
        const el = document.querySelector(`[data-provider="kakao"][data-context="${context}"]`);
        if (!el) return;
        const clientId = el.dataset.clientId;
        if (!Kakao.isInitialized()) {
            Kakao.init(clientId);
        }
        const state = await this.fetchState();
        Kakao.Auth.authorize({
            redirectUri: el.dataset.callbackUrl.replace(/\/callback$/, '/callback'), // app sets actual redirect
            state: state,
            throughTalk: true,
        });
        // Note: for SPA-style, prefer Kakao.Auth.login and then post code to server
    },
    initGoogle() {
        const elements = document.querySelectorAll('[data-provider="google"]');
        const el = elements[0];
        if (!el || typeof google === 'undefined' || !google.accounts?.id || this.googleInitialized) return;

        this.googleInitialized = true;
        const clientId = el.dataset.clientId;
        this.fetchNonce().then(nonce => {
            google.accounts.id.initialize({
                client_id: clientId,
                nonce: nonce,
                callback: (response) => {
                    this.postCallback('google', { credential: response.credential });
                },
            });
            elements.forEach(element => {
                const target = element.querySelector('.google-gis-button');
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
        const el = document.querySelector(`[data-provider="naver"][data-context="${context}"]`);
        if (!el || typeof naver === 'undefined') return;
        const clientId = el.dataset.clientId;
        this.fetchState().then(state => {
            const naverLogin = new naver.LoginWithNaverId({
                clientId: clientId,
                callbackUrl: el.dataset.callbackUrl,
                isPopup: false,
                loginButton: { color: 'green', type: 3, height: 48 },
            });

            // Keep the SDK's OAuth state aligned with the server-side session state.
            naverLogin.generateState = () => {
                naverLogin.state = state;
                return state;
            };

            naverLogin.init();
        });
    }
};

function bootSocialAuth() {
    if (document.querySelector('[data-provider="google"]')) {
        // Google GIS loads async; poll briefly
        const t = setInterval(() => {
            if (typeof google !== 'undefined' && google.accounts) {
                clearInterval(t);
                window.SocialAuth.initGoogle();
            }
        }, 100);
        setTimeout(() => clearInterval(t), 5000);
    }

    if (document.querySelector('[data-provider="naver"]')) {
        const t = setInterval(() => {
            if (typeof naver !== 'undefined' && naver.LoginWithNaverId) {
                clearInterval(t);
                document.querySelectorAll('[data-provider="naver"]').forEach(el => {
                    window.SocialAuth.initNaver(el.dataset.context);
                });
            }
        }, 100);
        setTimeout(() => clearInterval(t), 5000);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSocialAuth);
} else {
    bootSocialAuth();
}
</script>
@endonce

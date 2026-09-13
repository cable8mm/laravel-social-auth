{{-- Include this once in your layout after provider buttons --}}
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
    initGoogle(context) {
        const el = document.querySelector(`[data-provider="google"][data-context="${context}"]`);
        if (!el || typeof google === 'undefined') return;
        const clientId = el.dataset.clientId;
        this.fetchNonce().then(nonce => {
            google.accounts.id.initialize({
                client_id: clientId,
                nonce: nonce,
                callback: (response) => {
                    this.postCallback('google', { credential: response.credential });
                },
            });
            const target = document.getElementById(`google-btn-${context}`);
            if (target) {
                google.accounts.id.renderButton(target, { theme: 'outline', size: 'large', width: 280 });
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
            naverLogin.init();
            // After redirect back, app should read access token from hash/query and POST to callback
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-provider="google"]')) {
        // Google GIS loads async; poll briefly
        const t = setInterval(() => {
            if (typeof google !== 'undefined' && google.accounts) {
                clearInterval(t);
                document.querySelectorAll('[data-provider="google"]').forEach(el => {
                    window.SocialAuth.initGoogle(el.dataset.context);
                });
            }
        }, 100);
        setTimeout(() => clearInterval(t), 5000);
    }
});
</script>

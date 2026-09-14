<style>
    .social-auth-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-inline: auto;
        width: min(100%, 17.5rem);
    }

    .social-btn {
        display: block;
        max-width: 17.5rem;
        min-height: 3rem;
        width: 100%;
    }

    .social-btn-google,
    .social-btn-kakao,
    .social-btn-naver {
        min-height: 3rem;
        width: 100%;
    }

    .social-btn-naver {
        align-items: center;
        background: #00c73c;
        border-radius: 0.75rem;
        display: flex;
        justify-content: center;
        overflow: hidden;
    }

    .social-btn-naver #naverIdLogin_loginButton {
        align-items: center;
        background: #00c73c !important;
        display: flex !important;
        height: 3rem !important;
        justify-content: center;
        max-width: 100%;
        width: 100% !important;
    }

    .social-btn-naver #naverIdLogin_loginButton img {
        display: block;
        height: 3rem !important;
        max-width: 100% !important;
        object-fit: contain;
        width: auto !important;
    }

    .social-btn-kakao .btn-kakao {
        align-items: center;
        background: #fee500;
        border: 0;
        border-radius: 0.75rem;
        box-sizing: border-box;
        color: #191919;
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 0.9375rem;
        font-weight: 700;
        justify-content: center;
        line-height: 1.25;
        min-height: 3rem;
        padding: 0.75rem 1rem;
        position: relative;
        transition: background-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
        width: 100%;
    }

    .social-btn-kakao .btn-kakao::before {
        background: #191919;
        border-radius: 50%;
        content: "";
        height: 1.125rem;
        margin-right: 0.625rem;
        mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='white' d='M12 3C6.477 3 2 6.582 2 11c0 2.835 1.87 5.32 4.69 6.72L5.5 21l4.04-2.05c.79.16 1.61.25 2.46.25 5.523 0 10-3.582 10-8.2S17.523 3 12 3Z'/%3E%3C/svg%3E") center / contain no-repeat;
        width: 1.125rem;
    }

    .social-btn-kakao .btn-kakao:hover {
        background: #f5dc00;
        box-shadow: 0 0.25rem 0.75rem rgb(25 25 25 / 12%);
        transform: translateY(-1px);
    }

    .social-btn-kakao .btn-kakao:focus-visible {
        outline: 0.1875rem solid rgb(25 25 25 / 35%);
        outline-offset: 0.125rem;
    }

    .social-btn-kakao .btn-kakao:active {
        transform: translateY(0);
    }
</style>

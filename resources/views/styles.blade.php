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
    .social-btn-naver,
    .social-btn-apple {
        min-height: 3rem;
        width: 100%;
    }

    .social-btn-naver {
        background: #03a94d;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .social-btn-naver .btn-naver {
        align-items: center;
        background: #03a94d;
        border: 0;
        border-radius: inherit;
        box-sizing: border-box;
        color: #fff;
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 0.9375rem;
        font-weight: 700;
        justify-content: center;
        line-height: 1.25;
        min-height: 3rem;
        padding: 0.75rem 1rem;
        transition: background-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
        width: 100%;
    }

    .social-btn-naver .naver-symbol {
        fill: currentColor;
        color: #fff;
        height: 1.5rem;
        margin-right: 0.625rem;
        width: 1.5rem;
    }

    .social-btn-naver .btn-naver:hover {
        background: #029344;
        box-shadow: 0 0.25rem 0.75rem rgb(0 0 0 / 12%);
        transform: translateY(-1px);
    }

    .social-btn-naver .btn-naver:focus-visible {
        outline: 0.1875rem solid rgb(3 169 77 / 35%);
        outline-offset: 0.125rem;
    }

    .social-btn-naver .btn-naver:active {
        transform: translateY(0);
    }

    .social-btn-kakao .btn-kakao {
        align-items: center;
        background: #fee500;
        border: 0;
        border-radius: 0.75rem;
        box-sizing: border-box;
        color: rgb(0 0 0 / 85%);
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

    .social-btn-kakao .kakao-symbol {
        fill: currentColor;
        height: 1.125rem;
        margin-right: 0.625rem;
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

    .social-btn-apple #appleid-signin {
        min-height: 3rem;
        width: 100%;
    }
</style>

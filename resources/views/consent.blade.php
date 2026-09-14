<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>약관 동의</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
        .term { margin: 1rem 0; }
        .term label { display: flex; align-items: flex-start; gap: .5rem; }
        .error { color: #c00; margin-bottom: 1rem; }
        button[type=submit] { margin-top: 1.5rem; padding: .75rem 1.5rem; background: #333; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>약관 동의</h1>
    <p>{{ ucfirst($pending['provider'] ?? '') }} 계정으로 가입을 완료하려면 아래 약관에 동의해 주세요.</p>

    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('social-auth.consent.submit') }}">
        @csrf

        @foreach($terms as $key => $term)
            <div class="term">
                <label>
                    <input
                        type="checkbox"
                        name="{{ $key }}"
                        value="1"
                        @if($term['required'] ?? false) required @endif
                    >
                    <span>
                        @if(!empty($term['url']))
                            <a href="{{ $term['url'] }}" target="_blank" rel="noopener">{{ $term['label'] }}</a>
                        @else
                            {{ $term['label'] }}
                        @endif
                        @if($term['required'] ?? false)
                            <strong>(필수)</strong>
                        @else
                            (선택)
                        @endif
                    </span>
                </label>
            </div>
        @endforeach

        <button type="submit">동의하고 가입 완료</button>
    </form>
</body>
</html>

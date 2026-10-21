<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Data;

use Illuminate\Http\Client\Response;

/**
 * Error details a provider returned for a failed request, with secrets redacted.
 * Kept for logging and listeners only; never shown to end users.
 */
final class ProviderError
{
    private const REDACTED = '[redacted]';

    private const MAX_LENGTH = 500;

    public function __construct(
        public readonly int $status,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorDescription = null,
    ) {}

    /**
     * Recorded response shapes:
     * - Kakao kauth: {"error", "error_description", "error_code": "KOE320"}
     * - Kakao kapi:  {"msg", "code": -401}
     * - Naver profile: {"resultcode": "024", "message"}
     * - Naver agreement: {"error", "detail": {"reason"}}
     * - Apple token: {"error", "error_description"}
     * - GitHub token: {"error", "error_description", "error_uri"} (sent with HTTP 200)
     * - GitHub REST: {"message", "documentation_url"}
     *
     * @param  list<string|null>  $secrets  Values sent with the request that must never be kept.
     */
    public static function fromResponse(Response $response, array $secrets = []): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $detail = is_array($body['detail'] ?? null) ? $body['detail'] : [];

        return new self(
            status: $response->status(),
            error: self::clean($body['error'] ?? null, $secrets),
            errorCode: self::clean($body['error_code'] ?? $body['code'] ?? $body['resultcode'] ?? $detail['reason'] ?? null, $secrets),
            errorDescription: self::clean($body['error_description'] ?? $body['msg'] ?? $body['message'] ?? null, $secrets),
        );
    }

    /**
     * @return array{status: int, error: string|null, error_code: string|null, error_description: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'error' => $this->error,
            'error_code' => $this->errorCode,
            'error_description' => $this->errorDescription,
        ];
    }

    /**
     * @param  list<string|null>  $secrets
     */
    private static function clean(mixed $value, array $secrets): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = (string) $value;

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $value = str_replace($secret, self::REDACTED, $value);
            }
        }

        // Kakao echoes the authorization code, e.g. "authorization code not found for code=...".
        $value = (string) preg_replace(
            '/\b(code|access_token|refresh_token|id_token|client_secret|token)=[^\s&,;]+/i',
            '$1='.self::REDACTED,
            $value,
        );

        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}

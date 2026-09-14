<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;

class NaverDisconnectCallbackVerifier
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function verify(
        ?string $callbackClientId,
        ?string $encryptedUniqueId,
        ?string $timestamp,
        ?string $signature,
    ): string {
        if ($callbackClientId !== $this->clientId
            || ! is_string($encryptedUniqueId)
            || $encryptedUniqueId === ''
            || ! is_string($timestamp)
            || $timestamp === ''
            || ! is_string($signature)
            || $signature === '') {
            throw SocialAuthException::verificationFailed('naver', 'Invalid disconnect callback');
        }

        $key = $this->key();
        $signatureBase = "clientId={$this->clientId}&encryptUniqueId={$encryptedUniqueId}&timestamp={$timestamp}";
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $signatureBase, $key, true));

        if (! hash_equals($expectedSignature, $signature)) {
            throw SocialAuthException::verificationFailed('naver', 'Invalid disconnect callback signature');
        }

        $decoded = base64_decode(strtr($encryptedUniqueId, '-_', '+/'), true);
        if ($decoded === false || strlen($decoded) <= 16) {
            throw SocialAuthException::verificationFailed('naver', 'Invalid encrypted user ID');
        }

        $uniqueId = openssl_decrypt(
            substr($decoded, 16),
            'AES-128-CBC',
            $key,
            OPENSSL_RAW_DATA,
            substr($decoded, 0, 16),
        );

        if (! is_string($uniqueId) || $uniqueId === '') {
            throw SocialAuthException::verificationFailed('naver', 'Unable to decrypt user ID');
        }

        return $uniqueId;
    }

    private function key(): string
    {
        return substr(md5($this->clientSecret, true), 0, 16);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

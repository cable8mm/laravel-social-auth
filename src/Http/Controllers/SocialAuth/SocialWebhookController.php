<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth;

use Cable8mm\LaravelSocialAuth\Events\SocialAccountStatusChanged;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialAccountService;
use Cable8mm\LaravelSocialAuth\Verifiers\KakaoAccountStatusWebhookVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\NaverDisconnectCallbackVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class SocialWebhookController extends Controller
{
    public function __construct(
        private readonly SocialAccountService $accountService,
    ) {}

    public function kakaoAccountStatus(Request $request): JsonResponse
    {
        try {
            $claims = (new KakaoAccountStatusWebhookVerifier(
                (string) config('social-auth.providers.kakao.client_id'),
            ))->verify($request->getContent());

            foreach ($claims['events'] as $eventType => $event) {
                if (! is_array($event)) {
                    continue;
                }

                $providerId = $this->providerId($event);
                $eventPayload = $this->eventPayload($event);

                event(new SocialAccountStatusChanged(
                    provider: 'kakao',
                    providerId: $providerId,
                    eventType: (string) $eventType,
                    eventPayload: $eventPayload,
                ));

                if ($providerId === null) {
                    continue;
                }

                $account = $this->accountService->findByProvider('kakao', $providerId);
                if ($account === null) {
                    continue;
                }

                if (in_array($eventType, [
                    'https://schemas.openid.net/secevent/oauth/event-type/tokens-revoked',
                    'https://schemas.openid.net/secevent/risc/event-type/sessions-revoked',
                ], true)) {
                    $this->accountService->clearTokens($account);
                }

                if ($eventType === 'https://schemas.openid.net/secevent/risc/event-type/account-purged') {
                    $this->accountService->delete($account);
                }
            }

            return response()->json(null, 202);
        } catch (SocialAuthException) {
            return response()->json([
                'err' => 'invalid_request',
                'description' => 'The webhook payload could not be verified.',
            ], 400);
        }
    }

    public function naverDisconnect(Request $request): Response
    {
        try {
            $providerId = (new NaverDisconnectCallbackVerifier(
                (string) config('social-auth.providers.naver.client_id'),
                (string) config('social-auth.providers.naver.client_secret'),
            ))->verify(
                $request->input('clientId'),
                $request->input('encryptUniqueId'),
                $request->input('timestamp'),
                $request->input('signature'),
            );

            $account = $this->accountService->findByProvider('naver', $providerId);
            if ($account !== null) {
                event(new SocialAccountStatusChanged(
                    provider: 'naver',
                    providerId: $providerId,
                    eventType: 'connection-revoked',
                    eventPayload: [],
                ));
                $this->accountService->delete($account);
            }

            return response()->noContent();
        } catch (SocialAuthException) {
            return response()->json(['error' => 'invalid_request'], 400);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function providerId(array $event): ?string
    {
        $subject = $event['subject'] ?? null;
        $providerId = is_array($subject) ? $subject['sub'] ?? null : null;

        return is_string($providerId) && $providerId !== '' ? $providerId : null;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function eventPayload(array $event): array
    {
        return array_filter(
            $event,
            static fn (mixed $value, string|int $key): bool => $key !== 'subject' || is_array($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}

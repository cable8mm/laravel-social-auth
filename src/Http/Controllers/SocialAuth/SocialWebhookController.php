<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth;

use Cable8mm\LaravelSocialAuth\Events\SocialAccountStatusChanged;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialAccountService;
use Cable8mm\LaravelSocialAuth\Verifiers\AppleServerNotificationVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\GoogleRiscWebhookVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\KakaoAccountStatusWebhookVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\NaverDisconnectCallbackVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SocialWebhookController extends Controller
{
    /** How long a verified delivery is remembered so a replay of it is ignored. */
    private const REPLAY_WINDOW_DAYS = 30;

    public function __construct(
        private readonly SocialAccountService $accountService,
    ) {}

    public function kakaoAccountStatus(Request $request): JsonResponse
    {
        try {
            $claims = (new KakaoAccountStatusWebhookVerifier(
                (string) config('social-auth.providers.kakao.client_id'),
            ))->verify($request->getContent());

            if (! $this->isFirstDelivery('kakao', $claims['jti'])) {
                return response()->json(null, 202);
            }

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

    public function googleRisc(Request $request): JsonResponse
    {
        try {
            $claims = (new GoogleRiscWebhookVerifier(
                (string) config('social-auth.providers.google.client_id'),
            ))->verify($request->getContent());

            if (! $this->isFirstDelivery('google', $claims['jti'])) {
                return response()->json(null, 202);
            }

            foreach ($claims['events'] as $eventType => $event) {
                if (! is_array($event)) {
                    continue;
                }

                $providerId = $this->providerId($event);
                event(new SocialAccountStatusChanged(
                    provider: 'google',
                    providerId: $providerId,
                    eventType: (string) $eventType,
                    eventPayload: $this->eventPayload($event),
                ));
            }

            return response()->json(null, 202);
        } catch (SocialAuthException) {
            return response()->json([
                'err' => 'invalid_request',
                'description' => 'The RISC payload could not be verified.',
            ], 400);
        }
    }

    public function apple(Request $request): JsonResponse
    {
        try {
            $notification = (new AppleServerNotificationVerifier(
                (string) config('social-auth.providers.apple.client_id'),
            ))->verify($request->getContent());
            $event = $notification['event'];
            $providerId = (string) $event['sub'];
            $eventType = (string) $event['type'];

            event(new SocialAccountStatusChanged(
                provider: 'apple',
                providerId: $providerId,
                eventType: $eventType,
                eventPayload: $event,
            ));

            if (in_array($eventType, ['consent-revoked', 'account-deleted'], true)) {
                $account = $this->accountService->findByProvider('apple', $providerId);
                if ($account !== null) {
                    $this->accountService->delete($account);
                }
            }

            return response()->json(null, 200);
        } catch (SocialAuthException) {
            return response()->json([
                'error' => 'invalid_request',
                'description' => 'The Apple notification could not be verified.',
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

            // The signature covers the user ID and timestamp, so it identifies one callback.
            if (! $this->isFirstDelivery('naver', (string) $request->input('signature'))) {
                return response()->noContent();
            }

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
     * Records a verified delivery and reports whether it was seen before. Call only after verification,
     * so unverified requests cannot mark an ID as delivered.
     */
    private function isFirstDelivery(string $provider, string $deliveryId): bool
    {
        return Cache::add(
            "social_auth.webhook_delivery.{$provider}.".hash('sha256', $deliveryId),
            true,
            now()->addDays(self::REPLAY_WINDOW_DAYS),
        );
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

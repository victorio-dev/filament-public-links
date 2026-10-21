<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Illuminate\Http\Request;
use Victorio\PublicLinks\Enums\AccessOutcome;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;

/**
 * Writes one access row per public request (rule E4): outcome, HMAC of the IP, truncated user
 * agent, time, and the link's tenant. Never the token, never the raw IP (rule E5). Rate-limited
 * (429) requests are not recorded.
 *
 * @internal
 */
final class AccessRecorder
{
    public function record(
        ?PublicLink $link,
        AccessOutcome $outcome,
        Request $request,
        ?string $action = null,
    ): PublicLinkAccess {
        $model = PublicLinksConfig::accessModel();
        $userAgent = $request->userAgent();

        $access = new $model([
            'public_link_id' => $link?->getKey(),
            'outcome' => $outcome->value,
            'action' => $action,
            'ip_hash' => IpHasher::forRequest($request),
            'user_agent' => $userAgent === null || $userAgent === ''
                ? null
                : mb_substr($userAgent, 0, PublicLinksConfig::userAgentMaxLength()),
            'accessed_at' => now(),
        ]);

        $tenantColumn = PublicLinksConfig::tenantColumn();
        $access->forceFill([$tenantColumn => $link?->getAttribute($tenantColumn)]);
        $access->save();

        return $access;
    }
}

<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Contracts\HandlesPublicLinkActions;
use Victorio\PublicLinks\Contracts\PublicLinkHandler;
use Victorio\PublicLinks\Data\PublicLinkVisit;
use Victorio\PublicLinks\Enums\AccessOutcome;
use Victorio\PublicLinks\Events\PublicLinkActionPerformed;
use Victorio\PublicLinks\Events\PublicLinkFirstOpened;
use Victorio\PublicLinks\Events\PublicLinkOpened;
use Victorio\PublicLinks\Http\Middleware\ResolvePublicLink;
use Victorio\PublicLinks\Http\PublicLinkResponses;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\PublicLinkRegistry;
use Victorio\PublicLinks\Support\AccessRecorder;

/**
 * Hands a resolved, usable link to its registered handler. The middleware has already checked the
 * token, expiry, revocation and usage limit.
 *
 * @internal
 */
final class PublicLinkController
{
    public function __construct(
        private readonly PublicLinkRegistry $registry,
        private readonly AccessRecorder $recorder,
    ) {}

    public function show(Request $request): Response
    {
        $link = $this->link($request);
        $resolved = $this->resolve($link, $request);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        [$handler, $linkable] = $resolved;

        // The handler decides whether an open is a use; recordUse() is atomic, so of two requests
        // racing for the last use exactly one gets through.
        if ($handler->countsOpensAsUses() && ! $link->recordUse()) {
            $this->recorder->record($link, AccessOutcome::Exhausted, $request);

            return PublicLinkResponses::status(AccessOutcome::Exhausted, 410, $link);
        }

        $first = $link->markOpened();
        $access = $this->recorder->record($link, AccessOutcome::Opened, $request);

        event(new PublicLinkOpened($link, $access));

        if ($first) {
            event(new PublicLinkFirstOpened($link, $access));
        }

        return $handler->show(new PublicLinkVisit($link, $linkable, $request, access: $access));
    }

    public function action(Request $request, string $token, string $action): Response
    {
        $link = $this->link($request);
        $resolved = $this->resolve($link, $request);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        [$handler, $linkable] = $resolved;

        if (! $handler instanceof HandlesPublicLinkActions || ! in_array($action, $handler->actions(), true)) {
            $this->recorder->record($link, AccessOutcome::UnknownAction, $request, $action);

            return PublicLinkResponses::status(AccessOutcome::NotFound, 404, $link);
        }

        // Written before the handler runs, so the row exists even if the handler throws, and the
        // handler can refer to it through $visit->access.
        $access = $this->recorder->record($link, AccessOutcome::Action, $request, $action);
        $response = $handler->handleAction($action, new PublicLinkVisit($link, $linkable, $request, $action, $access));

        if ($response->getStatusCode() < 400) {
            event(new PublicLinkActionPerformed($link, $action, $access));
        }

        return $response;
    }

    private function link(Request $request): PublicLink
    {
        $link = $request->attributes->get(ResolvePublicLink::ATTRIBUTE);

        if (! $link instanceof PublicLink) {
            abort(404);
        }

        return $link;
    }

    /**
     * The handler and the model behind the link, or the 410 answer when either is gone.
     *
     * @return array{0: PublicLinkHandler, 1: Model}|Response
     */
    private function resolve(PublicLink $link, Request $request): array|Response
    {
        $handler = $this->registry->handlerFor($link->linkable_type, $link->purpose);

        if ($handler === null) {
            // A configuration problem of the host app, not of the visitor; never log the token.
            Log::warning('filament-public-links: no handler registered for an existing link.', [
                'link' => $link->getKey(),
                'linkable_type' => $link->linkable_type,
                'purpose' => $link->purpose,
            ]);
            $this->recorder->record($link, AccessOutcome::MissingHandler, $request);

            return PublicLinkResponses::status(AccessOutcome::MissingHandler, 410, $link);
        }

        $linkable = $link->linkable;

        if (! $linkable instanceof Model) {
            $this->recorder->record($link, AccessOutcome::MissingTarget, $request);

            return PublicLinkResponses::status(AccessOutcome::MissingTarget, 410, $link);
        }

        return [$handler, $linkable];
    }
}

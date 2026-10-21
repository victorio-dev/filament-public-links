<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Filament;

use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;

/**
 * A pre-configured variant shipped as its own action class, the way filament-acceptance's
 * SendForApprovalAction extends IssuePublicLinkAction.
 */
final class ShareCopyAction extends IssuePublicLinkAction
{
    public static function getDefaultName(): string
    {
        return 'shareCopy';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->modalHeading('Share with a colleague')->fields(['purpose', 'expiry']);
    }
}

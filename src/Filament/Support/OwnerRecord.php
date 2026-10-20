<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * The record whose links an action works on: the relation manager's owner record, the record of
 * a table row, or the record of a resource page.
 *
 * @internal
 */
final class OwnerRecord
{
    public static function resolve(mixed $livewire, ?Model $record = null): ?Model
    {
        if (is_object($livewire) && method_exists($livewire, 'getOwnerRecord')) {
            $owner = $livewire->getOwnerRecord();

            if ($owner instanceof Model) {
                return $owner;
            }
        }

        if ($record instanceof PublicLink) {
            $linkable = $record->linkable;

            return $linkable instanceof Model ? $linkable : null;
        }

        if ($record instanceof Model) {
            return $record;
        }

        if (is_object($livewire) && method_exists($livewire, 'getRecord')) {
            $pageRecord = $livewire->getRecord();

            return $pageRecord instanceof Model ? $pageRecord : null;
        }

        return null;
    }

    /**
     * A link of this owner by key, or null. Arguments of a mounted action can be sent by the
     * client, so a link id is only trusted when it belongs to the owner.
     */
    public static function link(Model $owner, mixed $key): ?PublicLink
    {
        if (! is_string($key) || ! method_exists($owner, 'publicLinks')) {
            return null;
        }

        $link = $owner->publicLinks()->whereKey($key)->first();

        return $link instanceof PublicLink ? $link : null;
    }
}

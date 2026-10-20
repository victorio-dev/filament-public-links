<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Concerns\HasPublicLinks;

/**
 * @property string $id
 * @property string $name
 */
final class UlidRecord extends Model
{
    use HasPublicLinks;
    use HasUlids;

    protected $table = 'fixture_ulid_records';

    protected $guarded = [];
}

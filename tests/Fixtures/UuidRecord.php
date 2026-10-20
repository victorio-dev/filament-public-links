<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Concerns\HasPublicLinks;

/**
 * @property string $id
 * @property string $name
 */
final class UuidRecord extends Model
{
    use HasPublicLinks;
    use HasUuids;

    protected $table = 'fixture_uuid_records';

    protected $guarded = [];
}

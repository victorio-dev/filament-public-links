<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Concerns\HasPublicLinks;

/**
 * @property int $id
 * @property string $name
 */
final class IntRecord extends Model
{
    use HasPublicLinks;

    protected $table = 'fixture_int_records';

    protected $guarded = [];
}

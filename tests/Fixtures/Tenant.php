<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class Tenant extends Model
{
    protected $table = 'fixture_tenants';

    protected $guarded = [];
}

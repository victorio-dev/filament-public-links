<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Victorio\PublicLinks\Tests\SharePrefixTestCase;
use Victorio\PublicLinks\Tests\TestCase;

// RefreshDatabase migrates once per run and wraps every test in a transaction, so the shared
// `testing` database on mysql and pgsql starts clean each time.
uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');

// Suites that need config before the app boots (e.g. route settings) bring their own TestCase.
uses(SharePrefixTestCase::class, RefreshDatabase::class)->in('Config');

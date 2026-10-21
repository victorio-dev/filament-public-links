<?php

declare(strict_types=1);

it('registers the package config', function (): void {
    expect(config('filament-public-links'))->toBeArray();
});

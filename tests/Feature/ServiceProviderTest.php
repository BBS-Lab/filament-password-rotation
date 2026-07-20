<?php

declare(strict_types=1);

use BBSLab\FilamentPasswordRotation\FilamentPasswordRotationServiceProvider;
use Illuminate\Support\ServiceProvider;

it('publishes the first-login backfill migration under its own tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        FilamentPasswordRotationServiceProvider::class,
        'filament-password-rotation-user-migration',
    );

    expect($paths)->not->toBeEmpty();

    $source = (string) array_key_first($paths);
    $target = (string) $paths[$source];

    expect($source)->toEndWith('database/migrations/add_password_changed_at_to_users_table.php.stub')
        ->and(is_file($source))->toBeTrue() // absolute path (built off __DIR__) to a real stub
        ->and($target)->toEndWith('_add_password_changed_at_to_users_table.php')
        ->and($target)->toContain('database'.DIRECTORY_SEPARATOR.'migrations');
});

<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation\Tests;

use BBSLab\FilamentPasswordRotation\FilamentPasswordRotationServiceProvider;
use BBSLab\LaravelPasswordRotation\LaravelPasswordRotationServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Workbench\App\Providers\AdminPanelProvider;

abstract class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            AdminPanelProvider::class,
            LaravelPasswordRotationServiceProvider::class,
            FilamentPasswordRotationServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Filament's page render path encrypts (session/CSRF); without a key it
        // throws MissingAppKeyException. Testbench does not always provide one
        // (e.g. the Filament 4 / testbench 9 matrix cell), so pin a fixed one.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Pin the package default so the suite tests 'change' mode deterministically,
        // immune to the workbench .env (whose PASSWORD_ROTATION_EXPIRY_ACTION may be
        // flipped to 'reset' for the `composer serve` demo). Reset-mode tests opt in
        // explicitly with config(['filament-password-rotation.expiry_action' => 'reset']).
        $app['config']->set('filament-password-rotation.expiry_action', 'change');
    }

    protected function defineDatabaseMigrations(): void
    {
        // The password_histories table is owned and auto-run by the base package
        // (LaravelPasswordRotationServiceProvider::runsMigrations). Only the
        // workbench user-column migration is local to this package.
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}

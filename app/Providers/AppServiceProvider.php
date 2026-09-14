<?php

namespace App\Providers;

use App\Models\AssistanceDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction() && ! app()->runningUnitTests());

        Route::model('document', AssistanceDocument::class);

        $registerSqliteSoundex = static function (Connection $connection): void {
            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }

            $connection->getPdo()->sqliteCreateFunction(
                'SOUNDEX',
                static fn (?string $value): string => soundex((string) $value),
                1,
            );
        };

        foreach (DB::getConnections() as $connection) {
            $registerSqliteSoundex($connection);
        }

        Event::listen(
            ConnectionEstablished::class,
            static fn (ConnectionEstablished $event) => $registerSqliteSoundex($event->connection),
        );

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}

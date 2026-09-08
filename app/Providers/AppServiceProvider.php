<?php

namespace App\Providers;

use App\Models\AssistanceDocument;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
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

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-intake', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->ip());
        });

        RateLimiter::for('public-intake-write', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });
    }
}

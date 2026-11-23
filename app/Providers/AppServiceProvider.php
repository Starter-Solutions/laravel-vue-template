<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Telescope\TelescopeServiceProvider as LaravelTelescopeServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(LaravelTelescopeServiceProvider::class)) {
            $this->app->register(LaravelTelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin()
            && (! Features::enabled(Features::emailVerification()) || $user->hasVerifiedEmail()));

        if ($this->app->environment('local')) {
            $this->loadMigrationsFrom(database_path('migrations/local'));
        }

        Inertia::handleExceptionsUsing(function (ExceptionResponse $exception): ?ExceptionResponse {
            if ($exception->response instanceof JsonResponse) {
                return null;
            }

            return match (true) {
                $exception->statusCode() === 404 => $exception->render('NotFound'),
                $exception->statusCode() === 500 && app()->isProduction() => $exception->render('ServerError'),
                default => null,
            };
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
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

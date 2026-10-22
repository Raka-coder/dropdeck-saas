<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * Configure rate limiters for public DropLink endpoints (PRD 6.3).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('droplink-page', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->ip()));

        RateLimiter::for('droplink-download', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->ip()));

        RateLimiter::for('droplink-password', fn (Request $request): array => [
            Limit::perMinute(5)->by($request->ip().'|'.$request->route('token')),
            Limit::perMinutes(10, 10)->by($request->ip().'|'.$request->route('token')),
        ]);

        RateLimiter::for('upload', fn (Request $request): Limit => Limit::perMinute(
            (int) config('dropdeck.limits.upload_per_minute'),
        )->by($this->userOrIpKey($request)));

        RateLimiter::for('droplink-create', fn (Request $request): Limit => Limit::perMinute(
            (int) config('dropdeck.limits.link_create_per_minute'),
        )->by($this->userOrIpKey($request)));
    }

    /**
     * Rate limit key for authenticated actions, falling back to the client IP.
     */
    private function userOrIpKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}

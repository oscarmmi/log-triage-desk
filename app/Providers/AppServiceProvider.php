<?php

namespace App\Providers;

use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\GeminiLlmClient;
use App\Services\Llm\LlmClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmClient::class, function (): LlmClient {
            $apiKey = (string) config('services.gemini.api_key', '');

            if ($apiKey === '') {
                return new FakeLlmClient;
            }

            return new GeminiLlmClient(
                $apiKey,
                (string) config('services.gemini.model', 'gemini-2.5-flash'),
            );
        });
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

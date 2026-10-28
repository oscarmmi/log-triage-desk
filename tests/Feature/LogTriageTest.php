<?php

use App\Models\LogEvent;
use App\Models\TriageRun;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Logs\LogTriage;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
});

it('binds the deterministic fake when no Gemini key is configured', function (): void {
    config()->set('services.gemini.api_key', null);

    expect(app(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class);
});

it('clusters checkout failures together and assigns error severity', function (): void {
    $run = app(LogTriage::class)->run();
    $checkoutGroup = $run->groups->first(fn ($group): bool => str_contains($group->signature, 'checkout'));

    expect($run->status)->toBe('completed')
        ->and($run->event_count)->toBe(12)
        ->and($checkoutGroup)->not->toBeNull()
        ->and($checkoutGroup->count)->toBe(4)
        ->and($checkoutGroup->severity)->toBe('error')
        ->and($checkoutGroup->owner)->toBe('Checkout')
        ->and($checkoutGroup->sample_ids)->toHaveCount(4);
});

it('keeps the one-off invoice checksum event in its own group', function (): void {
    $run = app(LogTriage::class)->run();
    $uniqueEvent = LogEvent::query()->where('source', 'report-exporter')->firstOrFail();
    $containingGroups = $run->groups->filter(fn ($group): bool => in_array($uniqueEvent->id, $group->sample_ids, true));

    expect($containingGroups)->toHaveCount(1)
        ->and($containingGroups->first()->count)->toBe(1);
});

it('returns a successful guest response with persisted groups', function (): void {
    $this->postJson(route('triage.store'))
        ->assertOk()
        ->assertJsonPath('run.status', 'completed')
        ->assertJsonPath('run.event_count', 12)
        ->assertJsonPath('run.group_count', 4);

    expect(TriageRun::query()->count())->toBe(1);
});

it('falls back to local triage when Gemini is unavailable', function (): void {
    config()->set('services.gemini.api_key', 'test-key');
    Http::fake(['*googleapis.com/*' => Http::response(['error' => 'unavailable'], 503)]);

    $this->postJson(route('triage.store'))
        ->assertOk()
        ->assertJsonPath('run.status', 'completed');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'generateContent'));
});

<?php

namespace App\Http\Controllers;

use App\Models\LogEvent;
use App\Models\TriageGroup;
use App\Models\TriageRun;
use App\Services\Logs\LogTriage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TriageController extends Controller
{
    public function index(): Response
    {
        $events = LogEvent::query()->orderByDesc('occurred_at')->orderByDesc('id')->get();
        $latestRun = TriageRun::query()->with('groups')->latest('id')->first();

        return Inertia::render('Welcome', [
            'events' => $events->map(fn (LogEvent $event): array => $this->serializeEvent($event))->values()->all(),
            'run' => $latestRun ? $this->serializeRun($latestRun) : null,
            'llmMode' => config('services.gemini.api_key') ? 'Gemini ready' : 'Local model',
        ]);
    }

    public function store(LogTriage $triage): JsonResponse
    {
        try {
            $run = $triage->run();

            return response()->json([
                'run' => $this->serializeRun($run),
                'events' => LogEvent::query()->orderByDesc('occurred_at')->orderByDesc('id')->get()
                    ->map(fn (LogEvent $event): array => $this->serializeEvent($event))->values()->all(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Log triage run failed.', ['exception' => $exception::class]);

            return response()->json([
                'message' => 'Triage could not finish. Your logs are safe; please try again.',
            ], 200);
        }
    }

    /** @return array<string, mixed> */
    private function serializeRun(TriageRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'event_count' => $run->event_count,
            'group_count' => $run->group_count,
            'completed_at' => $run->completed_at?->toIso8601String(),
            'groups' => $run->groups->map(fn (TriageGroup $group): array => $this->serializeGroup($group))->values()->all(),
        ];
    }

    /**
     * @return array{id: int, source: string, message: string, occurred_at: string}
     */
    private function serializeEvent(LogEvent $event): array
    {
        return [
            'id' => $event->id,
            'source' => $event->source,
            'message' => $event->message,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, signature: string, severity: string, owner: string, count: int, sample_ids: list<int>, reasons: list<string>}
     */
    private function serializeGroup(TriageGroup $group): array
    {
        return [
            'id' => $group->id,
            'signature' => $group->signature,
            'severity' => $group->severity,
            'owner' => $group->owner,
            'count' => $group->count,
            'sample_ids' => $group->sample_ids,
            'reasons' => $group->reasons,
        ];
    }
}

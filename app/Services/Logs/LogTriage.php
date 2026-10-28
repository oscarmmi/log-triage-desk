<?php

namespace App\Services\Logs;

use App\Models\LogEvent;
use App\Models\TriageGroup;
use App\Models\TriageRun;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogTriage
{
    public function __construct(private readonly LlmClient $llm) {}

    public function run(): TriageRun
    {
        $events = LogEvent::query()->orderBy('occurred_at')->orderBy('id')->get();
        $run = TriageRun::query()->create([
            'status' => 'processing',
            'event_count' => $events->count(),
        ]);

        try {
            $clusters = $this->cluster($events);

            foreach ($clusters as $cluster) {
                $signature = $cluster[0]['signature'];
                $samples = array_map(static fn (array $item): string => $item['event']->message, $cluster);

                try {
                    $classification = $this->validatedClassification(
                        $this->llm->classify($signature, $samples),
                        $signature,
                        $samples,
                    );
                } catch (Throwable $exception) {
                    Log::warning('Log triage classification fell back to local rules.', [
                        'exception' => $exception::class,
                    ]);
                    $classification = $this->fallbackClassification($signature, $samples);
                }

                TriageGroup::query()->create([
                    'triage_run_id' => $run->id,
                    'signature' => $signature,
                    'severity' => $classification['severity'],
                    'owner' => $classification['owner'],
                    'count' => count($cluster),
                    'sample_ids' => array_map(static fn (array $item): int => $item['event']->id, $cluster),
                    'reasons' => $classification['reasons'],
                ]);
            }

            $run->forceFill([
                'status' => 'completed',
                'group_count' => count($clusters),
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $run->forceFill(['status' => 'failed'])->save();
            throw $exception;
        }

        return $run->load('groups');
    }

    /**
     * @param  Collection<int, LogEvent>  $events
     * @return list<list<array{event: LogEvent, signature: string, vector: array<string, float>}>>
     */
    private function cluster(Collection $events): array
    {
        /** @var list<array{event: LogEvent, signature: string, tokens: list<string>}> $documents */
        $documents = [];
        foreach ($events as $event) {
            $documents[] = [
                'event' => $event,
                'signature' => $this->normalize($event->message),
                'tokens' => $this->tokens($event->message),
            ];
        }

        $documentFrequency = [];
        foreach ($documents as $document) {
            foreach (array_unique($document['tokens']) as $token) {
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }

        $documentCount = max(count($documents), 1);
        /** @var list<array{event: LogEvent, signature: string, vector: array<string, float>}> $vectorDocuments */
        $vectorDocuments = [];
        foreach ($documents as $document) {
            $termFrequency = array_count_values($document['tokens']);
            $vector = [];
            foreach ($termFrequency as $term => $frequency) {
                $inverseDocumentFrequency = log(1 + ($documentCount / (1 + $documentFrequency[$term])));
                $vector[$term] = (1 + log($frequency)) * $inverseDocumentFrequency;
            }
            $vectorDocuments[] = [
                'event' => $document['event'],
                'signature' => $document['signature'],
                'vector' => $vector,
            ];
        }

        $clusters = [];
        foreach ($vectorDocuments as $document) {
            $bestCluster = null;
            $bestSimilarity = 0.0;

            foreach ($clusters as $clusterIndex => $cluster) {
                $similarity = $this->cosineSimilarity($document['vector'], $cluster[0]['vector']);
                if ($similarity > $bestSimilarity) {
                    $bestSimilarity = $similarity;
                    $bestCluster = $clusterIndex;
                }
            }

            if ($bestCluster !== null && $bestSimilarity >= 0.16) {
                $clusters[$bestCluster][] = $document;
            } else {
                $clusters[] = [$document];
            }
        }

        return $clusters;
    }

    private function normalize(string $message): string
    {
        $normalized = strtolower($message);
        $normalized = preg_replace('/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/i', '<id>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(?:ord|usr|req|evt|txn|pay)[_-][a-z0-9_-]+\b/i', '<id>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(order|user|request|event|transaction|payment)[_-]?(?:id)?[=: ]+[a-z0-9_-]{4,}\b/i', '$1 <id>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d{4,}\b/', '<id>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? trim($normalized);

        return $normalized;
    }

    /** @return list<string> */
    private function tokens(string $message): array
    {
        preg_match_all('/[a-z][a-z0-9_-]{1,}|\b\d{3}\b/i', strtolower($message), $matches);

        return $matches[0];
    }

    /**
     * @param  array<string, float>  $left
     * @param  array<string, float>  $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $dotProduct = 0.0;
        foreach ($left as $term => $weight) {
            $dotProduct += $weight * ($right[$term] ?? 0.0);
        }

        $leftMagnitude = sqrt(array_sum(array_map(static fn (float $weight): float => $weight ** 2, $left)));
        $rightMagnitude = sqrt(array_sum(array_map(static fn (float $weight): float => $weight ** 2, $right)));

        return ($leftMagnitude * $rightMagnitude) > 0
            ? $dotProduct / ($leftMagnitude * $rightMagnitude)
            : 0.0;
    }

    /**
     * @param  array<string, mixed>  $classification
     * @param  list<string>  $samples
     * @return array{severity: string, owner: string, reasons: list<string>}
     */
    private function validatedClassification(array $classification, string $signature, array $samples): array
    {
        $severity = $classification['severity'] ?? null;
        $owner = $classification['owner'] ?? null;
        $reasons = $classification['reasons'] ?? null;

        if (! in_array($severity, ['info', 'warn', 'error'], true)
            || ! is_string($owner)
            || trim($owner) === ''
            || ! is_array($reasons)
            || $reasons === []) {
            throw new \UnexpectedValueException('Classification response did not match the expected schema.');
        }

        $cleanReasons = array_values(array_filter($reasons, static fn ($reason): bool => is_string($reason) && trim($reason) !== ''));
        if ($cleanReasons === []) {
            throw new \UnexpectedValueException('Classification response had no usable rationale.');
        }

        return [
            'severity' => $severity,
            'owner' => mb_substr(trim($owner), 0, 120),
            'reasons' => array_slice(array_map(static fn (string $reason): string => mb_substr(trim($reason), 0, 240), $cleanReasons), 0, 3),
        ];
    }

    /**
     * @param  list<string>  $samples
     * @return array{severity: string, owner: string, reasons: list<string>}
     */
    private function fallbackClassification(string $signature, array $samples): array
    {
        $text = strtolower($signature.' '.implode(' ', $samples));

        if (str_contains($text, 'checkout') || str_contains($text, 'payment') || str_contains($text, 'http 500')) {
            return ['severity' => 'error', 'owner' => 'Checkout', 'reasons' => ['A repeated checkout failure can block order completion.']];
        }
        if (str_contains($text, 'webhook') || str_contains($text, 'timeout')) {
            return ['severity' => 'warn', 'owner' => 'Integrations', 'reasons' => ['A delivery timeout may delay downstream processing.']];
        }
        if (str_contains($text, 'info') || str_contains($text, 'healthy') || str_contains($text, 'heartbeat')) {
            return ['severity' => 'info', 'owner' => 'Platform', 'reasons' => ['Routine operational message with no failure signal.']];
        }

        return ['severity' => 'warn', 'owner' => 'Platform', 'reasons' => ['Unmatched event needs a human owner to investigate its impact.']];
    }
}

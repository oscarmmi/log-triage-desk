<?php

namespace App\Services\Llm;

/** Deterministic, zero-network classification used by tests and offline demos. */
class FakeLlmClient implements LlmClient
{
    public function classify(string $signature, array $samples): array
    {
        $text = strtolower($signature.' '.implode(' ', $samples));

        if (str_contains($text, 'checkout') || str_contains($text, 'payment')) {
            return [
                'severity' => 'error',
                'owner' => 'Checkout',
                'reasons' => ['Repeated checkout failures can prevent customers from completing an order.'],
            ];
        }

        if (str_contains($text, 'webhook') || str_contains($text, 'timeout')) {
            return [
                'severity' => 'warn',
                'owner' => 'Integrations',
                'reasons' => ['A delivery timeout may delay downstream event processing.'],
            ];
        }

        if (str_contains($text, 'info') || str_contains($text, 'healthy') || str_contains($text, 'heartbeat')) {
            return [
                'severity' => 'info',
                'owner' => 'Platform',
                'reasons' => ['Routine operational message with no failure signal.'],
            ];
        }

        return [
            'severity' => 'warn',
            'owner' => 'Platform',
            'reasons' => ['Unmatched event needs a human owner to investigate its impact.'],
        ];
    }
}

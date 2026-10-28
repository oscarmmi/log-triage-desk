<?php

namespace App\Services\Llm;

interface LlmClient
{
    /**
     * @param  list<string>  $samples
     * @return array{severity: string, owner: string, reasons: list<string>}
     */
    public function classify(string $signature, array $samples): array;
}

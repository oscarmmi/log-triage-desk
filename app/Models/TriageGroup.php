<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $triage_run_id
 * @property string $signature
 * @property string $severity
 * @property string $owner
 * @property int $count
 * @property list<int> $sample_ids
 * @property list<string> $reasons
 */
class TriageGroup extends Model
{
    protected $fillable = [
        'triage_run_id', 'signature', 'severity', 'owner', 'count', 'sample_ids', 'reasons',
    ];

    protected function casts(): array
    {
        return ['sample_ids' => 'array', 'reasons' => 'array'];
    }

    /** @return BelongsTo<TriageRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(TriageRun::class, 'triage_run_id');
    }
}

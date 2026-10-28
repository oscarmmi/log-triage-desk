<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $status
 * @property int $event_count
 * @property int $group_count
 * @property CarbonImmutable|null $completed_at
 */
class TriageRun extends Model
{
    protected $fillable = ['status', 'event_count', 'group_count', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }

    /** @return HasMany<TriageGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TriageGroup::class);
    }
}

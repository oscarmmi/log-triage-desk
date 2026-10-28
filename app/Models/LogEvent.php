<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source
 * @property string $message
 * @property CarbonImmutable $occurred_at
 */
class LogEvent extends Model
{
    protected $fillable = ['source', 'message', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}

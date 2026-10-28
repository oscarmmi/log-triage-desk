<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 24)->default('processing')->index();
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedInteger('group_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_runs');
    }
};

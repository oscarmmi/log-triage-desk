<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('triage_run_id')->constrained()->cascadeOnDelete();
            $table->string('signature', 500);
            $table->string('severity', 16)->default('info');
            $table->string('owner', 120);
            $table->unsignedInteger('count')->default(0);
            $table->json('sample_ids');
            $table->json('reasons');
            $table->timestamps();
            $table->index(['triage_run_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_groups');
    }
};

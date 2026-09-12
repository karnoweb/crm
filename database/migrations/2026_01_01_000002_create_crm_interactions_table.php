<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('type');
            $table->string('subject_group');
            $table->string('subject_key');
            $table->string('subject_label')->nullable();
            $table->float('weight')->default(1);
            $table->string('source');
            $table->string('source_id')->nullable();
            $table->string('idempotency_key');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->unique(['lead_id', 'idempotency_key']);
            $table->index(['lead_id', 'subject_group', 'subject_key']);
            $table->index('occurred_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_interactions');
    }
};

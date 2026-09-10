<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Support\CrmSchema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->decimal('value', 15, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->string('status')->default('open');
            CrmSchema::branchKey($table);
            CrmSchema::assignedTo($table);
            $table->timestamp('expected_close_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lead_id', 'status']);
            $table->index('pipeline_stage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};

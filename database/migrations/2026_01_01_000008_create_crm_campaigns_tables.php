<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Support\CrmSchema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('channel');
            $table->foreignId('segment_id')->nullable()->constrained('crm_segments')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            CrmSchema::branchKey($table);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['campaign_id', 'lead_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_recipients');
        Schema::dropIfExists('crm_campaigns');
    }
};

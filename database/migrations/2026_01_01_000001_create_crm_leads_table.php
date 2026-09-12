<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Support\CrmSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            CrmSchema::userKey($table)->unique();
            CrmSchema::branchKey($table);
            CrmSchema::assignedTo($table);
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('last_status_change_at')->nullable();
            $table->integer('score')->default(0);
            $table->integer('rfm_recency_score')->nullable();
            $table->integer('rfm_frequency_score')->nullable();
            $table->integer('rfm_monetary_score')->nullable();
            $table->string('rfm_monetary_currency')->nullable();
            $table->timestamp('rfm_monetary_at')->nullable();
            $table->string('rfm_segment')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->unsignedInteger('total_interactions')->default(0);
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('branch_id');
            $table->index('assigned_to');
            $table->index('captured_at');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};

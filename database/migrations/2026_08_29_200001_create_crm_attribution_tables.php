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
        Schema::create('crm_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('deal_id')->nullable()->index();
            CrmSchema::assignedTo($table, 'credited_to');
            $table->string('source')->nullable();
            $table->string('policy');
            $table->string('status');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->timestamp('attributed_at')->nullable();
            $table->timestamp('window_starts_at')->nullable();
            $table->timestamp('window_ends_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
            $table->timestamp('reversed_at')->nullable();
            CrmSchema::branchKey($table);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'attributed_at']);
            $table->index(['lead_id', 'status']);
        });

        Schema::create('crm_attribution_windows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('deal_id')->nullable()->index();
            CrmSchema::assignedTo($table, 'credited_to');
            $table->string('source');
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->boolean('is_active')->default(true);
            CrmSchema::branchKey($table);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'is_active', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_attribution_windows');
        Schema::dropIfExists('crm_attributions');
    }
};

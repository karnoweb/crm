<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_dynamic')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->json('last_match_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_segment_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')->constrained('crm_segments')->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->json('value');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_segment_rules');
        Schema::dropIfExists('crm_segments');
    }
};

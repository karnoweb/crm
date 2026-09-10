<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('subject_group');
            $table->string('subject_key');
            $table->string('subject_label')->nullable();
            $table->float('score')->default(0);
            $table->unsignedInteger('interactions_count')->default(0);
            $table->timestamp('first_interacted_at')->nullable();
            $table->timestamp('last_interacted_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'subject_group', 'subject_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_interests');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Support\CrmSchema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('activityable_type');
            $table->unsignedBigInteger('activityable_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['activityable_type', 'activityable_id']);
        });

        Schema::create('crm_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('notable_type');
            $table->unsignedBigInteger('notable_id');
            $table->string('title')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['notable_type', 'notable_id']);
        });

        Schema::create('crm_followups', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('due_at');
            CrmSchema::assignedTo($table);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['due_at', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_followups');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_activities');
    }
};

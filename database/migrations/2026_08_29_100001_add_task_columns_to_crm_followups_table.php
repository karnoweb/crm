<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_followups', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('subject_id');
            $table->string('type')->nullable()->after('title');
            $table->string('status')->default('open')->after('type');
            $table->string('outcome')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('notified_at');

            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_followups', function (Blueprint $table): void {
            $table->dropIndex(['status', 'due_at']);
            $table->dropColumn(['title', 'type', 'status', 'outcome', 'completed_at']);
        });
    }
};

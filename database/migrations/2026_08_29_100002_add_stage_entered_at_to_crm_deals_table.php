<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->timestamp('stage_entered_at')->nullable()->after('pipeline_stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->dropColumn('stage_entered_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Globalize provider configurations: a single set shared by all tenants,
     * managed only by the super admin. We keep workspace_id nullable for
     * backward compatibility but enforce uniqueness per `type` globally.
     */
    public function up(): void
    {
        // Collapse any per-workspace rows to one row per type (keep newest),
        // so the new global unique(type) index can be created safely.
        $keepIds = DB::table('provider_configurations')
            ->selectRaw('MAX(id) as id')
            ->groupBy('type')
            ->pluck('id')
            ->all();
        if (! empty($keepIds)) {
            DB::table('provider_configurations')->whereNotIn('id', $keepIds)->delete();
        }

        Schema::table('provider_configurations', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'type']);
        });
        Schema::table('provider_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->change();
            $table->unique('type');
        });
        DB::table('provider_configurations')->update(['workspace_id' => null]);
    }

    public function down(): void
    {
        Schema::table('provider_configurations', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->unique(['workspace_id', 'type']);
        });
    }
};

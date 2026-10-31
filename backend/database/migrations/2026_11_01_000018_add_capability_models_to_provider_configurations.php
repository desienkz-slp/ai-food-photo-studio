<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_configurations', function (Blueprint $table) {
            // Separate models per capability. Nullable so existing rows (which
            // only set `model` for editing) stay valid.
            $table->string('model_create')->nullable()->after('model');
            $table->string('model_vision')->nullable()->after('model_create');
        });
    }

    public function down(): void
    {
        Schema::table('provider_configurations', function (Blueprint $table) {
            $table->dropColumn(['model_create', 'model_vision']);
        });
    }
};

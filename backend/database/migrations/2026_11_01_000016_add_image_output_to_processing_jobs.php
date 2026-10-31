<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            // Nullable so existing identity_rgb_v1 rows remain valid.
            $table->string('output_mime')->nullable()->after('output_key');
            $table->string('output_ext', 8)->nullable()->after('output_mime');
            $table->text('prompt')->nullable()->after('operation');
        });
    }

    public function down(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->dropColumn(['output_mime', 'output_ext', 'prompt']);
        });
    }
};

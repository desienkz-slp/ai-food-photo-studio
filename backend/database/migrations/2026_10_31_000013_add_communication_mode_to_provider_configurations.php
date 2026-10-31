<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_configurations', function (Blueprint $table) {
            // chat = chat completions, message = single message/completion,
            // response = responses API. Default keeps existing rows valid.
            $table->string('communication_mode', 20)->default('chat')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('provider_configurations', function (Blueprint $table) {
            $table->dropColumn('communication_mode');
        });
    }
};

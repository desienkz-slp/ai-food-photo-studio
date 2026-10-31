<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->string('size', 16)->nullable()->after('prompt');
        });
    }

    public function down(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};

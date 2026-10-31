<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_version_id')->constrained()->cascadeOnDelete();
            $table->string('artifact_key')->unique();
            $table->string('sha256', 64);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique('asset_version_id');
        });
    }

    public function down(): void { Schema::dropIfExists('asset_exports'); }
};

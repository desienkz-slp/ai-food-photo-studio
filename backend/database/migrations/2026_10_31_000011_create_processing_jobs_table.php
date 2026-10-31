<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('asset_version_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status');
            $table->string('original_sha256', 64);
            $table->string('output_sha256', 64)->nullable();
            $table->string('output_key')->nullable();
            $table->string('operation')->default('identity_rgb_v1');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('processing_jobs'); }
};

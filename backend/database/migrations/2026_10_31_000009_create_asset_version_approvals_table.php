<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_version_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_sha256', 64);
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
        });
    }
    public function down(): void { Schema::dropIfExists('asset_version_approvals'); }
};

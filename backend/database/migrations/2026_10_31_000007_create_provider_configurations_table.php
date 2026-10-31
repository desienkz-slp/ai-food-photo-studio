<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('endpoint', 2048)->nullable();
            $table->string('model');
            $table->text('api_key');
            $table->timestamps();
            $table->unique(['workspace_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_configurations');
    }
};

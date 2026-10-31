<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('instruction', 2000);
            $table->string('path')->unique();
            $table->string('mime', 32);
            $table->string('ext', 8);
            $table->string('sha256', 64);
            $table->unsignedInteger('bytes');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('edit_histories'); }
};

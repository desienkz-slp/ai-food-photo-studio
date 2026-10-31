<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('asset_versions', function(Blueprint $t){$t->id();$t->foreignId('asset_id')->constrained()->cascadeOnDelete();$t->string('original_sha256',64);$t->string('status')->default('draft');$t->string('label')->nullable();$t->timestamps();$t->unique(['asset_id','original_sha256']);}); } public function down(): void {Schema::dropIfExists('asset_versions');} };

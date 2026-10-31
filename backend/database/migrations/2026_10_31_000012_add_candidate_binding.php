<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('asset_version_approvals', function(Blueprint $t){$t->foreignId('candidate_job_id')->nullable()->constrained('processing_jobs');$t->string('candidate_sha256',64)->nullable();}); } public function down(): void { Schema::table('asset_version_approvals',fn(Blueprint $t)=>$t->dropConstrainedForeignId('candidate_job_id')->dropColumn('candidate_sha256')); } };

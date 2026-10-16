<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_mulai')->nullable();
            $table->char('context_hash', 64)->nullable();
            $table->index(['mahasiswa_id', 'semester_akademik_id', 'status'], 'roadmap_student_term_status');
        });
        Schema::table('krs', function (Blueprint $table) {
            $table->foreignId('roadmap_id')->nullable()->constrained('roadmap')->restrictOnDelete();
            $table->index(['semester_akademik_id', 'status']);
        });
        Schema::table('roadmap_detail', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
        });
        Schema::table('tagihan', function (Blueprint $table) {
            $table->uuid('request_key')->nullable()->unique();
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['created_at']);
        });
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropColumn('request_key');
        });
        Schema::table('roadmap_detail', fn (Blueprint $table) => $table->dropConstrainedForeignId('kelas_id'));
        Schema::table('krs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roadmap_id');
            $table->dropIndex(['semester_akademik_id', 'status']);
        });
        Schema::table('roadmap', function (Blueprint $table) {
            $table->dropIndex('roadmap_student_term_status');
            $table->dropColumn(['semester_mulai', 'context_hash']);
        });
    }
};

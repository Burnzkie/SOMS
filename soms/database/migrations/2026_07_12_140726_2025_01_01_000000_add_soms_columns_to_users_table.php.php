<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id')->unique()->after('id');
            $table->enum('role', ['admin', 'officer', 'student'])->default('student')->after('email');
            $table->string('department')->nullable()->after('role');
            $table->string('program')->nullable()->after('department');
            $table->string('level')->nullable()->after('program');
            $table->boolean('is_approved')->default(false)->after('level');
            $table->foreignId('approved_by')->nullable()->after('is_approved')->constrained('users')->nullOnDelete();
            $table->boolean('must_change_password')->default(true)->after('approved_by');
            $table->string('qr_token')->unique()->nullable()->after('must_change_password');
            $table->unsignedInteger('qr_version')->default(1)->after('qr_token');
            $table->boolean('qr_revoked')->default(false)->after('qr_version');
            $table->timestamp('qr_generated_at')->nullable()->after('qr_revoked');
            $table->string('fcm_token')->nullable()->after('qr_generated_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'student_id',
                'role',
                'department',
                'program',
                'level',
                'is_approved',
                'must_change_password',
                'qr_token',
                'qr_version',
                'qr_revoked',
                'qr_generated_at',
                'fcm_token',
                'deleted_at',
            ]);
        });
    }
};
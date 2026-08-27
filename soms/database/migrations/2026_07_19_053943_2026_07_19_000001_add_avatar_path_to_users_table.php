<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable — most accounts have no custom avatar.
            // NOT added to $fillable on the User model; only ever set via
            // AvatarController::update() using forceFill(), same pattern
            // as role/qr_token/etc. — never mass-assignable.
            $table->string('avatar_path')->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
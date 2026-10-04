<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // two_factor_secret already exists. A secret only counts once the user
    // has proved their app produces the right codes; recovery codes are the
    // way back in when the phone is lost.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted, a 32-character secret is 256 characters: one more
            // than the column held, which MySQL would refuse.
            $table->text('two_factor_secret')->nullable()->change();
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_confirmed_at', 'two_factor_recovery_codes']);
            $table->string('two_factor_secret')->nullable()->change();
        });
    }
};

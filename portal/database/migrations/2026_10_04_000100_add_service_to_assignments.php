<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // What the assignment delivers decides how it is billed: by the hour,
    // the visit, the day or the session. A massage booking and a morning
    // care placement are both assignments; this is what tells them apart.
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('care_plan_id')->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('notes');
        });
    }
};

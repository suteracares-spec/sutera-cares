<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The plan describes what the person needs; the assignment describes
        // who delivers it. Caregivers change; the plan should not have to be
        // rewritten when they do.
        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('agreed_by', 150)->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'active', 'superseded'])->default('draft')->index();
            $table->timestamps();

            $table->unique(['patient_id', 'version']);
        });

        Schema::create('care_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['personal_care', 'mobility', 'household', 'companionship', 'appointment', 'wellness']);
            $table->string('description', 400);
            $table->enum('frequency', ['every_visit', 'daily', 'weekly', 'as_needed'])->default('every_visit');
            $table->enum('time_of_day', ['morning', 'midday', 'evening', 'night', 'any'])->default('any');
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('role', ['primary', 'relief'])->default('primary');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('charge_rate', 10, 2)->nullable();   // what the client pays
            $table->enum('status', ['proposed', 'active', 'ended'])->default('proposed');
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['caregiver_id', 'status']);
        });

        // Dated rows, not a recurrence rule. Real life is full of exceptions —
        // a public holiday, a hospital admission, a caregiver off sick — and
        // you cannot cancel one day of a rule.
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'missed', 'cancelled'])->default('scheduled');
            $table->string('cancel_reason', 300)->nullable();
            $table->foreignId('covered_by_id')->nullable()->constrained('caregivers')->nullOnDelete();
            $table->timestamps();

            $table->index(['shift_date', 'status']);
        });

        // The evidence table. "Was anyone actually there on Tuesday" is the
        // question you get asked in a dispute, and memory is not evidence.
        Schema::create('visit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained()->restrictOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->json('tasks_completed')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('concern_flagged')->default(false)->index();
            $table->string('concern_detail', 600)->nullable();
            $table->unsignedSmallInteger('minutes_worked')->nullable();
            $table->timestamps();
        });

        Schema::create('concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raised_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('category', ['care_quality', 'attendance', 'safety', 'billing', 'staff_conduct', 'other']);
            $table->text('detail');
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed'])->default('open')->index();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concerns');
        Schema::dropIfExists('visit_logs');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('care_plan_tasks');
        Schema::dropIfExists('care_plans');
    }
};

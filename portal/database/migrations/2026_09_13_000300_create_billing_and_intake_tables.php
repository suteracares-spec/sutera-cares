<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->enum('category', ['personal_care', 'daily_care', 'live_in', 'appointment', 'wellness']);
            $table->enum('unit', ['hour', 'visit', 'day', 'month', 'session']);
            $table->decimal('base_rate', 10, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();          // INV-2026-0118
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('surcharges', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->char('currency', 3)->default('MYR');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'part_paid', 'paid', 'overdue', 'void'])->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });

        // Lines point at shifts, so every invoice can be explained line by line.
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 300);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 10, 2);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['bank_transfer', 'duitnow', 'cash', 'cheque', 'card']);
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The two forms on provider.suteracares.org land here.
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 150);
            $table->string('client_phone', 30);
            $table->string('client_email', 190)->nullable();
            $table->string('client_relationship', 80)->nullable();
            $table->string('patient_name', 150)->nullable();
            $table->smallInteger('patient_age')->nullable();
            $table->string('patient_area', 120)->nullable();
            $table->string('patient_mobility', 60)->nullable();
            $table->text('needs')->nullable();
            $table->string('schedule_wanted', 200)->nullable();
            $table->string('source', 60)->default('website');
            $table->enum('status', ['new', 'contacted', 'assessment_booked', 'converted', 'declined', 'lost'])->default('new');
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('role_applied', 120)->nullable();
            $table->smallInteger('years_experience')->nullable();
            $table->string('availability', 60)->nullable();
            $table->string('languages', 200)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['new', 'screening', 'interview', 'offered', 'hired', 'rejected'])->default('new');
            $table->foreignId('caregiver_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // PDPA requires you to know who looked at what.
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);                   // viewed, created, updated, exported, login_failed
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('detail', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('services');
    }
};

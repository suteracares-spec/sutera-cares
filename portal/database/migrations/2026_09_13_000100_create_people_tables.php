<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();            // SCP-0042
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->text('ic_number')->nullable();           // encrypted cast
            $table->date('dob')->nullable();
            $table->enum('gender', ['female', 'male', 'other'])->nullable();
            $table->string('address', 500)->nullable();
            $table->string('area', 120)->nullable()->index();
            $table->string('postcode', 10)->nullable();
            $table->enum('mobility_level', ['independent', 'walks_with_aid', 'wheelchair', 'bed_bound'])->nullable();
            $table->string('languages', 200)->nullable();
            $table->text('allergies')->nullable();
            $table->text('notes')->nullable();               // encrypted cast

            // PDPA: sensitive personal data needs explicit, recorded consent.
            $table->timestamp('consent_given_at')->nullable();
            $table->string('consent_by', 150)->nullable();

            $table->enum('status', ['enquiry', 'assessment', 'active', 'paused', 'closed'])->default('enquiry')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // A link table, not a column on patients: several family members are
        // usually involved, with different levels of access. One guardian_id
        // would force you to pick a favourite child.
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 60)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_bill_payer')->default(false);
            $table->boolean('can_view_notes')->default(true);
            $table->boolean('can_view_invoices')->default(false);
            $table->boolean('can_request_changes')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'patient_id']);
        });

        Schema::create('caregivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();            // CG-014
            $table->text('ic_number')->nullable();           // encrypted cast
            $table->enum('gender', ['female', 'male', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('languages', 200)->nullable();
            $table->string('skills', 500)->nullable();
            $table->boolean('has_own_transport')->default(false);
            $table->smallInteger('max_travel_km')->nullable();
            $table->string('base_area', 120)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();   // what we pay, not what we charge
            $table->date('police_check_expires_at')->nullable();
            $table->boolean('right_to_work_verified')->default(false);
            $table->enum('status', ['applicant', 'vetting', 'active', 'inactive', 'left'])->default('applicant')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregivers');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('patients');
    }
};

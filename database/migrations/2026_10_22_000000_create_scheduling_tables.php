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
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('phone')->nullable()->index(); // E.164
            $table->boolean('sms_opt_in')->default(false);
            $table->timestamps();

            $table->index(['last_name', 'date_of_birth']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('department');
            $table->string('appointment_type');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('scheduled'); // scheduled, cancelled, completed, no_show
            $table->string('source'); // staff, voice, sms
            $table->string('conversation_id')->nullable(); // Retell call_id / chat_id that booked it
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['department', 'starts_at']);
        });

        // One row per Retell call or SMS chat, upserted as webhook events arrive.
        // Deliberately no transcript or raw payload: keep PHI out of this table.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->unique();
            $table->string('channel'); // voice, sms
            $table->string('direction')->nullable(); // inbound, outbound
            $table->string('last_event');
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disconnection_reason')->nullable();
            $table->text('summary')->nullable();
            $table->string('user_sentiment')->nullable();
            $table->boolean('successful')->nullable();
            $table->boolean('transferred')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        // Who did what to which record. IDs only, never names or birth dates.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('actor_type'); // ai_voice, ai_sms, staff, system
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversation_id')->nullable()->index();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome'); // ok, denied, error
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
    }
};

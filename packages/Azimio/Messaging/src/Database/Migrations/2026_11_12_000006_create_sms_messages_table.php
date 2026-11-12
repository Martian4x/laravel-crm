<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every SMS, single or part of a batch, outbound or inbound — one log.
 *
 * Key columns and why they exist:
 *
 *  - segments / credits_charged: one credit is one segment (decision 8.0.1),
 *    so the cost of a row is stored, not recomputed from body length later
 *    when the segment rules or the body may have changed.
 *  - cost_source: 'platform' debited the tenant's platform credits,
 *    'own' went through their own provider and cost the platform nothing.
 *    Without this the ledger and the log cannot be reconciled.
 *  - credit_shortfall: set when status is 'failed_own_provider' and the
 *    tenant lacks the platform credits to retry (decision 8.0.6), so the
 *    failure UI can choose between offering the AZIMIOCRM retry and showing
 *    the no-platform-credits alert without recomputing anything.
 *  - dest_addr is normalised E.164; raw_number keeps what the user actually
 *    typed, so a normalisation bug stays diagnosable.
 *  - provider_message_id is how delivery-report callbacks find this row.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedInteger('batch_id')->nullable();
            $table->foreign('batch_id')->references('id')->on('sms_batches')->cascadeOnDelete();

            $table->unsignedInteger('sender_profile_id')->nullable();
            $table->foreign('sender_profile_id')->references('id')->on('sms_sender_profiles')->nullOnDelete();

            // Person / Lead / User the message is addressed to, when known.
            $table->string('recipient_type', 64)->nullable();
            $table->unsignedInteger('recipient_id')->nullable();

            $table->string('dest_addr', 20);
            $table->string('raw_number', 32)->nullable();

            $table->text('body');

            // gsm7 | ucs2
            $table->string('encoding', 8)->default('gsm7');
            $table->unsignedSmallInteger('segments')->default(1);

            $table->unsignedInteger('credits_charged')->default(0);

            // platform | own
            $table->string('cost_source', 10)->default('platform');

            // outbound | inbound
            $table->string('direction', 10)->default('outbound');

            // queued | sending | submitted | delivered | failed |
            // failed_own_provider | rejected | undeliverable
            $table->string('status', 24)->default('queued');

            $table->string('provider_message_id')->nullable();
            $table->string('provider_request_id')->nullable();

            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedInteger('credit_shortfall')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('batch_id');
            $table->index('dest_addr');
            $table->index('direction');
            $table->index('provider_message_id');
            $table->index('provider_request_id');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_messages');
    }
};

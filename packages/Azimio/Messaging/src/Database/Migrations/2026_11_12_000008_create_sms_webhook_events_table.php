<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw provider callbacks, stored before they are trusted (8.19).
 *
 * Three jobs: replay a callback after a bug, prove to an operator what they
 * actually sent us during a billing dispute, and keep a record of rejected
 * payloads — signature_valid = 0 rows are kept, not discarded, because a run
 * of them is how a forged-callback attempt looks.
 *
 * NOT tenant-scoped: a callback arrives before tenant context exists, and is
 * attributed to a tenant only once message_id resolves. Platform admins read
 * this table; tenants never do.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_webhook_events', function (Blueprint $table) {
            $table->increments('id');

            $table->string('provider', 32);

            // delivery_report | inbound
            $table->string('event_type', 32)->nullable();

            $table->json('payload')->nullable();

            $table->boolean('signature_valid')->default(0);

            $table->unsignedInteger('message_id')->nullable();
            $table->foreign('message_id')->references('id')->on('sms_messages')->nullOnDelete();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index('provider');
            $table->index('event_type');
            $table->index('processed_at');
            $table->index('signature_valid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_webhook_events');
    }
};

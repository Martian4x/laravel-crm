<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only credit ledger — the source of truth for platform credits.
 *
 * Never updated or deleted: a correction is a new 'adjustment' row. The
 * tenant's balance is the SUM of credits for that tenant; tenants.sms_credits
 * only caches it for cheap reads and is recomputable from here (the reconcile
 * action in 8.15).
 *
 * 'credits' is SIGNED: allocations and refunds are positive, debits negative.
 * balance_after is the running balance at the time the row was written, so a
 * dispute can be reconstructed without replaying arithmetic.
 *
 * Own-provider (BYO) sends never appear here — they cost the tenant nothing
 * against the platform (decision in the Phase 8 mode table).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_credit_ledger', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // allocation | purchase | debit | refund | adjustment | expiry
            $table->string('type', 20);

            $table->integer('credits');
            $table->integer('balance_after');

            // Polymorphic back-reference: the batch, message or credit request
            // that caused this movement.
            $table->string('reference_type', 64)->nullable();
            $table->unsignedInteger('reference_id')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('type');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_credit_ledger');
    }
};

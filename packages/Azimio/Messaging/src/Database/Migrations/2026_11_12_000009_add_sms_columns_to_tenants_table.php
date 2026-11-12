<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant SMS state on the tenants row.
 *
 * sms_credits is a CACHE of the sms_credit_ledger sum, not the truth. It
 * exists so a balance read (shown on nearly every SMS screen) does not
 * aggregate the whole ledger, and the reconcile action in 8.15 recomputes
 * it. Any write to it must happen in the same transaction as the ledger row
 * that justifies it.
 *
 * sms_mode records which of the two modes the tenant is on (decision 8.0.4,
 * one active mode at a time). It is denormalised from the active sender
 * profile so middleware and UI can branch without a join.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('sms_credits')->default(0)->after('max_users');

            // platform | own
            $table->string('sms_mode', 10)->default('platform')->after('sms_credits');

            $table->unsignedInteger('sms_low_credit_threshold')->default(50)->after('sms_mode');
        });
    }

    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['sms_credits', 'sms_mode', 'sms_low_credit_threshold']);
        });
    }
};

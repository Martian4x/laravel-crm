<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a tenant is suspended (7.9).
 *
 * Without this, "suspended" is a bare boolean and nobody — not the next
 * admin, not the tenant on the phone asking why — can tell whether it was
 * non-payment, abuse, a trial that lapsed, or someone clicking the wrong
 * row. `suspended_at` makes "when" answerable too.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('suspension_reason', 255)->nullable()->after('trial_ends_at');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
        });
    }

    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['suspension_reason', 'suspended_at']);
        });
    }
};

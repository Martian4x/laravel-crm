<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant compliance settings (8.12).
 *
 * All nullable on purpose: NULL means "use the platform default" from
 * config('messaging.compliance'), so an existing tenant keeps the current
 * behaviour and only an explicit override changes anything. A zero daily
 * cap would otherwise be indistinguishable from "not set", which is the
 * difference between no limit and no sending at all.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('sms_quiet_hours_enabled')->nullable()->after('sms_low_credit_threshold');
            $table->string('sms_quiet_start', 5)->nullable()->after('sms_quiet_hours_enabled');
            $table->string('sms_quiet_end', 5)->nullable()->after('sms_quiet_start');
            $table->unsignedInteger('sms_daily_cap')->nullable()->after('sms_quiet_end');
        });
    }

    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'sms_quiet_hours_enabled',
                'sms_quiet_start',
                'sms_quiet_end',
                'sms_daily_cap',
            ]);
        });
    }
};

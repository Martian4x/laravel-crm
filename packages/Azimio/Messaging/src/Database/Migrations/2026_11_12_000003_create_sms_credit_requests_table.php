<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant asking the platform for SMS credits (8.13), and the platform
 * admin's decision on it (8.16).
 *
 * Payment happens outside the CRM (decision 8.0.3), so this records the
 * request and the resulting allocation, not a transaction. Approving a
 * request writes the sms_credit_ledger allocation row in the same database
 * transaction, so a granted request can never exist without its credits.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_credit_requests', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedInteger('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedInteger('credits_requested');
            $table->text('reason')->nullable();

            // pending | approved | partially_approved | rejected
            $table->string('status', 24)->default('pending');

            $table->unsignedInteger('credits_granted')->nullable();

            $table->unsignedInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_credit_requests');
    }
};

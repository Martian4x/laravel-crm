<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One bulk send (8.8). Individual recipients live in sms_messages.
 *
 * credits_estimated is what the pre-send summary quoted the user;
 * credits_charged is what actually settled. They differ whenever messages
 * were rejected before submission (released, never debited — decision
 * 8.0.2), which makes the gap auditable rather than mysterious.
 *
 * The per-status counters are denormalised on purpose: a batch detail page
 * must not COUNT over tens of thousands of message rows on every load. They
 * are derived values, so the reconcile path recomputes them from
 * sms_messages rather than trusting them.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_batches', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedInteger('sender_profile_id')->nullable();
            $table->foreign('sender_profile_id')->references('id')->on('sms_sender_profiles')->nullOnDelete();

            $table->unsignedInteger('template_id')->nullable();
            $table->foreign('template_id')->references('id')->on('sms_templates')->nullOnDelete();

            $table->string('name');
            $table->text('body');

            // manual | datagrid_selection | segment
            $table->string('audience_type', 32)->default('manual');

            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('submitted_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->unsignedInteger('credits_estimated')->default(0);
            $table->unsignedInteger('credits_charged')->default(0);

            // draft | scheduled | sending | completed | partially_failed | cancelled
            $table->string('status', 24)->default('draft');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_batches');
    }
};

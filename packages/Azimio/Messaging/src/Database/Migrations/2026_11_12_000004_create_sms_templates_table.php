<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable message bodies with merge fields (8.9).
 *
 * SHARED tenancy, like email_templates: a NULL tenant_id row is a platform
 * template visible to every tenant and read-only for them (the Phase 6.1
 * guard in BelongsToTenant enforces that).
 *
 * 'category' matters beyond labelling: marketing messages are subject to
 * quiet hours and get the opt-out notice appended, transactional ones are
 * exempt (8.12).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->text('body');

            // transactional | marketing
            $table->string('category', 20)->default('transactional');

            $table->string('locale', 5)->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_templates');
    }
};

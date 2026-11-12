<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numbers that must not be messaged (8.11).
 *
 * Scoped per tenant, deliberately: opting out of Tenant A's messages must
 * not silence Tenant B, who may have an entirely legitimate relationship
 * with the same person. The unique key is (tenant_id, dest_addr) for that
 * reason, not dest_addr alone.
 *
 * dest_addr is the normalised E.164 form, so a suppression cannot be
 * defeated by writing the same number in a different local format.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_opt_outs', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('dest_addr', 20);

            // stop_reply | manual | import
            $table->string('source', 20)->default('manual');

            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();

            $table->string('reason')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'dest_addr']);
            $table->index('dest_addr');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_opt_outs');
    }
};

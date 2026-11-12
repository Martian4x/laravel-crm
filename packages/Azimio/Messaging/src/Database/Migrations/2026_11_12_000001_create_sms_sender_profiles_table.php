<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a tenant sends as, and through which provider.
 *
 * tenant_id NULL is the platform's own AZIMIOCRM profile, shared by every
 * tenant in platform mode, but reachable only via withoutTenancy() in the
 * sending service — see the note below. A tenant row is
 * their own provider (BYO), whose credentials never touch platform credits.
 *
 * Credentials are encrypted at rest (decision 8.0.5) — the model casts this
 * column as 'encrypted:json', so APP_KEY is backup-critical.
 *
 * IMPORTANT — registered as STRICT in TenantTables, not SHARED, which is a
 * correction to the Phase 8.2 spec. SHARED would make every NULL tenant_id
 * row visible to every tenant, and this table's NULL row holds the
 * platform's provider credentials: a tenant reading it through the model
 * would get them decrypted by the cast. Tenants therefore never read the
 * platform profile. The sending service loads it explicitly with
 * withoutTenancy(), and tenant-facing UI shows the sender name from
 * config('messaging.platform.sender_name') instead of from this row.
 *
 * Decision 8.0.4 allows exactly one ACTIVE profile per tenant. That is an
 * application rule, not a DB constraint: MySQL has no portable partial
 * unique index, so it is enforced in the service layer and tested in 8.25.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_sender_profiles', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('label');
            $table->string('sender_name', 32);
            $table->string('driver', 32)->default('beem');

            // Encrypted JSON blob: api keys, secrets, per-driver options.
            $table->text('credentials')->nullable();

            $table->boolean('is_default')->default(0);

            // pending | approved | rejected | disabled
            $table->string('status', 20)->default('pending');

            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->unique(['tenant_id', 'sender_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_sender_profiles');
    }
};

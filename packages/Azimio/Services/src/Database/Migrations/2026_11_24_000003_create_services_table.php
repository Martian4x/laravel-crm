<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services (9.4) — what a firm sells that is not stock.
     *
     * A separate table rather than a flag on `products` on purpose. A
     * product has a quantity on hand; a service has a RATE and a UNIT of
     * time, and the two do not share a meaning of "quantity: 5". Overloading
     * the products table would have left every consultancy in the tenant
     * base entering stock levels for advice.
     *
     * STRICT tenancy: a service is a tenant's own price list and must never
     * be visible to another tenant.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('tenant_id')->unsigned()->nullable();

            $table->string('name');

            // The service equivalent of a SKU. Optional, because plenty of
            // firms never code their services at all.
            $table->string('code')->nullable();

            $table->text('description')->nullable();

            // 12,2 matches Krayin's own money columns, so a service total
            // and a product total add up the same way.
            $table->decimal('price', 12, 2)->default(0);

            // hour / day / month / fixed — see ServiceUnit. Stored as a
            // string so adding a unit later is a code change, not a
            // migration against live data.
            $table->string('unit', 16)->default('fixed');

            // VAT in Tanzania is 18% but not everything is standard-rated,
            // so taxability is per service rather than assumed.
            $table->boolean('is_taxable')->default(true);

            // Retired rather than deleted: a service already quoted on a
            // past deal must stay resolvable.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Scoped by tenant: two tenants may each have a service coded
            // "CONSULT-01" without colliding.
            $table->unique(['tenant_id', 'code'], 'services_tenant_code_unique');

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('services');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a quote line be a service instead of a product (9.4.2).
     *
     * `quote_items` already denormalises sku, name, price and total, so the
     * line renders and totals correctly whatever it points at — the only
     * thing missing was somewhere to record WHICH service, so the line can
     * be traced back to the price list it came from.
     *
     * product_id becomes nullable because a service line has no product. It
     * carries no foreign key upstream (only quote_id does), so relaxing it
     * constrains nothing that was being enforced.
     *
     * Raw SQL for the MODIFY: `->change()` needs doctrine/dbal, which this
     * project does not carry.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `quote_items` MODIFY `product_id` INT UNSIGNED NULL');

        Schema::table('quote_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quote_items', 'service_id')) {
                $table->integer('service_id')->unsigned()->nullable()->after('product_id');

                // RESTRICT, not CASCADE: deleting a service must not silently
                // delete lines from quotes already sent to a customer. The
                // Services module retires rather than deletes for the same
                // reason, and this makes the database agree.
                $table->foreign('service_id')
                    ->references('id')
                    ->on('services')
                    ->onDelete('restrict');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Any quote line that was a service becomes an orphan with no product
     * and no service, so those lines are removed first rather than left
     * pointing at nothing.
     *
     * @return void
     */
    public function down()
    {
        DB::table('quote_items')->whereNotNull('service_id')->delete();

        Schema::table('quote_items', function (Blueprint $table) {
            if (Schema::hasColumn('quote_items', 'service_id')) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            }
        });

        DB::statement('ALTER TABLE `quote_items` MODIFY `product_id` INT UNSIGNED NOT NULL');
    }
};

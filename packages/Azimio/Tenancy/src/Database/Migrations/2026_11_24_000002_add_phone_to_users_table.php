<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff phone numbers (9.3).
     *
     * Real columns rather than attributes: `users` is not one of Krayin's
     * attribute entities, so there is no EAV route to it — and no
     * AttributeSeeder to delete them either.
     *
     * Two plain columns rather than the multi-value `phone` widget the
     * organizations profile gets, for the same reason: that widget belongs
     * to the attribute system, and users are outside it.
     *
     * This also unblocks the per-user staff alert SMS left over from 8.20,
     * which was deferred on exactly this missing column.
     *
     * 32 chars is generous for E.164 (max 15 digits plus a +), leaving room
     * for however people actually type numbers before PhoneNormalizer gets
     * to them.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'phone2')) {
                $table->string('phone2', 32)->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['phone', 'phone2'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

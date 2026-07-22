<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite (tenant_id, created_at) indexes on the highest-volume tables:
     * dashboards and datagrids filter by tenant + date range on every load.
     *
     * @var array
     */
    protected $tables = [
        'activities',
        'emails',
        'leads',
        'persons',
        'organizations',
        'products',
        'quotes',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index(['tenant_id', 'created_at'], $table.'_tenant_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_tenant_created_idx');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business tables that carry a tenant_id. Rows with NULL tenant_id are
     * platform-level (shared defaults or super-admin data). Child/pivot
     * tables (quote_items, lead_tags, attribute_values, ...) inherit their
     * tenant through the parent row and are intentionally not listed.
     *
     * @var array
     */
    protected $tables = [
        'activities',
        'attributes',
        'core_config',
        'email_templates',
        'emails',
        'groups',
        'lead_pipelines',
        'lead_sources',
        'lead_types',
        'leads',
        'marketing_campaigns',
        'marketing_events',
        'organizations',
        'persons',
        'products',
        'quotes',
        'roles',
        'tags',
        'warehouses',
        'web_forms',
        'webhooks',
        'workflows',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('tenant_id')->nullable()->after('id');
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $blueprint->index('tenant_id');
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
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['tenant_id']);
                $blueprint->dropIndex(['tenant_id']);
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};

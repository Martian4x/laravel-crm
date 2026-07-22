<?php

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Pipeline;

beforeEach(function () {
    $this->manager = app(TenantManager::class);

    $this->tenantA = Tenant::firstOrCreate(['slug' => 'iso-test-a'], ['name' => 'Iso Test A']);
    $this->tenantB = Tenant::firstOrCreate(['slug' => 'iso-test-b'], ['name' => 'Iso Test B']);
});

afterEach(function () {
    $this->manager->forget();

    Person::withoutTenancy()->whereIn('tenant_id', [$this->tenantA->id, $this->tenantB->id])->delete();

    $this->tenantA->delete();
    $this->tenantB->delete();
});

it('stamps new records with the active tenant', function () {
    $this->manager->set($this->tenantA);

    $person = Person::create(['name' => 'Iso Person A']);

    expect($person->tenant_id)->toBe($this->tenantA->id);
});

it('hides one tenant\'s records from another', function () {
    $this->manager->set($this->tenantA);
    $person = Person::create(['name' => 'Iso Person A']);

    $this->manager->set($this->tenantB);

    expect(Person::find($person->id))->toBeNull()
        ->and(Person::where('name', 'Iso Person A')->count())->toBe(0);
});

it('blocks cross-tenant updates and deletes through the model', function () {
    $this->manager->set($this->tenantA);
    $person = Person::create(['name' => 'Iso Person A']);

    $this->manager->set($this->tenantB);

    expect(Person::where('id', $person->id)->update(['job_title' => 'hacked']))->toBe(0)
        ->and(Person::where('id', $person->id)->delete())->toBe(0);

    $this->manager->set($this->tenantA);

    expect(Person::find($person->id))->not->toBeNull();
});

it('shows shared platform defaults to every tenant', function () {
    $platformPipelines = Pipeline::withoutTenancy()->whereNull('tenant_id')->count();

    $this->manager->set($this->tenantA);

    expect(Pipeline::count())->toBeGreaterThanOrEqual($platformPipelines);
});

it('scopes raw datagrid query builders to the tenant', function () {
    $this->manager->set($this->tenantA);

    $grid = new class
    {
        public $qb;

        public function getQueryBuilder()
        {
            return $this->qb;
        }
    };

    $grid->qb = \Illuminate\Support\Facades\DB::table('leads');

    app(\Azimio\Tenancy\Listeners\ScopeDataGridToTenant::class)
        ->handle('datagrid.lead_data_grid.query_builder.set.after', [$grid]);

    expect($grid->qb->toSql())->toContain('`leads`.`tenant_id`');

    // Shared-defaults table gets the "own OR platform" filter
    $grid->qb = \Illuminate\Support\Facades\DB::table('lead_pipelines');

    app(\Azimio\Tenancy\Listeners\ScopeDataGridToTenant::class)
        ->handle('datagrid.pipeline_data_grid.query_builder.set.after', [$grid]);

    expect($grid->qb->toSql())->toContain('is null');
});

it('runs unscoped when no tenant context is set', function () {
    $this->manager->set($this->tenantA);
    $person = Person::create(['name' => 'Iso Person A']);

    $this->manager->forget();

    expect(Person::find($person->id))->not->toBeNull();
});

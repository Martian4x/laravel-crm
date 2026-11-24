<?php

use Azimio\Services\Enums\ServiceUnit;
use Azimio\Services\Models\Service;
use Azimio\Services\Support\QuoteLineRef;
use Azimio\Tenancy\Models\Tenant;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;
use Webkul\User\Models\User;

/**
 * Phase 9.4.2 — selling services on quotes.
 *
 * The mechanism under test: the quote form's lookup writes whatever `id`
 * the search returned straight into the line's `product_id`, because its
 * hidden input is `v-model="selectedItem.id"` and there is no second
 * field. Services therefore travel as `svc-12` and are unpacked
 * server-side.
 *
 * The thing that would hurt most if it broke is a service line silently
 * storing a NUMBER in product_id — it would then point at an unrelated
 * product, and the quote would show the wrong item at the wrong price.
 */
function sqAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function sqTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function sqOtherTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'kilimanjaro-suppliers')->firstOrFail();
}

function sqService(array $attributes = [], ?int $tenantId = null): Service
{
    $service = new Service;

    $service->forceFill(array_merge([
        'tenant_id'  => $tenantId ?? sqTenant()->id,
        'name'       => 'Advisory Retainer',
        'code'       => 'ADV-'.uniqid(),
        'price'      => 250000,
        'unit'       => ServiceUnit::Month->value,
        'is_taxable' => true,
        'is_active'  => true,
    ], $attributes))->save();

    return $service;
}

function sqPerson(): Person
{
    return Person::withoutTenancy()->where('tenant_id', sqTenant()->id)->firstOr(function () {
        $person = new Person;

        $person->forceFill([
            'tenant_id'       => sqTenant()->id,
            'name'            => 'Quote Test Person',
            'emails'          => [['value' => 'qt@example.test', 'label' => 'work']],
            'contact_numbers' => [['value' => '255700000111', 'label' => 'work']],
        ])->save();

        return $person;
    });
}

function sqQuotePayload(string $lineRef, array $overrides = []): array
{
    return array_merge([
        'subject'         => 'Service Quote '.uniqid(),
        'user_id'         => sqAdmin()->id,
        'person_id'       => sqPerson()->id,
        'expired_at'      => now()->addDays(14)->format('Y-m-d'),
        'sub_total'       => 250000,
        'grand_total'     => 250000,
        'discount_amount' => 0,
        'tax_amount'      => 0,
        'billing_address' => [
            'address'  => 'Plot 1',
            'country'  => 'TZ',
            'state'    => 'Dar es salaam',
            'city'     => 'Dar es salaam',
            'postcode' => '11000',
        ],
        'items' => [
            'item_0' => [
                'product_id'      => $lineRef,
                'name'            => 'Advisory Retainer',
                'quantity'        => 1,
                'price'           => 250000,
                'total'           => 250000,
                'discount_amount' => 0,
                'tax_amount'      => 0,
                'final_total'     => 250000,
            ],
        ],
    ], $overrides);
}

afterEach(function () {
    QuoteItem::whereNotNull('service_id')->delete();

    Quote::withoutTenancy()
        ->where('subject', 'like', 'Service Quote %')
        ->orWhere('subject', 'like', 'Product Quote %')
        ->get()
        ->each(function ($quote) {
            $quote->items()->delete();
            $quote->delete();
        });

    Service::withoutTenancy()
        ->whereIn('tenant_id', [sqTenant()->id, sqOtherTenant()->id])
        ->delete();
});

it('offers the tenant services in the quote item lookup', function () {
    $service = sqService(['name' => 'Rooftop Installation']);

    $response = test()->actingAs(sqAdmin())
        ->getJson(route('admin.products.search', ['query' => 'Rooftop']));

    $response->assertOK();

    $rows = collect($response->json('data'));
    $row = $rows->firstWhere('id', QuoteLineRef::forService($service->id));

    expect($row)->not->toBeNull()
        ->and($row['name'])->toContain('Rooftop Installation')
        // Tagged so a seller can tell it from a product of the same name.
        // Asserted via trans(): twiga-traders runs in Swahili, so this
        // reads "(huduma)" and hardcoding English would be wrong.
        ->and($row['name'])->toContain(trans('services::app.lookup-tag'))
        ->and((float) $row['price'])->toBe(250000.0);
});

it('does not offer another tenant services', function () {
    sqService(['name' => 'Secret Rate Card', 'code' => 'rate-card-other-tenant'], sqOtherTenant()->id);

    test()->actingAs(sqAdmin())
        ->getJson(route('admin.products.search', ['query' => 'Secret Rate Card']))
        ->assertOK()
        ->assertJsonMissing(['sku' => 'rate-card-other-tenant']);
});

it('does not offer retired services', function () {
    $retired = sqService(['name' => 'Discontinued Audit', 'is_active' => false]);

    $response = test()->actingAs(sqAdmin())
        ->getJson(route('admin.products.search', ['query' => 'Discontinued']));

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain(QuoteLineRef::forService($retired->id));
});

it('saves a service quote line against the service, not a product', function () {
    $service = sqService();

    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload(QuoteLineRef::forService($service->id)))
        ->assertRedirect();

    $item = QuoteItem::where('service_id', $service->id)->latest('id')->first();

    expect($item)->not->toBeNull()
        ->and($item->service_id)->toBe($service->id)
        // The crux: it must NOT have been coerced into a product id.
        ->and($item->product_id)->toBeNull()
        ->and((float) $item->total)->toBe(250000.0);
});

it('still saves an ordinary product line', function () {
    // Regression: merging services into the search must not disturb the
    // path every existing quote already uses.
    $product = Product::withoutTenancy()->where('tenant_id', sqTenant()->id)->first();

    if (! $product) {
        $product = new Product;
        $product->forceFill([
            'tenant_id' => sqTenant()->id,
            'name'      => 'Test Widget',
            'sku'       => 'WIDGET-'.uniqid(),
            'price'     => 250000,
            'quantity'  => 10,
        ])->save();
    }

    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload((string) $product->id, [
            'subject' => 'Product Quote '.uniqid(),
        ]))
        ->assertRedirect();

    $item = QuoteItem::where('product_id', $product->id)->latest('id')->first();

    expect($item)->not->toBeNull()
        ->and($item->product_id)->toBe($product->id)
        ->and($item->service_id)->toBeNull();
});

it('refuses a quote line pointing at another tenant service', function () {
    $theirs = sqService(['name' => 'Theirs'], sqOtherTenant()->id);

    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload(QuoteLineRef::forService($theirs->id)))
        ->assertSessionHasErrors('items.item_0.product_id');

    expect(QuoteItem::where('service_id', $theirs->id)->count())->toBe(0);
});

it('refuses a quote line pointing at a retired service', function () {
    $retired = sqService(['is_active' => false]);

    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload(QuoteLineRef::forService($retired->id)))
        ->assertSessionHasErrors('items.item_0.product_id');
});

it('refuses a quote line pointing at nothing real', function () {
    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload('svc-999999'))
        ->assertSessionHasErrors('items.item_0.product_id');

    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload('not-a-reference'))
        ->assertSessionHasErrors('items.item_0.product_id');
});

it('keeps a sold service resolvable after it is retired', function () {
    // Retiring is not deleting, precisely so a quote already sent to a
    // customer still shows what was sold.
    $service = sqService();

    test()->actingAs(sqAdmin())
        ->post(route('admin.quotes.store'), sqQuotePayload(QuoteLineRef::forService($service->id)))
        ->assertRedirect();

    $service->update(['is_active' => false]);

    $item = QuoteItem::where('service_id', $service->id)->latest('id')->first();

    expect($item->service)->not->toBeNull()
        ->and($item->service->name)->toBe('Advisory Retainer');
});

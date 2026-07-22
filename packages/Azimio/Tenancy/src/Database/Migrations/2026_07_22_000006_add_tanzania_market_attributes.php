<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tanzania-market attributes (platform-wide, NULL tenant_id — visible to
     * every tenant like the other shared attribute definitions):
     *
     * - quotes.payment_method + payment_reference: record how a deal was paid
     *   (mobile money is the norm in TZ) and its transaction id.
     * - organizations.tin + vrn: TRA taxpayer & VAT registration numbers.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();

        DB::table('attributes')->insert([
            [
                'code'            => 'payment_method',
                'name'            => 'Payment Method',
                'type'            => 'select',
                'entity_type'     => 'quotes',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => 14,
                'is_required'     => 0,
                'is_unique'       => 0,
                'quick_add'       => 0,
                'is_user_defined' => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ], [
                'code'            => 'payment_reference',
                'name'            => 'Payment Reference (TxID)',
                'type'            => 'text',
                'entity_type'     => 'quotes',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => 15,
                'is_required'     => 0,
                'is_unique'       => 0,
                'quick_add'       => 0,
                'is_user_defined' => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ], [
                'code'            => 'tin',
                'name'            => 'TIN',
                'type'            => 'text',
                'entity_type'     => 'organizations',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => 4,
                'is_required'     => 0,
                'is_unique'       => 0,
                'quick_add'       => 0,
                'is_user_defined' => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ], [
                'code'            => 'vrn',
                'name'            => 'VRN',
                'type'            => 'text',
                'entity_type'     => 'organizations',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => 5,
                'is_required'     => 0,
                'is_unique'       => 0,
                'quick_add'       => 0,
                'is_user_defined' => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ]);

        $paymentMethodId = DB::table('attributes')
            ->where('code', 'payment_method')
            ->where('entity_type', 'quotes')
            ->value('id');

        $options = ['M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halopesa', 'T-Pesa', 'Bank Transfer', 'Cash'];

        foreach ($options as $sort => $name) {
            DB::table('attribute_options')->insert([
                'attribute_id' => $paymentMethodId,
                'name'         => $name,
                'sort_order'   => $sort + 1,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $attributeIds = DB::table('attributes')
            ->whereIn('code', ['payment_method', 'payment_reference', 'tin', 'vrn'])
            ->whereIn('entity_type', ['quotes', 'organizations'])
            ->pluck('id');

        DB::table('attribute_options')->whereIn('attribute_id', $attributeIds)->delete();

        DB::table('attribute_values')->whereIn('attribute_id', $attributeIds)->delete();

        DB::table('attributes')->whereIn('id', $attributeIds)->delete();
    }
};

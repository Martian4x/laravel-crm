<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tanzania's 31 administrative regions (mikoa), so address forms offer a
     * region dropdown when the country is Tanzania.
     *
     * @var array
     */
    protected $regions = [
        'AR' => 'Arusha',
        'DS' => 'Dar es Salaam',
        'DO' => 'Dodoma',
        'GE' => 'Geita',
        'IR' => 'Iringa',
        'KR' => 'Kagera',
        'KA' => 'Katavi',
        'KI' => 'Kigoma',
        'KJ' => 'Kilimanjaro',
        'LI' => 'Lindi',
        'MY' => 'Manyara',
        'MR' => 'Mara',
        'MB' => 'Mbeya',
        'MJ' => 'Mjini Magharibi',
        'MO' => 'Morogoro',
        'MT' => 'Mtwara',
        'MW' => 'Mwanza',
        'NJ' => 'Njombe',
        'PN' => 'Pemba North',
        'PS' => 'Pemba South',
        'PW' => 'Pwani',
        'RK' => 'Rukwa',
        'RV' => 'Ruvuma',
        'SH' => 'Shinyanga',
        'SI' => 'Simiyu',
        'SD' => 'Singida',
        'SO' => 'Songwe',
        'TB' => 'Tabora',
        'TN' => 'Tanga',
        'UN' => 'Unguja North',
        'US' => 'Unguja South',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $countryId = DB::table('countries')->where('code', 'TZ')->value('id');

        if (! $countryId) {
            return;
        }

        foreach ($this->regions as $code => $name) {
            DB::table('country_states')->updateOrInsert(
                ['country_code' => 'TZ', 'code' => $code],
                [
                    'name'       => $name,
                    'country_id' => $countryId,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('country_states')->where('country_code', 'TZ')->delete();
    }
};

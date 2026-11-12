<?php

return [
    'name' => 'Ujumbe',

    'sms' => [
        'title' => 'SMS',

        'modes' => [
            'platform' => 'Salio la jukwaa (AZIMIOCRM)',
            'own'      => 'Mtoa huduma wako',
        ],

        'status' => [
            'queued'              => 'Imepangwa',
            'sending'             => 'Inatumwa',
            'submitted'           => 'Imewasilishwa',
            'delivered'           => 'Imefika',
            'failed'              => 'Imeshindikana',
            'failed-own-provider' => 'Imeshindikana kwa mtoa huduma wako',
            'rejected'            => 'Imekataliwa',
            'undeliverable'       => 'Haiwezi kufikishwa',
        ],

        'errors' => [
            'own-provider-failed'    => 'Mtoa huduma wako wa SMS alikataa ujumbe huu: :reason',
            'retry-via-platform'     => 'Tuma kwa kutumia AZIMIOCRM (salio :credits)',
            'no-platform-credits'    => 'Ujumbe huu unahitaji salio :needed lakini unalo :available. Omba salio la SMS ili kutuma.',
            'sender-not-approved'    => 'Jina lako la mtumaji linasubiri idhini, haliwezi kutuma bado.',
            'opted-out'              => 'Namba hii imejitoa kwenye ujumbe wako.',
            'invalid-number'         => 'Namba hii haikuweza kusomeka kama namba halali ya simu.',
            'quiet-hours'            => 'Ujumbe wa matangazo unasubiri hadi :time.',
        ],
    ],
];

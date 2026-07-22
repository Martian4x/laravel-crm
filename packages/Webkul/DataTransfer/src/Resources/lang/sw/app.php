<?php

return [
    'importers' => [
        'persons' => [
            'title' => 'Watu',

            'validation' => [
                'errors' => [
                    'duplicate-email' => 'Barua pepe : \'%s\' imepatikana zaidi ya mara moja kwenye faili la kuingiza.',
                    'duplicate-phone' => 'Simu : \'%s\' imepatikana zaidi ya mara moja kwenye faili la kuingiza.',
                    'email-not-found' => 'Barua pepe : \'%s\' haijapatikana kwenye mfumo.',
                ],
            ],
        ],

        'products' => [
            'title' => 'Bidhaa',

            'validation' => [
                'errors' => [
                    'sku-not-found' => 'Bidhaa yenye SKU iliyotajwa haijapatikana',
                ],
            ],
        ],

        'leads' => [
            'title' => 'Fursa',

            'validation' => [
                'errors' => [
                    'id-not-found' => 'Kitambulisho : \'%s\' hakijapatikana kwenye mfumo.',
                ],
            ],
        ],
    ],

    'validation' => [
        'errors' => [
            'column-empty-headers' => 'Safu namba "%s" zina vichwa vitupu.',
            'column-name-invalid'  => 'Majina ya safu si sahihi: "%s".',
            'column-not-found'     => 'Safu zinazohitajika hazijapatikana: %s.',
            'column-numbers'       => 'Idadi ya safu hailingani na idadi ya safu kwenye kichwa.',
            'invalid-attribute'    => 'Kichwa kina sifa zisizo sahihi: "%s".',
            'system'               => 'Hitilafu ya mfumo isiyotarajiwa imetokea.',
            'wrong-quotes'         => 'Alama za kunukuu zilizopinda zimetumika badala ya zilizonyooka.',
            'already-exists'       => ':attribute tayari ipo.',
        ],
    ],
];

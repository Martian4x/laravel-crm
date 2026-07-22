<?php

return [
    'seeders' => [
        'attributes' => [
            'leads' => [
                'description'         => 'Maelezo',
                'expected-close-date' => 'Tarehe Tarajiwa ya Kufunga',
                'lead-value'          => 'Thamani ya Fursa',
                'sales-owner'         => 'Mmiliki wa Mauzo',
                'source'              => 'Chanzo',
                'title'               => 'Kichwa',
                'type'                => 'Aina',
                'pipeline'            => 'Mchakato',
                'stage'               => 'Hatua',
            ],

            'persons' => [
                'contact-numbers' => 'Namba za Mawasiliano',
                'emails'          => 'Barua Pepe',
                'job-title'       => 'Cheo cha Kazi',
                'name'            => 'Jina',
                'organization'    => 'Shirika',
                'sales-owner'     => 'Mmiliki wa Mauzo',
            ],

            'organizations' => [
                'address'     => 'Anwani',
                'name'        => 'Jina',
                'sales-owner' => 'Mmiliki wa Mauzo',
            ],

            'products' => [
                'description' => 'Maelezo',
                'name'        => 'Jina',
                'price'       => 'Bei',
                'quantity'    => 'Idadi',
                'sku'         => 'SKU',
            ],

            'quotes' => [
                'adjustment-amount' => 'Kiasi cha Marekebisho',
                'billing-address'   => 'Anwani ya Malipo',
                'description'       => 'Maelezo',
                'discount-amount'   => 'Kiasi cha Punguzo',
                'discount-percent'  => 'Asilimia ya Punguzo',
                'expired-at'        => 'Muda wa Mwisho',
                'grand-total'       => 'Jumla Kuu',
                'person'            => 'Mtu',
                'sales-owner'       => 'Mmiliki wa Mauzo',
                'shipping-address'  => 'Anwani ya Usafirishaji',
                'sub-total'         => 'Jumla Ndogo',
                'subject'           => 'Mada',
                'tax-amount'        => 'Kiasi cha Kodi',
            ],

            'warehouses' => [
                'contact-address' => 'Anwani ya Mawasiliano',
                'contact-emails'  => 'Barua Pepe za Mawasiliano',
                'contact-name'    => 'Jina la Mawasiliano',
                'contact-numbers' => 'Namba za Mawasiliano',
                'description'     => 'Maelezo',
                'name'            => 'Jina',
            ],
        ],

        'email' => [
            'activity-created'      => 'Shughuli imeundwa',
            'activity-modified'     => 'Shughuli imebadilishwa',
            'date'                  => 'Tarehe',
            'new-activity'          => 'Una shughuli mpya, tafadhali angalia maelezo hapa chini',
            'new-activity-modified' => 'Una shughuli iliyobadilishwa, tafadhali angalia maelezo hapa chini',
            'participants'          => 'Washiriki',
            'title'                 => 'Kichwa',
            'type'                  => 'Aina',
        ],

        'lead' => [
            'pipeline' => [
                'default' => 'Mchakato wa Kawaida',

                'pipeline-stages' => [
                    'follow-up'   => 'Ufuatiliaji',
                    'lost'        => 'Imepotea',
                    'negotiation' => 'Majadiliano',
                    'new'         => 'Mpya',
                    'prospect'    => 'Mteja Tarajiwa',
                    'won'         => 'Imeshinda',
                ],
            ],

            'source' => [
                'direct'   => 'Moja kwa Moja',
                'email'    => 'Barua Pepe',
                'phone'    => 'Simu',
                'web'      => 'Wavuti',
                'web-form' => 'Fomu ya Wavuti',
            ],

            'type' => [
                'existing-business' => 'Biashara Iliyopo',
                'new-business'      => 'Biashara Mpya',
            ],
        ],

        'user' => [
            'role' => [
                'administrator-role' => 'Jukumu la Msimamizi',
                'administrator'      => 'Msimamizi',
            ],
        ],

        'workflow' => [
            'email-to-participants-after-activity-updation' => 'Barua pepe kwa washiriki baada ya shughuli kubadilishwa',
            'email-to-participants-after-activity-creation' => 'Barua pepe kwa washiriki baada ya shughuli kuundwa',
        ],
    ],

    'installer' => [
        'index' => [
            'create-administrator' => [
                'admin'            => 'Msimamizi',
                'krayin'           => 'AzimioCustomers',
                'confirm-password' => 'Thibitisha Nywila',
                'email'            => 'Barua Pepe',
                'email-address'    => 'admin@example.com',
                'password'         => 'Nywila',
                'title'            => 'Unda Msimamizi',
            ],

            'environment-configuration' => [
                'allowed-currencies'  => 'Sarafu Zinazoruhusiwa',
                'allowed-locales'     => 'Lugha Zinazoruhusiwa',
                'application-name'    => 'Jina la Programu',
                'krayin'              => 'AzimioCustomers',
                'database-connection' => 'Muunganisho wa Hifadhidata',
                'database-hostname'   => 'Jina la Seva ya Hifadhidata',
                'database-name'       => 'Jina la Hifadhidata',
                'database-password'   => 'Nywila ya Hifadhidata',
                'database-port'       => 'Bandari ya Hifadhidata',
                'database-prefix'     => 'Kiambishi cha Hifadhidata',
                'database-username'   => 'Jina la Mtumiaji wa Hifadhidata',
                'default-currency'    => 'Sarafu ya Kawaida',
                'default-locale'      => 'Lugha ya Kawaida',
                'default-timezone'    => 'Saa za Eneo la Kawaida',
                'default-url'         => 'URL ya Kawaida',
                'default-url-link'    => 'https://localhost',
                'mysql'               => 'Mysql',
                'pgsql'               => 'pgSQL',
                'select-timezone'     => 'Chagua Saa za Eneo',
                'sqlsrv'              => 'SQLSRV',
                'tanzanian-shilling'  => 'Shilingi ya Tanzania (TZS)',
                'title'               => 'Usanidi wa Mfumo',
                'united-states-dollar' => 'Dola ya Marekani (USD)',
                'warning-message'     => 'Tahadhari! Mipangilio ya lugha ya kawaida na sarafu ya kawaida ni ya kudumu na haiwezi kubadilishwa baada ya kuwekwa.',
            ],

            'installation-processing' => [
                'krayin'       => 'Usakinishaji wa AzimioCustomers',
                'krayin-info'  => 'Kuunda majedwali ya hifadhidata, hii inaweza kuchukua muda kidogo',
                'title'        => 'Usakinishaji',
            ],

            'installation-completed' => [
                'admin-panel'                => 'Paneli ya Msimamizi',
                'krayin-forums'              => 'Jukwaa la AzimioCustomers',
                'customer-panel'             => 'Paneli ya Wateja',
                'explore-krayin-extensions'  => 'Chunguza Viendelezi vya AzimioCustomers',
                'title'                      => 'Usakinishaji Umekamilika',
                'title-info'                 => 'AzimioCustomers imesakinishwa kwa mafanikio kwenye mfumo wako.',
            ],

            'ready-for-installation' => [
                'create-databsae-table'   => 'Unda jedwali la hifadhidata',
                'install'                 => 'Usakinishaji',
                'install-info'            => 'AzimioCustomers Kwa Usakinishaji',
                'install-info-button'     => 'Bofya kitufe hapa chini ili',
                'populate-database-table' => 'Jaza majedwali ya hifadhidata',
                'start-installation'      => 'Anza Usakinishaji',
                'title'                   => 'Tayari kwa Usakinishaji',
            ],

            'start' => [
                'locale'        => 'Lugha',
                'main'          => 'Anza',
                'select-locale' => 'Chagua Lugha',
                'title'         => 'Usakinishaji wako wa AzimioCustomers',
                'welcome-title' => 'Karibu AzimioCustomers',
            ],

            'server-requirements' => [
                'calendar'    => 'Kalenda',
                'ctype'       => 'cType',
                'curl'        => 'cURL',
                'dom'         => 'dom',
                'fileinfo'    => 'fileInfo',
                'filter'      => 'Filter',
                'gd'          => 'GD',
                'hash'        => 'Hash',
                'intl'        => 'intl',
                'json'        => 'JSON',
                'mbstring'    => 'mbstring',
                'openssl'     => 'openssl',
                'pcre'        => 'pcre',
                'pdo'         => 'pdo',
                'php'         => 'PHP',
                'php-version' => '8.1 au zaidi',
                'session'     => 'session',
                'title'       => 'Mahitaji ya Mfumo',
                'tokenizer'   => 'tokenizer',
                'xml'         => 'XML',
            ],

            'back'                     => 'Rudi',
            'krayin'                   => 'AzimioCustomers',
            'krayin-info'              => 'Mradi wa Jamii wa',
            'krayin-logo'              => 'Nembo ya AzimioCustomers',
            'continue'                 => 'Endelea',
            'installation-description' => 'Usakinishaji wa AzimioCustomers kwa kawaida unahusisha hatua kadhaa. Hapa kuna muhtasari wa mchakato wa usakinishaji wa AzimioCustomers',
            'installation-info'        => 'Tunafurahi kukuona hapa!',
            'installation-title'       => 'Karibu kwenye Usakinishaji',
            'installation-wizard'      => 'Lugha ya Msaidizi wa Usakinishaji',
            'title'                    => 'Kisakinishi cha AzimioCustomers',
            'webkul'                   => 'Webkul',
        ],
    ],
];

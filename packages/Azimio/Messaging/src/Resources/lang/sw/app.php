<?php

return [
    'name' => 'Ujumbe',

    'acl' => [
        'sms'      => 'Ujumbe',
        'create'   => 'Tuma SMS',
        'logs'     => 'Kumbukumbu za SMS',
        'settings' => 'Mipangilio ya SMS',
    ],

    'sms' => [
        'title' => 'SMS',

        'log' => [
            'title'          => 'Kumbukumbu za SMS',
            'batches'        => 'Makundi',
            'batch'          => 'Kundi',
            'batch-name'     => 'Jina',
            'status'         => 'Hali',
            'number'         => 'Namba',
            'when'           => 'Lini',
            'error'          => 'Hitilafu',
            'recipients'     => 'Wapokeaji',
            'delivered'      => 'Zilifika',
            'failed'         => 'Zilishindikana',
            'total-messages' => 'Jumbe',
            'any-status'     => 'Hali yoyote',
            'any-batch'      => 'Kundi lolote',
            'filter'         => 'Chuja',
            'export'         => 'Pakua CSV',
            'empty'          => 'Hakuna jumbe bado.',
            'no-batches'     => 'Hakuna makundi bado.',
            'failed-summary' => 'Jumbe :count katika kundi hili zilishindikana.',
            'retry-same'     => 'Jaribu tena zilizoshindikana',
            'retry-queued'   => 'Jumbe :count zimepangwa tena.',
            'nothing-to-retry' => 'Hakuna jumbe zilizoshindikana za kujaribu tena.',
            'shortfall'      => 'Unakosa salio :credits kujaribu tena kwa AZIMIOCRM.',
            'activity-title' => 'SMS ilitumwa kwa :number',
        ],

        'compose' => [
            'title'             => 'Tuma SMS',
            'to'                => 'Kwenda kwa',
            'message'           => 'Ujumbe',
            'template'          => 'Kiolezo',
            'no-template'       => 'Hakuna kiolezo',
            'preview'           => 'Muonekano',
            'characters'        => 'Herufi',
            'segments'          => 'Vipande',
            'credits'           => 'Salio',
            'send'              => 'Tuma SMS',
            'unicode-warning'   => 'Una herufi isiyo ya GSM, hivyo uwezo unashuka kutoka 160 hadi 70 kwa kipande.',
            'queued'            => 'Ujumbe umepangwa kwenda :number.',
            'nothing-to-send'   => 'Hakukuwa na chochote cha kutuma.',
            'single-batch-name' => 'Ujumbe mmoja',
        ],

        'settings' => [
            'title'          => 'Mipangilio ya SMS',
            'platform-title' => 'Salio la jukwaa',
            'platform-help'  => 'Tuma kwa kutumia jina la AzimioCustomers na salio ulilopewa kampuni yako. Hakuna cha kusanidi.',
            'own-title'      => 'Mtoa huduma wako wa SMS',
            'own-help'       => 'Tuma kwa kutumia akaunti na salio lako mwenyewe. Salio la jukwaa halitumiki kabisa.',
            'own-label'      => 'Mtoa huduma wako',

            'sender-name'      => 'Jina la mtumaji',
            'sender-name-help' => 'Hadi herufi 11. Lazima liwe tayari limesajiliwa na linafanya kazi kwenye akaunti yako.',
            'provider'         => 'Mtoa huduma',
            'api-key'          => 'Ufunguo wa API',
            'secret-key'       => 'Siri ya API',
            'credit-balance'   => 'Salio la SMS',
            'provider-balance' => 'Salio kwa mtoa huduma wako',

            'currently-active' => 'Inatumika sasa',
            'use-platform'     => 'Badilisha kwenda salio la jukwaa',
            'save-and-verify'  => 'Hifadhi na thibitisha',
            'verify-note'      => 'Taarifa zako zitahakikiwa kwa mtoa huduma kabla hazijaanza kutumika, ili kosa dogo lisisababishe ujumbe kushindwa kimyakimya.',

            'low-balance' => 'Salio lako la SMS linakaribia kuisha. Omba salio zaidi kabla ya kampeni yako ijayo.',

            'test-title' => 'Tuma ujumbe wa majaribio',
            'test-help'  => 'Hutuma SMS moja kwa njia inayotumika hapo juu, ili uhakikishe inafika.',
            'send-test'  => 'Tuma jaribio',
            'test-body'  => 'Ujumbe wa majaribio kutoka :tenant kupitia AzimioCustomers.',

            'own-activated'      => 'Mtoa huduma wako amethibitishwa na sasa anatumika.',
            'platform-activated' => 'Sasa unatuma kwa kutumia salio la jukwaa.',
            'test-queued'        => 'Ujumbe wa majaribio umepangwa kwenda :number.',
            'test-failed'        => 'Ujumbe wa majaribio haukuweza kupangwa.',
            'no-profile'         => 'Hakuna njia ya kutuma iliyosanidiwa bado.',

            'credentials-rejected'  => 'Mtoa huduma wako amekataa taarifa hizi. Hakiki ufunguo na siri ya API kisha jaribu tena.',
            'sender-not-on-account' => 'Jina la mtumaji ":sender" halitumiki kwenye akaunti yako. Majina yaliyosajiliwa: :names',
        ],

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
        ],

        'phone' => [
            'empty'        => 'Hakuna namba ya simu iliyotolewa.',
            'too-short'    => 'Namba hii ni fupi mno kuwa namba ya simu.',
            'too-long'     => 'Namba hii ni ndefu mno kuwa namba ya simu.',
            'not-mobile'   => 'Hii inaonekana ni simu ya mezani, haiwezi kupokea SMS.',
            'unrecognised' => 'Namba hii haikueleweka.',
            'quiet-hours'            => 'Ujumbe wa matangazo unasubiri hadi :time.',
        ],
    ],
];

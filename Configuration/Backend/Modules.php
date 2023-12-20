<?php

use Qc\QcReferences\Controller\ReferencesReport;

return [
    'web_QcReferences' => [
        'parent' => 'web_info',
        //'position' => ['after' => 'web_info'],
        'access' => 'user,group',
        'icon' => '',
        'path' => '/module/web/info/QcReferences',
        'labels' => 'LLL:EXT:qc_references/Resources/Private/Language/locallang.xlf:mod_qcPageReferences',
        'extensionName' => 'QcReferences',
        'routes' => [
            '_default' => [
                'target' => ReferencesReport::class . '::getReferencesAction',
            ],
        ],
    ],
];

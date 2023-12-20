<?php

return [
    'filter_references' => [
      //  'path'   => '/QcReferences/filter',
        'referrer' => 'required,refresh-empty',
        'target' => \Qc\QcReferences\Controller\ReferencesReport::class.'::filterReferencesAction'
    ],

];

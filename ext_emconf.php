<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "qc_references"
 *
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Qc References',
    'description' => "This module shows the references to the selected pages in the Pagetree, even if you don't have access to the content linking to it.",
    'author' => 'Quebec.ca',
    'category' => 'Module',
    'state' => 'beta',
    'version' => '12.0.0',
    'autoload' => [
        'psr-4' => [
            'Qc\\QcReferences\\' => 'Classes',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '^12.4.9',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

<?php

defined('TYPO3') || die();

// Extend Module INFO with new Element
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_info',
    \Qc\QcReferences\Controller\ReferencesReport::class,
    '',
    'LLL:EXT:qc_references/Resources/Private/Language/locallang.xlf:mod_qcPageReferences'
);

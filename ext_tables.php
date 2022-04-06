<?php
defined('TYPO3_MODE') || die();

use Qc\QcReferences\ReferencesReport;


// Extend Module INFO with new Element
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_info',
    ReferencesReport::class,
    '',
    'LLL:EXT:qc_references/Resources/Private/Language/locallang.xlf:mod_qcPageReferences'
);

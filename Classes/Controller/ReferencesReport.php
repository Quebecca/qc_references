<?php

declare(strict_types=1);
/***
 *
 * This file is part of Qc References project.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2022 <techno@quebec.ca>
 *
 ***/
namespace Qc\QcReferences\Controller;

use Doctrine\DBAL\Driver\Exception;
use Qc\QcReferences\Domain\Repository\ReferenceRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Info\Controller\InfoModuleController;

class ReferencesReport
{
    const LANG_FILE = 'LLL:EXT:qc_references/Resources/Private/Language/locallang.xlf:';

    /**
     * @var InfoModuleController Contains a reference to the parent calling object
     */
    private $pObj;

    /**
     * @var int
     */
    private int $id = 0;

    /**
     * @var ModuleTemplate
     */
    private ModuleTemplate $moduleTemplate;

    /**
     * @var StandaloneView
     */
    private StandaloneView $view;

    /**
     * @var PageRepository
     */
    private PageRepository $pageRepository;

    /**
     * @var LocalizationUtility
     */
    private LocalizationUtility $localizationUtility;

    /**
     * @var ReferenceRepository
     */
    private ReferenceRepository $referenceRepository;

    /**
     * @var int
     */
    private int $currentPaginationPage = 1;

    /**
     * @var int
     */
    private int $showHiddenOrDeletedElements = 0;

    /**
     * @var UriBuilder|mixed|object
     */
    protected $uriBuilder;

    /**
     * Init, called from parent object
     *
     * @param InfoModuleController $pObj A reference to the parent (calling) object
     */
    public function init($pObj)
    {
        $this->pObj = $pObj;

        $this->id = (int)GeneralUtility::_GP('id');
        $this->showHiddenOrDeletedElements = (int)GeneralUtility::_GP('showHiddenOrDeletedElements');
        $page = (int)GeneralUtility::_GP('paginationPage');
        $this->currentPaginationPage = $page > 0 ? $page : 1;

        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->view = $this->createView('InfoModule');
        $this->pageRepository =  GeneralUtility::makeInstance(PageRepository::class);
        $this->localizationUtility = GeneralUtility::makeInstance(LocalizationUtility::class);
        $this->referenceRepository =  GeneralUtility::makeInstance(ReferenceRepository::class);
    }

    /**
     * Inject UriBuilder
     * @param UriBuilder $uriBuilder
     */
    public function injectUriBuilder(UriBuilder $uriBuilder)
    {
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * @param string $templateName
     * @return StandaloneView
     */
    protected function createView(string $templateName): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setPartialRootPaths(['EXT:qc_references/Resources/Private/Partials']);
        $view->setLayoutRootPaths(['EXT:qc_references/Resources/Private/Layouts']);
        $view->setTemplateRootPaths(['EXT:qc_references/Resources/Private/Templates/Backend']);
        $view->setTemplate($templateName);
        return $view;
    }

    /**
     * Main, called from parent object
     *
     * @return string Module content
     * @throws Exception
     */
    public function main(): string
    {
        $this->initialize();
        $this->view->assignMultiple([
            'content' => $this->renderContent(),
            'pageId' => $this->id,
            'showHiddenOrDeletedElements' => $this->showHiddenOrDeletedElements,
            'pageTitle' => $this->pageRepository->getPage($this->id, true)['title']
        ]);
        return $this->view->render();
    }

    /**
     * Initializes the Module
     */
    protected function initialize()
    {
        $pageRenderer = $this->moduleTemplate->getPageRenderer();
        $pageRenderer->addCssFile('EXT:qc_references/Resources/Public/Css/qcReferences.css', 'stylesheet', 'all');
    }

    /**
     * Create tabs to split the report and the checkLink functions
     * @throws Exception
     */
    protected function renderContent(): string
    {
        $menuItems[] = [
            'label' => $this->localizationUtility->translate(self::LANG_FILE . 'mod_qcPageReferences'),
            'content' => $this->createViewForPageReferencesTable()->render()
        ];
        return $this->moduleTemplate->getDynamicTabMenu($menuItems, 'report-qcreferences');
    }

    /**
     * Displays the View for the Backend User List
     *
     * @return StandaloneView
     * @throws Exception
     */
    protected function createViewForPageReferencesTable(): StandaloneView
    {
        $pagination = $this->referenceRepository->getReferences($this->id, $this->showHiddenOrDeletedElements, $this->currentPaginationPage);
        $data = [];
        // Build URi For rendering records
        foreach ($pagination['paginatedData'] as $record) {
            $record['url'] = $this->buildUriForRow($record);
            $data [] = $record;
        }
        $view = $this->createView('PageReferences');
        $view->assignMultiple([
            'numberOfReferences' => $this->referenceRepository->getNumberOfReferences(),
            'showHiddenOrDeletedElements' => $this->showHiddenOrDeletedElements,
            'currentPage' => $this->id,
            'references' => $data,
            'pagination' => $pagination['pagination'],
        ]);
        return $view;
    }

    /**
     * @param $line
     * @return string
     */
    public function buildUriForRow($line): string
    {
        $key = $line['tablename'] == 'tt_content' ? 'pid' : ($line['tablename'] == 'pages' ? 'recuid' : '');
        return $key != '' ? $this->uriBuilder->reset()->setTargetPageUid($line[$key])->buildFrontendUri() : '';
    }
}

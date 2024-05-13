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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use Doctrine\DBAL\Driver\Exception;
use Qc\QcReferences\Domain\Repository\ReferenceRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

class ReferencesReport
{
    /**
     * @var int
     */
    private int $id = 0;

    /**
     * @var int
     */
    private int $currentPaginationPage = 1;

    /**
     * @var int
     */
    private int $showHiddenOrDeletedElements = 0;

    public function __construct(
        private PageRenderer $pageRenderer,
        private ReferenceRepository $referenceRepository,
        private PageRepository $pageRepository,
        private UriBuilder $uriBuilder,
        private ModuleTemplateFactory $moduleTemplateFactory
    ){}

    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function getReferencesAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:qc_references/Resources/Public/Css/qcReferences.css', 'stylesheet', 'all');
        $this->showHiddenOrDeletedElements = intval($request->getParsedBody()['showHiddenOrDeletedElements']?? 0);
        $page = (int)GeneralUtility::_GP('paginationPage');
        $this->currentPaginationPage = $page > 0 ? $page : 1;
        $this->id = (int)GeneralUtility::_GP('id');
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->makeDocHeaderModuleMenu(['id' => $this->id]);
        $pagination = $this->referenceRepository->getReferences($this->id, $this->showHiddenOrDeletedElements, $this->currentPaginationPage);
        $data = [];
        // Build URi For rendering records
        foreach ($pagination['paginatedData'] as $record) {
            $record['url'] = $this->buildUriForRow($record);
            $data [] = $record;
        }
        $moduleTemplate->assignMultiple([
            'numberOfReferences' => $this->referenceRepository->getNumberOfReferences(),
            'showHiddenOrDeletedElements' => $this->showHiddenOrDeletedElements,
            'currentPage' => $this->id,
            'references' => $data,
            'pagination' => $pagination['pagination'],
            'pageId' => $this->id,
            'pageTitle' => $this->pageRepository->getPage($this->id, true)['title'] ?? ''
        ]);
        return $moduleTemplate->renderResponse('PageReferences');
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

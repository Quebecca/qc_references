<?php

declare(strict_types=1);
/***
 *
 * This file is part of Qc References project.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2026 <techno@quebec.ca>
 *
 ***/

namespace Qc\QcReferences\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use Doctrine\DBAL\Driver\Exception;
use Qc\QcReferences\Domain\Repository\ReferenceRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

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
        private ReferenceRepository   $referenceRepository,
        private PageRepository        $pageRepository,
        private UriBuilder            $uriBuilder,
        private ModuleTemplateFactory $moduleTemplateFactory
    )   {}

    /**
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @throws Exception
     */
    public function getReferencesAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->uriBuilder->setRequest($this->getExtbaseRequest());
        $this->showHiddenOrDeletedElements = intval($request->getParsedBody()['showHiddenOrDeletedElements'] ?? 0);

        $page = (int) ($request->getParsedBody()['paginationPage'] ?? $request->getQueryParams()['paginationPage'] ?? 0);

        $this->currentPaginationPage = $page > 0 ? $page : 1;
        $this->id = (int) ($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);

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
     *
     * @return string
     */
    public function buildUriForRow($line): string
    {
        $key = $line['tablename'] == 'tt_content' ? 'pid' : ($line['tablename'] == 'pages' ? 'recuid' : '');
        return $key != '' ? $this->uriBuilder->reset()->setTargetPageUid($line[$key])->buildFrontendUri() : '';
    }

    private function getExtbaseRequest(): RequestInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        // We have to provide an Extbase request object
        return new Request(
            $request->withAttribute('extbase', new ExtbaseRequestParameters()),
        );
    }
}

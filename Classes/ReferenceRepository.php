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
namespace Qc\QcReferences;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ReferenceRepository
{
    /**
     * @var BackendUserGroupRepository
     */
    private BackendUserGroupRepository $backendUserGroupRepository;

    /**
     * @var IconFactory
     */
    private $iconFactory;

    /**
     * TSconfig of the current User Backend
     *
     * @var array
     */
    protected $modTS = [];

    const DEFAULT_ITEMS_PER_PAGE = 20;

    /**
     * @var int
     */
    protected int $numberOfReferences = 0;

    const LANG_FILE = 'LLL:EXT:qc_references/Resources/Private/Language/locallang.xlf:';

    /**
     * @var QueryBuilder
     */
    protected QueryBuilder $pagesQueryBuilder;
    /**
     * @var QueryBuilder
     */
    protected QueryBuilder $ttContentQueryBuilder;
    /**
     * @var QueryBuilder
     */
    protected QueryBuilder $refIndexQueryBuilder;

    public function __construct()
    {
        $this->backendUserGroupRepository = $backendUserGroupRepository ?? GeneralUtility::makeInstance(BackendUserGroupRepository::class);
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->refIndexQueryBuilder = $this->getQueryBuilderForTable('sys_refindex');
        $this->ttContentQueryBuilder = $this->getQueryBuilderForTable('tt_content');
        $this->pagesQueryBuilder = $this->getQueryBuilderForTable('pages');
    }

    /**
     * Return the references records
     *
     * @param int|File $ref Filename or uid
     * @return array
     * @throws Exception
     * @throws DBALException
     */
    public function getReferences($ref, $showHiddenOrDeletedElement, $paginationPage): array
    {
        $this->modTS = BackendUtility::getPagesTSconfig($ref)['mod.']['qcReferences.'];
        $tsTables = explode(',', $this->modTS['allowedTables']);
        $tsItemsPerPage = (int)$this->modTS['itemsPerPage'];
        $itemsPerPage = $tsItemsPerPage > 0 ?  $tsItemsPerPage : self::DEFAULT_ITEMS_PER_PAGE;

        // Get the allowed tables for elements that refers to the selected page
        $alowedTables = array_map(function ($item) {
            return str_replace(' ', '', $item);
        }, $tsTables);

        $refLines = [];
        $rows = $this->getReferencesFromDB('pages', $ref);
        foreach ($rows as $row) {
            if (!in_array($row['tablename'], $alowedTables)) {
                continue;
            }
            // Check if the element is hidden or deleted
            $ckeck = $this->checkElementIfIsHidden($row['tablename'], $row['recuid']);
            if ($showHiddenOrDeletedElement == 0 && $ckeck) {
                continue;
            }

            $line = $this->mapRowToLine($row);
            $refLines[] = $line;
        }
        $this->numberOfReferences = count($refLines);
        return $this->getPagination($refLines, $paginationPage, $itemsPerPage);
    }

    /**
     * This function is used to map and process returned data from the DB
     * @param $row
     * @return array
     */
    public function mapRowToLine($row): array
    {
        $lang = $this->getLanguageService();
        $record = BackendUtility::getRecord($row['tablename'], $row['recuid'], '*', '', false);

        $line['deleted'] = $record['deleted'];
        $line['elementDescription'] = 'uid : ' . $record['uid'];
        $status = $record['deleted'] ? 'deleted' :  ($record['hidden'] ? 'hidden' : '');
        $line['elementDescription'] .= ' ' . $this->getItemStatus($record['starttime'], $record['endtime'])['statusMessage'];
        $line['elementDescription'] .= ' - ' . $status;
        $line['icon'] = $this->iconFactory->getIconForRecord($row['tablename'], $record, Icon::SIZE_SMALL)->render();
        $line['row'] = $row;
        $line['record'] = $record;
        $line['recordTitle'] = BackendUtility::getRecordTitle($row['tablename'], $record, false, true);
        $line['title'] = $lang->sL($GLOBALS['TCA'][$row['tablename']]['ctrl']['title']);
        $line['tablename'] = $row['tablename'];
        $line['recuid'] = $row['recuid'];

        if ($row['tablename'] == 'tt_content') {
            $line['pid'] = $this->getPid($row['recuid'], $row['tablename'], $this->ttContentQueryBuilder)['pid'];
            $line['groupName'] = $this->getBEGroup($line['pid'], $this->pagesQueryBuilder);
        } else {
            if ($row['tablename'] == 'pages') {
                $line['groupName'] = $this->getBEGroup($row['recuid'], $this->pagesQueryBuilder);
            } else {
                $line['pid'] = '-';
            }
        }
        $line['path'] = BackendUtility::getRecordPath($record['pid'], '', 0, 0);

        return $line;
    }

    /**
     * This function returns the status of the stored element
     * @param $startTime
     * @param $endTime
     * @return string[]
     */
    public function getItemStatus($startTime, $endTime): array
    {
        $lang = $this->getLanguageService();
        if ($endTime !== 0 && $endTime < time()) {
            $numberOfDays = round((time() - $endTime) / (60*60*24));
            return  [
                'status' => 'expired',
                'statusMessage' => $lang->sL(self::LANG_FILE . 'stop') . ' ' . date('d-m-y', $endTime)
                    . " ( $numberOfDays " . $lang->sL(self::LANG_FILE . 'days') . ' )'
            ];
        }
        if ($startTime !== 0 && $startTime > time()) {
            $numberOfDays = round(($startTime - time()) / (60 * 60 * 24));
            return [
                'status' => 'notAvailable',
                'statusMessage' => $lang->sL(self::LANG_FILE . 'start') . ' ' . date('d-m-y', $startTime)
                    . " ( $numberOfDays " . $lang->sL(self::LANG_FILE . 'days') . ' )'
            ];
        }
        return [
            'status' => 'available',
            'statusMessage' => ''
        ];
    }

    /**
     * This function is used to get sys_refindex records from DB
     * @return array
     * @throws Exception
     */
    public function getReferencesFromDB($selectTable, $selectUid): array
    {
        $predicates = [
            $this->refIndexQueryBuilder->expr()->eq(
                'ref_table',
                $this->refIndexQueryBuilder->createNamedParameter($selectTable, \PDO::PARAM_STR)
            ),
            $this->refIndexQueryBuilder->expr()->eq(
                'ref_uid',
                $this->refIndexQueryBuilder->createNamedParameter($selectUid, \PDO::PARAM_INT)
            )
        ];

        return  $this->refIndexQueryBuilder
            ->select('*')
            ->from('sys_refindex')
            ->where(...$predicates)
            ->orderBy('tablename', 'DESC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * This function is used to get the pid for the tt_content element that refers to the selected page
     * @param $uid
     * @param $tablename
     * @param $queryBuilder
     * @return mixed
     */
    protected function getPid($uid, $tablename, $queryBuilder)
    {
        $predicates = [
            $queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
            ),
        ];
        return   $queryBuilder
            ->select('pid')
            ->from($tablename)
            ->where(...$predicates)
            ->execute()
            ->fetchAssociative();
    }

    /**
     * Generate query builders
     * @param string $tablename
     * @return QueryBuilder
     */
    public function getQueryBuilderForTable(string $tablename): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($tablename);
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        return $queryBuilder;
    }

    /**
     * This function is used to get the name of the group that create the page
     * @param $pid
     * @param $queryBuilder
     * @return string|void
     */
    protected function getBEGroup($pid, $queryBuilder)
    {
        if ($pid != null) {
            $predicates = [
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
                ),
            ];

            $res =  $queryBuilder
                ->select('perms_groupid')
                ->from('pages')
                ->where(...$predicates)
                ->execute()
                ->fetchOne();
            if ($res != null) {
                return $this->backendUserGroupRepository->findByUid($res)->getTitle();
            }
            return '';
        }
    }

    /**
     * This function is used to check if tt_content element that refers to the selected page, if is hidden or deleted
     * @param $tableName
     * @param $uid
     * @return bool|int
     * @throws Exception
     */
    protected function checkElementIfIsHidden($tableName, $uid)
    {
        if ($tableName == 'pages' || $tableName == 'tt_content') {
            $queryBuilder = $this->getQueryBuilderForTable($tableName);
            $predicates = [
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                ),
            ];

            $result =  $queryBuilder
                ->select('hidden', 'deleted')
                ->from($tableName)
                ->where(...$predicates)
                ->execute()
                ->fetchAssociative();
            return $result['deleted'] == 1 || $result['hidden'] == 1;
        }
        return 0;
    }

    /**
     * This function is used to get the pagination items
     * @param $data
     * @param int $currentPage
     * @param int $itemsPerPage
     * @return array
     */
    public function getPagination($data, int $currentPage, int $itemsPerPage): array
    {
        // convert data to array
        $items = [];
        foreach ($data as $row) {
            array_push($items, $row);
        }
        $paginator = GeneralUtility::makeInstance(ArrayPaginator::class, $items, $currentPage, $itemsPerPage);
        $pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
        return [
            'paginatedData' => $paginator->getPaginatedItems(),
            'pagination' => $pagination,
            'numberOfPages' => $paginator->getNumberOfPages()
        ];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return int
     */
    public function getNumberOfReferences(): int
    {
        return $this->numberOfReferences;
    }
}

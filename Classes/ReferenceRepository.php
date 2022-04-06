<?php

namespace Qc\QcReferences;


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

    protected int $numberOfReferences = 0;


    public function __construct(){
        $this->backendUserGroupRepository = $backendUserGroupRepository ?? GeneralUtility::makeInstance(BackendUserGroupRepository::class);
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Make reference display
     *
     * @param int|File $ref Filename or uid
     * @return array
     * @throws Exception
     */
    public function getReferences($ref,$showHiddenOrDeletedElement, $paginationPage): array
    {
        $this->modTS = BackendUtility::getPagesTSconfig($ref)['mod.']['qcReferences.'];
        $tsTables = explode(",",$this->modTS['allowedTables']);
        $tsItemsPerPage = (int)$this->modTS['itemsPerPage'];
        $itemsPerPage = $tsItemsPerPage > 0 ?  $tsItemsPerPage : self::DEFAULT_ITEMS_PER_PAGE;

        // Get the allowed tables for elements that refers to the selected page
        $alowedTables = array_map(function($item){
           return str_replace(' ', '', $item);
        }, $tsTables);

        $refLines = [];
        $rows = $this->getReferencesFromDB('pages', $ref);
        foreach ($rows as $row) {
            if(!in_array($row['tablename'], $alowedTables)){
                continue;
            }
            // Check if the element is hidden or deleted
            $ckeck = $this->checkElementIfIsHidden($row['tablename'], $row['recuid']);
            if($showHiddenOrDeletedElement == 0 && $ckeck){
                continue;
            }
            $refLines[] = $this->mapRowToLine($row);
        }
        $this->numberOfReferences = count($refLines);
        return $this->getPagination($refLines,$paginationPage,$itemsPerPage);
    }

    /**
     * @param $row
     * @return array
     */
    public function mapRowToLine($row): array
    {
        // Generate query builder for used tables
        $ttContentQueryBuilder = $this->getQueryBuilderForTable('tt_content');
        $pagesQueryBuilder = $this->getQueryBuilderForTable('pages');
        $lang = $this->getLanguageService();
        $record = BackendUtility::getRecord($row['tablename'], $row['recuid']);
        if ($record) {
            $parentRecord = BackendUtility::getRecord('pages', $record['pid']);
            $parentRecordTitle = is_array($parentRecord)
                ? BackendUtility::getRecordTitle('pages', $parentRecord)
                : '';

            $line['icon'] = $this->iconFactory->getIconForRecord($row['tablename'], $record, Icon::SIZE_SMALL)->render();
            $line['row'] = $row;
            $line['record'] = $record;
            $line['recordTitle'] = BackendUtility::getRecordTitle($row['tablename'], $record, false, true);
            $line['parentRecordTitle'] = $parentRecordTitle;
            $line['title'] = $lang->sL($GLOBALS['TCA'][$row['tablename']]['ctrl']['title']);
            $line['tablename'] = $row['tablename'];

            if( $row['tablename'] == 'tt_content'){
                $line['pid'] = $this->getPid($row['recuid'], $row['tablename'], $ttContentQueryBuilder)['pid'];
                $line['groupName'] = $this->getBEGroup($line['pid'],$pagesQueryBuilder);
                $line['pageLink'] = htmlspecialchars(BackendUtility::viewOnClick($record['pid'], '', BackendUtility::BEgetRootLine($record['pid'])));
            }
            else {
                if( $row['tablename'] == 'pages'){
                    $line['groupName'] = $this->getBEGroup($row['recuid'], $pagesQueryBuilder);
                    $line['pageLink'] = htmlspecialchars(BackendUtility::viewOnClick($row['recuid'], '', BackendUtility::BEgetRootLine($row['recuid'])));
                }
                else{
                    $line['pid'] = '-';
                }
            }

            $line['path'] = BackendUtility::getRecordPath($record['pid'], '', 0, 0);
        } else {
            $line['row'] = $row;
            $line['title'] = $lang->sL($GLOBALS['TCA'][$row['tablename']]['ctrl']['title']) ?: $row['tablename'];
        }
        return $line;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getReferencesFromDB($selectTable, $selectUid) : array {
        $refIndexQueryBuilder = $this->getQueryBuilderForTable('sys_refindex');

        $predicates = [
            $refIndexQueryBuilder->expr()->eq(
                'ref_table',
                $refIndexQueryBuilder->createNamedParameter($selectTable, \PDO::PARAM_STR)
            ),
            $refIndexQueryBuilder->expr()->eq(
                'ref_uid',
                $refIndexQueryBuilder->createNamedParameter($selectUid, \PDO::PARAM_INT)
            ),
            $refIndexQueryBuilder->expr()->eq(
                'deleted',
                $refIndexQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
            )
        ];

        return  $refIndexQueryBuilder
            ->select('*')
            ->from('sys_refindex')
            ->where(...$predicates)
            ->orderBy('tablename', 'DESC')
            ->execute()
            ->fetchAllAssociative();

    }


    /**
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
     * @param $pid
     * @param $queryBuilder
     * @return string|void
     */
    protected function getBEGroup($pid, $queryBuilder){
        if($pid != null) {
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
            if($res != null){
                return $this->backendUserGroupRepository->findByUid($res)->getTitle();
            }
            return '';
        }

    }

    /**
     * @param $tableName
     * @param $uid
     * @return bool|int
     * @throws Exception
     */
    protected function checkElementIfIsHidden($tableName, $uid){
        if($tableName == 'pages' || $tableName == 'tt_content'){
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($tableName);
            $queryBuilder
                ->getRestrictions()
                ->removeAll();
            $predicates = [
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                ),
            ];

           $result =  $queryBuilder
                ->select( 'hidden', 'deleted')
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
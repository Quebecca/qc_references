<?php

declare(strict_types=1);

namespace Qc\QcReferencesTest\Tests\Functional\Reference;

use Doctrine\DBAL\DBALException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qc\QcReferences\ReferenceRepository;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \Qc\QcReferences\ReferenceRepository
 */
class QcReferencesRepositoryFunctionalTest extends FunctionalTestCase
{
    /**
     * @var ReferenceRepository
     */
    protected ReferenceRepository $referenceRepository;

    /**
     * @var array<int,string>
     */
    protected $coreExtensionsToLoad = [
        'backend',
        'beuser',
        'fluid',
        'info',
        'install',
        'core'
    ];
    /**
     * @var array<int, non-empty-string>
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/qc_references_cicd'];

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws DBALException|Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->getContainer()->get(LanguageServiceFactory::class)->create('default');
        /** @var Typo3Version $versionInformation */
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() >= 11) {
            $this->referenceRepository = $this->getContainer()->get(ReferenceRepository::class);
        } else {
            /** @var ObjectManager $objectManager */
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $this->referenceRepository = $objectManager->get(ReferenceRepository::class);
        }
    }

    /**
     * @test
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function findByPageUidExisitingReferences(): void
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/Reference/Reference.xml');
        $row = $this->referenceRepository->getReferences(3, 0, 1)['paginatedData'][0];
        $recordTitle = $row['recordTitle'];
        $tablename = $row['tablename'];
        $path = $row['path'];
        $groupName = $row['groupName'];
        $pid = $row['pid'];
        self::assertNotNull($row);
        self::assertSame('my header', $recordTitle);
        self::assertSame('tt_content', $tablename);
        self::assertSame('/Page 2/', $path);
        self::assertSame('Group 1', $groupName);
        self::assertSame(2, $pid);
    }
}

<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Command;

use Elasticsearch\Common\Exceptions\BadMethodCallException;
use Generator;
use kr0lik\ElasticSearchReindex\Command\CreateIndexElasticSearchCommand;
use kr0lik\ElasticSearchReindex\Dto\IndexInfo;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;
use kr0lik\ElasticSearchReindex\Exception\IndicesWrongCongigurationException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\IndexGetter;
use kr0lik\ElasticSearchReindex\Service\IndicesDataGetter;
use kr0lik\ElasticSearchReindex\Service\Reindexer;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class CreateIndexElasticSearchCommandTest extends TestCase
{
    private const OK_INDEX1 = 'test-index';
    private const OK_INDEX2 = 'other-test-index';

    private CommandTester $commandTester;
    /**
     * @var IndexGetter|MockObject
     */
    private $getter;
    /**
     * @var ElasticSearchService|MockObject
     */
    private $service;
    /**
     * @var Reindexer|MockObject
     */
    private $reindexer;

    /**
     * @throws BadMethodCallException
     * @throws CommandNotFoundException
     * @throws IndicesWrongCongigurationException
     * @throws LogicException
     */
    protected function setUp(): void
    {
        $this->getter = $this->createMock(IndexGetter::class);
        $this->service = $this->createMock(ElasticSearchService::class);
        $this->reindexer = $this->createMock(Reindexer::class);
        $indicesDataGetter = new IndicesDataGetter([
            [
                'name' => self::OK_INDEX1,
                'body' => [
                    'key1' => 'val1',
                ],
            ],
            [
                'name' => self::OK_INDEX2,
                'body' => [
                    'key2' => 'val2',
                ],
            ],
        ]);

        $application = new Application();
        $application->add(new CreateIndexElasticSearchCommand(
            $this->getter,
            $this->service,
            $this->reindexer,
            $indicesDataGetter
        ));
        $command = $application->find('elastic-search:create-index');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandSuccessWithReindex(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willReturn('test-index')
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('test-index-v1')
        ;
        $this->service->expects(self::exactly(3))
            ->method('getIndexInfo')
            ->willReturn(
                new IndexInfo('test-index', 0, 100),
                new IndexInfo('test-index-v1', 0, 100),
                new IndexInfo('test-index-v1', 0, 100)
            )
        ;
        $this->reindexer->expects(self::once())
            ->method('reindex')
            ->willReturnCallback(static function (): Generator {
                yield 100;
            })
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::SUCCESS, $this->commandTester->getStatusCode());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandSuccessWithOtherIndexReindex(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willReturn('other-test-index')
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('other-test-index')
        ;
        $this->service->expects(self::exactly(3))
            ->method('getIndexInfo')
            ->willReturn(
                new IndexInfo('other-test-index', 0, 100),
                new IndexInfo('other-test-index', 0, 100),
                new IndexInfo('other-test-index', 0, 100)
            )
        ;
        $this->reindexer->expects(self::once())
            ->method('reindex')
            ->willReturnCallback(static function (): Generator {
                yield 100;
            })
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX2]);

        self::assertEquals(CreateIndexElasticSearchCommand::SUCCESS, $this->commandTester->getStatusCode());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandSuccessWithoutReindex(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willThrowException(new IndexNotExistException())
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('test-index-v1')
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::SUCCESS, $this->commandTester->getStatusCode());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandFailureOnCreateIndex(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willThrowException(new IndexNotExistException())
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willThrowException(new CreateIndexException())
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandFailureReindexInvalidResponse(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willReturn('test-index')
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('test-index-v1')
        ;
        $this->service->expects(self::exactly(2))
            ->method('getIndexInfo')
            ->willReturn(
                new IndexInfo('test-index', 0, 100),
                new IndexInfo('test-index-v1', 0, 100)
            )
        ;
        $this->reindexer->expects(self::once())
            ->method('reindex')
            ->willThrowException(new InvalidResponseBodyException())
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandFailureReindexTaskNotFound(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willReturn('test-index')
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('test-index-v1')
        ;
        $this->service->expects(self::exactly(2))
            ->method('getIndexInfo')
            ->willReturn(
                new IndexInfo('test-index', 0, 100),
                new IndexInfo('test-index-v1', 0, 100),
            )
        ;
        $this->reindexer->expects(self::once())
            ->method('reindex')
            ->willThrowException(new TaskNotFoundException())
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCommandWithNotExistsIndex(): void
    {
        $this->getter->expects(self::never())
            ->method('getOldIndexName')
        ;
        $this->getter->expects(self::never())
            ->method('getNewIndexName')
        ;
        $this->service->expects(self::never())
            ->method('getIndexInfo')
        ;
        $this->reindexer->expects(self::never())
            ->method('reindex')
        ;

        $this->commandTester->execute(['index-name' => 'not-exists-index']);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
        self::assertEquals("Index not-exists-index not configured.\n", $this->commandTester->getDisplay());
    }
}

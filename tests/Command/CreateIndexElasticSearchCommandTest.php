<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Command;

use Elasticsearch\Common\Exceptions\BadMethodCallException;
use Generator;
use kr0lik\ElasticSearchReindex\Command\CreateIndexElasticSearchCommand;
use kr0lik\ElasticSearchReindex\Dto\IndexInfoDto;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotConfiguredException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;
use kr0lik\ElasticSearchReindex\Exception\IndicesWrongCongigurationException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\IndexCreator;
use kr0lik\ElasticSearchReindex\Service\IndexNameGetter;
use kr0lik\ElasticSearchReindex\Service\Reindexer;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 * @coversNothing
 */
class CreateIndexElasticSearchCommandTest extends TestCase
{
    private const OK_INDEX1 = 'test-index';
    private const OK_INDEX2 = 'other-test-index';

    /**
     * @var CommandTester
     */
    private $commandTester;
    /**
     * @var IndexNameGetter|MockObject
     */
    private $getter;
    /**
     * @var IndexCreator|MockObject
     */
    private $creator;
    /**
     * @var ElasticSearchService|MockObject
     */
    private $service;
    /**
     * @var Reindexer|MockObject
     */
    private $reindexer;

    /**
     * @throws CommandNotFoundException
     * @throws LogicException
     * @throws RuntimeException
     * @throws BadMethodCallException
     * @throws IndicesWrongCongigurationException
     */
    protected function setUp(): void
    {
        $this->getter = $this->createMock(IndexNameGetter::class);
        $this->creator = $this->createMock(IndexCreator::class);
        $this->service = $this->createMock(ElasticSearchService::class);
        $this->reindexer = $this->createMock(Reindexer::class);

        $application = new Application();
        $application->add(new CreateIndexElasticSearchCommand(
            $this->getter,
            $this->creator,
            $this->service,
            $this->reindexer
        ));
        $command = $application->find('elastic-search:create-index');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
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
        $this->service->expects(self::exactly(2))
            ->method('getIndexInfo')
            ->willReturn(new IndexInfoDto(0, 100))
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
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
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
        $this->service->expects(self::exactly(2))
            ->method('getIndexInfo')
            ->willReturn(new IndexInfoDto(0, 100))
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
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
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
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testCommandFailureOnCreateIndex(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willThrowException(new IndexNotExistException())
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('some-new-index')
        ;
        $this->creator->expects(self::once())
            ->method('createNewIndex')
            ->willThrowException(new CreateIndexException())
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
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
            ->willReturn(new IndexInfoDto(0, 100))
        ;
        $this->reindexer->expects(self::once())
            ->method('reindex')
            ->willThrowException(new InvalidResponseBodyException())
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
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
            ->willReturn(new IndexInfoDto(0, 100))
        ;
        $this->reindexer->expects(self::once())
            ->method('reindex')
            ->willThrowException(new TaskNotFoundException())
        ;

        $this->commandTester->execute(['index-name' => self::OK_INDEX1]);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testCommandFailureNoIndexBody(): void
    {
        $this->getter->expects(self::once())
            ->method('getOldIndexName')
            ->willReturn('test-index')
        ;
        $this->getter->expects(self::once())
            ->method('getNewIndexName')
            ->willReturn('test-index-v1')
        ;
        $this->creator->expects(self::once())
            ->method('createNewIndex')
            ->willThrowException(new IndexNotConfiguredException())
        ;

        $this->commandTester->execute(['index-name' => 'not-exists-index']);

        self::assertEquals(CreateIndexElasticSearchCommand::FAILURE, $this->commandTester->getStatusCode());
    }
}

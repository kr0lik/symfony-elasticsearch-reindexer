<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadMethodCallException;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Namespaces\IndicesNamespace;
use Elasticsearch\Namespaces\TasksNamespace;
use kr0lik\ElasticSearchReindex\Dto\IndexInfoDto;
use kr0lik\ElasticSearchReindex\Dto\TaskInfoDto;
use kr0lik\ElasticSearchReindex\Exception\CreateAliasException;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\DeleteIndexException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
 * @coversNothing
 */
class ElasticSearchServiceTest extends TestCase
{
    /**
     * @var IndicesNamespace|MockObject
     */
    private $indices;
    /**
     * @var TasksNamespace|MockObject
     */
    private $tasks;
    /**
     * @var Client|MockObject
     */
    private $esClient;
    /**
     * @var ElasticSearchService
     */
    private $service;

    /**
     * @throws RuntimeException
     * @throws BadMethodCallException
     */
    public function setUp(): void
    {
        $this->indices = $this->createMock(IndicesNamespace::class);

        $this->tasks = $this->createMock(TasksNamespace::class);

        $this->esClient = $this->createMock(Client::class);
        $this->esClient->expects(self::any())
            ->method('indices')
            ->willReturn($this->indices)
        ;
        $this->esClient->expects(self::any())
            ->method('tasks')
            ->willReturn($this->tasks)
        ;

        $this->service = new ElasticSearchService($this->esClient);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testIsIndexExistTrue(): void
    {
        $this->indices->expects(self::once())
            ->method('exists')
            ->willReturn(true)
        ;

        $result = $this->service->isIndexExist('some-index');

        self::assertEquals(true, $result);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testIsIndexExistFalse(): void
    {
        $this->indices->expects(self::once())
            ->method('exists')
            ->willReturn(false)
        ;

        $result = $this->service->isIndexExist('some-index');

        self::assertEquals(false, $result);
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws BadMethodCallException
     */
    public function testReindexSuccess(): void
    {
        $this->esClient->expects(self::once())
            ->method('reindex')
            ->willReturn(['task' => 'task-id'])
        ;

        $result = $this->service->reindex('old-index', 'new-index', time());

        self::assertEquals('task-id', $result);
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws RuntimeException
     * @throws BadMethodCallException
     */
    public function testReindexFailure(): void
    {
        $this->esClient->expects(self::once())
            ->method('reindex')
            ->willReturn([])
        ;

        $this->expectException(InvalidResponseBodyException::class);

        $this->service->reindex('old-index', 'new-index', time());
    }

    /**
     * @throws CreateAliasException
     * @throws RuntimeException
     */
    public function testCreateAliasSuccess(): void
    {
        $this->indices->expects(self::once())
            ->method('updateAliases')
            ->willReturn(['acknowledged' => true])
        ;

        $this->doesNotPerformAssertions();

        $this->service->createAlias('alias', 'new-index');
    }

    /**
     * @throws CreateAliasException
     * @throws RuntimeException
     */
    public function testCreateAliasFailure(): void
    {
        $this->indices->expects(self::once())
            ->method('updateAliases')
            ->willReturn(['acknowledged' => false])
        ;

        $this->expectException(CreateAliasException::class);

        $this->service->createAlias('alias', 'new-index');
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testIsAliasExistTrue(): void
    {
        $this->indices->expects(self::once())
            ->method('existsAlias')
            ->willReturn(true)
        ;

        $result = $this->service->isAliasExist('alias');

        self::assertEquals(true, $result);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testIsAliasExistFalse(): void
    {
        $this->indices->expects(self::once())
            ->method('existsAlias')
            ->willReturn(false)
        ;

        $result = $this->service->isAliasExist('alias');

        self::assertEquals(false, $result);
    }

    /**
     * @throws CreateIndexException
     * @throws RuntimeException
     */
    public function testCreateIndexSuccess(): void
    {
        $this->indices->expects(self::once())
            ->method('create')
            ->willReturn(['acknowledged' => true])
        ;

        $this->doesNotPerformAssertions();

        $this->service->createIndex('some-index', []);
    }

    /**
     * @throws CreateIndexException
     * @throws RuntimeException
     */
    public function testCreateIndexFailure(): void
    {
        $this->indices->expects(self::once())
            ->method('create')
            ->willReturn(['acknowledged' => false])
        ;

        $this->expectException(CreateIndexException::class);

        $this->service->createIndex('alias', []);
    }

    /**
     * @throws DeleteIndexException
     * @throws RuntimeException
     */
    public function testDeleteIndexSuccess(): void
    {
        $this->indices->expects(self::once())
            ->method('delete')
            ->willReturn(['acknowledged' => true])
        ;

        $this->doesNotPerformAssertions();

        $this->service->deleteIndex('some-index');
    }

    /**
     * @throws DeleteIndexException
     * @throws RuntimeException
     */
    public function testDeleteIndexFailure(): void
    {
        $this->indices->expects(self::once())
            ->method('delete')
            ->willReturn(['acknowledged' => false])
        ;

        $this->expectException(DeleteIndexException::class);

        $this->service->deleteIndex('some-index');
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testGetIndexName(): void
    {
        $this->indices->expects(self::once())
            ->method('get')
            ->willReturn(['some-index' => []])
        ;

        $result = $this->service->getIndexName('some-index-v1');

        self::assertEquals('some-index', $result);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testGetIndexDocs(): void
    {
        $this->indices->expects(self::once())
            ->method('stats')
            ->willReturn([
                'indices' => [
                    'some-index' => [
                        'total' => [
                            'docs' => [
                                'count' => 100,
                            ],
                        ],
                    ],
                ],
            ])
        ;

        $result = $this->service->getIndexDocs('some-index');

        self::assertEquals(100, $result);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws BadMethodCallException
     */
    public function testGetIndexInfo(): void
    {
        $this->esClient->expects(self::once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'total' => 100,
                    'hits' => [
                        [
                            '_source' => [
                                'meta' => [
                                    'cas' => 1604596076,
                                ],
                            ],
                        ],
                    ],
                ],
            ])
        ;

        $result = $this->service->getIndexInfo('some-index');

        self::assertEquals(new IndexInfoDto(1604596076, 100), $result);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws BadMethodCallException
     */
    public function testGetIndexInfoOnError(): void
    {
        $this->esClient->expects(self::once())
            ->method('search')
            ->willThrowException(new BadRequest400Exception())
        ;

        $result = $this->service->getIndexInfo('some-index');

        self::assertEquals(new IndexInfoDto(0, 0), $result);
    }

    /**
     * @throws TaskNotFoundException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function testGetTaskInfoSuccess(): void
    {
        $this->tasks->expects(self::once())
            ->method('get')
            ->willReturn([
                'task' => [
                    'status' => [
                        'total' => 100,
                        'created' => 50,
                        'updated' => 40,
                        'deleted' => 10,
                    ],
                ],
                'completed' => true,
            ])
        ;

        $result = $this->service->getTaskInfo('some-index');

        self::assertEquals(new TaskInfoDto(true, 100, 100), $result);
    }

    /**
     * @throws TaskNotFoundException
     * @throws RuntimeException
     */
    public function testGetTaskInfoFailure(): void
    {
        $this->tasks->expects(self::once())
            ->method('get')
            ->willThrowException(new Missing404Exception())
        ;

        $this->expectException(TaskNotFoundException::class);

        $this->service->getTaskInfo('some-index');
    }
}

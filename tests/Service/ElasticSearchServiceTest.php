<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadMethodCallException;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Namespaces\IndicesNamespace;
use Elasticsearch\Namespaces\TasksNamespace;
use kr0lik\ElasticSearchReindex\Dto\IndexInfo;
use kr0lik\ElasticSearchReindex\Dto\TaskInfo;
use kr0lik\ElasticSearchReindex\Exception\CreateAliasException;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\DeleteIndexException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
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
    private ElasticSearchService $service;

    /**
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
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
     * @throws BadMethodCallException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidResponseBodyException
     */
    public function testReindexSuccess(): void
    {
        $this->esClient->expects(self::once())
            ->method('reindex')
            ->willReturn(['task' => 'task-id'])
        ;

        $result = $this->service->reindex('old-index', 'new-index', time(), []);

        self::assertEquals('task-id', $result);
    }

    /**
     * @throws BadMethodCallException
     * @throws InvalidResponseBodyException
     */
    public function testReindexFailure(): void
    {
        $this->esClient->expects(self::once())
            ->method('reindex')
            ->willReturn([])
        ;

        $this->expectException(InvalidResponseBodyException::class);

        $this->service->reindex('old-index', 'new-index', time(), []);
    }

    /**
     * @throws CreateAliasException
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
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
     * @throws BadMethodCallException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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

        self::assertEquals(new IndexInfo('some-index', 1604596076, 100), $result);
    }

    /**
     * @throws BadMethodCallException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testGetIndexInfoOnError(): void
    {
        $this->esClient->expects(self::once())
            ->method('search')
            ->willThrowException(new BadRequest400Exception())
        ;

        $result = $this->service->getIndexInfo('some-index');

        self::assertEquals(new IndexInfo('some-index', 0, 0), $result);
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws TaskNotFoundException
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

        self::assertEquals(new TaskInfo(true, 100, 100), $result);
    }

    /**
     * @throws TaskNotFoundException
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

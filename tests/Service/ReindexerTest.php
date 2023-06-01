<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use kr0lik\ElasticSearchReindex\Dto\IndexData;
use kr0lik\ElasticSearchReindex\Dto\IndexInfo;
use kr0lik\ElasticSearchReindex\Dto\TaskInfo;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\Reindexer;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
 */
class ReindexerTest extends TestCase
{
    /**
     * @var ElasticSearchService|MockObject
     */
    private $service;
    private Reindexer $reindexer;

    public function setUp(): void
    {
        $this->service = $this->createMock(ElasticSearchService::class);

        $this->reindexer = new Reindexer($this->service);
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     *
     * @dataProvider getReindexData
     */
    public function testReindexSuccess(int $oldIndexDocs, int $middleDocs): void
    {
        $this->service->expects(self::once())
            ->method('reindex')
            ->willReturn('some-task')
        ;
        $this->service->expects(self::any())
            ->method('getTaskInfo')
            ->willReturnOnConsecutiveCalls(
                new TaskInfo(
                    false,
                    $oldIndexDocs,
                    $middleDocs
                ),
                new TaskInfo(
                    true,
                    $oldIndexDocs,
                    $oldIndexDocs
                ),
            )
        ;

        $oldIndexInfo = new IndexInfo('old-index', 0, $oldIndexDocs);
        $newIndexInfo = new IndexInfo('new-index', 0, $oldIndexDocs);
        $indexData = new IndexData('old-index', []);

        $chekTime = time();

        foreach ($this->reindexer->reindex(
            $oldIndexInfo,
            $newIndexInfo,
            $indexData,
            1 * 1000 * 1000
        ) as $i => $processed) {
            ++$chekTime;

            $isFirstIteration = 0 === $i;
            $expected = $isFirstIteration ? $middleDocs : $oldIndexDocs;

            self::assertEquals($expected, $processed);
        }

        self::assertEquals($chekTime, time());
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     */
    public function testReindexInvalidResponse(): void
    {
        $this->service->expects(self::once())
            ->method('reindex')
            ->willThrowException(new InvalidResponseBodyException())
        ;

        $this->expectException(InvalidResponseBodyException::class);

        $oldIndexInfo = new IndexInfo('old-index', 0, 100);
        $newIndexInfo = new IndexInfo('new-index', 0, 0);
        $indexData = new IndexData('old-index', []);

        $this->reindexer->reindex(
            $oldIndexInfo,
            $newIndexInfo,
            $indexData,
            1 * 1000 * 1000
        )->next();
    }

    /**
     * @dataProvider getReindexData
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     */
    public function testReindexSuccessWithTaskNotFound(int $oldIndexDocs): void
    {
        $this->service->expects(self::once())
            ->method('reindex')
            ->willReturn('some-task')
        ;
        $this->service->expects(self::once())
            ->method('getTaskInfo')
            ->willThrowException(new TaskNotFoundException())
        ;
        $this->service->expects(self::once())
            ->method('getIndexDocs')
            ->willReturn($oldIndexDocs)
        ;

        $oldIndexInfo = new IndexInfo('old-index', 0, $oldIndexDocs);
        $newIndexInfo = new IndexInfo('new-index', 0, $oldIndexDocs);
        $indexData = new IndexData('old-index', []);

        $chekTime = time();

        foreach ($this->reindexer->reindex(
            $oldIndexInfo,
            $newIndexInfo,
            $indexData,
            1 * 1000 * 1000
        ) as $i => $processed) {
            $chekTime += 3;

            self::assertEquals($oldIndexDocs, $processed);
        }

        self::assertEquals($chekTime, time());
    }

    /**
     * @dataProvider getReindexData
     *
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     */
    public function testReindexFailureWithTaskNotFound(int $oldIndexDocs, int $middleDocs): void
    {
        $this->service->expects(self::once())
            ->method('reindex')
            ->willReturn('some-task')
        ;
        $this->service->expects(self::once())
            ->method('getTaskInfo')
            ->willThrowException(new TaskNotFoundException())
        ;
        $this->service->expects(self::once())
            ->method('getIndexDocs')
            ->willReturn($middleDocs)
        ;

        $this->expectException(TaskNotFoundException::class);

        $oldIndexInfo = new IndexInfo('old-index', 0, $oldIndexDocs);
        $newIndexInfo = new IndexInfo('new-index', 0, $oldIndexDocs);
        $indexData = new IndexData('old-index', []);

        $this->reindexer->reindex(
            $oldIndexInfo,
            $newIndexInfo,
            $indexData,
            1 * 1000 * 1000
        )->next();
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     *
     * @dataProvider getIsNeedReindexData
     */
    public function testIsNeedReindex(bool $isNeedReindex, int $oldTotalDocs, int $newTotalDocs): void
    {
        $this->service->expects(self::any())
            ->method('getIndexInfo')
            ->willReturnMap(
                [
                    ['old-index', new IndexInfo('old-index', 0, $oldTotalDocs)],
                    ['new-index', new IndexInfo('new-index', 0, $newTotalDocs)],
                ]
            )
        ;

        $result = $this->reindexer->isNeedReindex('old-index', 'new-index');

        self::assertEquals($isNeedReindex, $result);
    }

    /**
     * @return array<mixed>
     */
    public function getReindexData(): array
    {
        return [
            [
                'oldIndexDocs' => 100,
                'middleDocs' => 54,
            ],
            [
                'oldIndexDocs' => 10,
                'middleDocs' => 7,
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function getIsNeedReindexData(): array
    {
        return [
            [
                'isNeedReindex' => true,
                'oldTotalDocs' => 200,
                'newTotalDocs' => 100,
            ],
            [
                'isNeedReindex' => false,
                'oldTotalDocs' => 100,
                'newTotalDocs' => 100,
            ],
        ];
    }
}

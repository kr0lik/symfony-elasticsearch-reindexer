<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use kr0lik\ElasticSearchReindex\Dto\IndexInfoDto;
use kr0lik\ElasticSearchReindex\Dto\TaskInfoDto;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\Reindexer;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
 * @coversNothing
 */
class ReindexerTest extends TestCase
{
    /**
     * @var ElasticSearchService|MockObject
     */
    private $service;
    /**
     * @var Reindexer
     */
    private $reindexer;

    public function setUp(): void
    {
        $this->service = $this->createMock(ElasticSearchService::class);

        $this->reindexer = new Reindexer($this->service);
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     * @throws RuntimeException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
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
                new TaskInfoDto(
                    false,
                    $oldIndexDocs,
                    $middleDocs
                ),
                new TaskInfoDto(
                    true,
                    $oldIndexDocs,
                    $oldIndexDocs
                ),
            )
        ;

        $chekTime = time();
        foreach ($this->reindexer->reindex(
            'old-index',
            'new-index',
            $oldIndexDocs,
            0,
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
     * @throws RuntimeException
     */
    public function testReindexInvalidResponse(): void
    {
        $this->service->expects(self::once())
            ->method('reindex')
            ->willThrowException(new InvalidResponseBodyException())
        ;

        $this->expectException(InvalidResponseBodyException::class);

        $this->reindexer->reindex(
            'old-index',
            'new-index',
            100,
            0,
            1 * 1000 * 1000
        )->next();
    }

    /**
     * @dataProvider getReindexData
     *
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
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

        $chekTime = time();
        foreach ($this->reindexer->reindex(
            'old-index',
            'new-index',
            $oldIndexDocs,
            0,
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
     * @throws RuntimeException
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

        $this->reindexer->reindex(
            'old-index',
            'new-index',
            $oldIndexDocs,
            0,
            1 * 1000 * 1000
        )->next();
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     * @dataProvider getIsNeedReindexData
     */
    public function testIsNeedReindex(bool $isNeedReindex, int $oldDocs, int $newDocs, int $oldTotalDocs, int $newTotalDocs): void
    {
        $this->service->expects(self::any())
            ->method('getIndexInfo')
            ->willReturnMap(
                [
                    ['old-index', new IndexInfoDto(time(), $oldDocs)],
                    ['new-index', new IndexInfoDto(time(), $newDocs)],
                ]
            )
        ;

        foreach (range(1, 11) as $i) {
            $result = $this->reindexer->isNeedReindex(
                'old-index',
                'new-index',
                $oldTotalDocs,
                $newTotalDocs
            );

            $isLastIteration = 11 === $i;
            $expected = $isLastIteration ? $isNeedReindex : false;

            self::assertEquals($expected, $result);
        }
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
                'oldDocs' => 200,
                'newDocs' => 150,
                'oldTotalDocs' => 100,
                'newTotalDocs' => 100,
            ],
            [
                'isNeedReindex' => false,
                'oldDocs' => 100,
                'newDocs' => 100,
                'oldTotalDocs' => 100,
                'newTotalDocs' => 100,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use kr0lik\ElasticSearchReindex\Dto\IndexData;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\IndexGetter;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
 */
class IndexGetterTest extends TestCase
{
    /**
     * @var ElasticSearchService|MockObject
     */
    private $service;
    private IndexGetter $getter;

    public function setUp(): void
    {
        $this->service = $this->createMock(ElasticSearchService::class);

        $this->getter = new IndexGetter($this->service);
    }

    /**
     * @dataProvider getNewIndexData
     *
     * @throws CreateIndexException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testGetNewIndexName(string $expectedIndex, string $actualIndex): void
    {
        $this->service->expects(self::once())
            ->method('isIndexExist')
            ->willReturnCallback(static function (string $index) use ($expectedIndex): bool {
                return $index === $expectedIndex;
            })
        ;

        $indexData = new IndexData($actualIndex, []);
        $result = $this->getter->getNewIndexName($expectedIndex, $indexData);

        self::assertEquals($actualIndex, $result);
    }

    /**
     * @throws CreateIndexException
     */
    public function testGetNewIndexNameFailure(): void
    {
        $this->service->expects(self::once())
            ->method('isIndexExist')
            ->willReturn(false)
        ;
        $this->service->expects(self::once())
            ->method('createIndex')
            ->willThrowException(new CreateIndexException())
        ;

        $this->expectException(CreateIndexException::class);

        $indexData = new IndexData('some-index', []);
        $this->getter->getNewIndexName('some-index', $indexData);
    }

    /**
     * @dataProvider getOldIndexData
     *
     * @throws ExpectationFailedException
     * @throws IndexNotExistException
     * @throws InvalidArgumentException
     */
    public function testGetOldIndexNameWithAlias(string $expectedIndex, string $actualIndex): void
    {
        $this->service->expects(self::once())
            ->method('isAliasExist')
            ->willReturn(true)
        ;
        $this->service->expects(self::once())
            ->method('getIndexName')
            ->willReturn($actualIndex)
        ;
        $this->service->expects(self::once())
            ->method('isIndexExist')
            ->willReturn(true)
        ;

        $result = $this->getter->getOldIndexName($expectedIndex);

        self::assertEquals($actualIndex, $result);
    }

    /**
     * @dataProvider getOldIndexData
     *
     * @throws ExpectationFailedException
     * @throws IndexNotExistException
     * @throws InvalidArgumentException
     */
    public function testGetOldIndexNameWithoutAlias(string $expectedIndex): void
    {
        $this->service->expects(self::once())
            ->method('isAliasExist')
            ->willReturn(false)
        ;
        $this->service->expects(self::once())
            ->method('isIndexExist')
            ->willReturn(true)
        ;

        $result = $this->getter->getOldIndexName($expectedIndex);

        self::assertEquals($expectedIndex, $result);
    }

    /**
     * @throws IndexNotExistException
     */
    public function testGetOldIndexNameFailure(): void
    {
        $this->service->expects(self::once())
            ->method('isAliasExist')
            ->willReturn(false)
        ;
        $this->service->expects(self::once())
            ->method('isIndexExist')
            ->willThrowException(new IndexNotExistException())
        ;

        $this->expectException(IndexNotExistException::class);

        $this->getter->getOldIndexName('some-index');
    }

    /**
     * @return array<mixed>
     */
    public function getNewIndexData(): array
    {
        return [
            [
                'expectedIndex' => 'some-index',
                'actualIndex' => 'some-index-v1',
            ],
            [
                'expectedIndex' => 'some-index-v1',
                'actualIndex' => 'some-index-v2',
            ],
            [
                'expectedIndex' => 'some-index-v2',
                'actualIndex' => 'some-index-v3',
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function getOldIndexData(): array
    {
        return [
            [
                'expectedIndex' => 'some-index',
                'actualIndex' => 'some-index-v1',
            ],
            [
                'expectedIndex' => 'some-index',
                'actualIndex' => 'some-index',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotConfiguredException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\IndexCreator;
use kr0lik\ElasticSearchReindex\Service\IndicesDataGetter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class IndexCreatorTest extends TestCase
{
    /**
     * @var ElasticSearchService|MockObject
     */
    private $service;
    /**
     * @var IndicesDataGetter|MockObject
     */
    private $indicesDataGetter;
    /**
     * @var IndexCreator
     */
    private $creator;

    public function setUp(): void
    {
        $this->service = $this->createMock(ElasticSearchService::class);
        $this->indicesDataGetter = $this->createMock(IndicesDataGetter::class);

        $this->creator = new IndexCreator($this->service, $this->indicesDataGetter);
    }

    /**
     * @throws CreateIndexException
     * @throws RuntimeException
     */
    public function testCreateNewIndexSuccess(): void
    {
        $this->indicesDataGetter->expects(self::once())
            ->method('getIndexBody')
            ->willReturnCallback(static function (string $indexName): array {
                if ('some-index' === $indexName) {
                    return [];
                }

                throw new IndexNotConfiguredException();
            })
        ;

        $this->doesNotPerformAssertions();

        $this->creator->createNewIndex('some-index-v10');
    }

    /**
     * @throws CreateIndexException
     * @throws RuntimeException
     */
    public function testCreateNewIndexFailure(): void
    {
        $this->service->expects(self::once())
            ->method('createIndex')
            ->willThrowException(new CreateIndexException())
        ;

        $this->expectException(CreateIndexException::class);

        $this->creator->createNewIndex('some-index');
    }
}

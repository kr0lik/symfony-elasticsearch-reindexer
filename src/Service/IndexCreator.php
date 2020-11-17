<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Helper\IndexNameHelper;

class IndexCreator
{
    /**
     * @var ElasticSearchService
     */
    private $service;

    /**
     * @var IndicesDataGetter
     */
    private $indicesDataGetter;

    public function __construct(ElasticSearchService $service, IndicesDataGetter $indicesDataGetter)
    {
        $this->service = $service;
        $this->indicesDataGetter = $indicesDataGetter;
    }

    /**
     * @throws CreateIndexException
     */
    public function createNewIndex(string $newIndexName): void
    {
        $baseIndexName = IndexNameHelper::getBaseIndexName($newIndexName);

        $indexBody = $this->indicesDataGetter->getIndexBody($baseIndexName);

        $this->service->createIndex($newIndexName, $indexBody);
    }
}

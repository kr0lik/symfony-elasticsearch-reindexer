<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;
use kr0lik\ElasticSearchReindex\Helper\IndexNameHelper;

class IndexNameGetter
{
    private const MAX_NEW_INDEX_NAME_CHECK_ITERATION = 100;

    /**
     * @var ElasticSearchService
     */
    private $service;

    public function __construct(ElasticSearchService $service)
    {
        $this->service = $service;
    }

    /**
     * @throws IndexNotExistException
     */
    public function getOldIndexName(string $indexName): string
    {
        if ($this->service->isAliasExist($indexName)) {
            $oldIndexName = $this->service->getIndexName($indexName);
        } else {
            $oldIndexName = $indexName;
        }

        if (!$this->service->isIndexExist($oldIndexName)) {
            throw new IndexNotExistException("Index `{$oldIndexName}` not exists");
        }

        return $oldIndexName;
    }

    public function getNewIndexName(string $oldIndexName): string
    {
        $version = $this->getVersion($oldIndexName);

        do {
            $newIndexName = $this->getIndexNameWithVersion($oldIndexName, ++$version);
        } while ($this->service->isIndexExist($newIndexName));

        return $newIndexName;
    }

    private function getVersion(string $indexName): int
    {
        if ((bool) preg_match('#-v(?<version>\d+)$#i', $indexName, $matches)) {
            return (int) $matches['version'];
        }

        return 0;
    }

    private function getIndexNameWithVersion(string $indexName, int $version = 1): string
    {
        $baseIndexName = IndexNameHelper::getBaseIndexName($indexName);

        return "{$baseIndexName}-v{$version}";
    }
}

<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use kr0lik\ElasticSearchReindex\Dto\IndexData;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;

class IndexGetter
{
    private ElasticSearchService $service;

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
            $oldIndex = $this->service->getIndexName($indexName);
        } else {
            $oldIndex = $indexName;
        }

        if (!$this->service->isIndexExist($oldIndex)) {
            throw new IndexNotExistException("Index `{$oldIndex}` not exists");
        }

        return $oldIndex;
    }

    /**
     * @throws CreateIndexException
     */
    public function getNewIndexName(string $oldIndex, IndexData $indexData): string
    {
        $version = $this->getVersion($oldIndex);

        do {
            $newIndex = $this->getIndexNameWithVersion($oldIndex, ++$version);
        } while ($this->service->isIndexExist($newIndex));

        $data = $indexData->getBody();
        $data['settings']['index']['number_of_replicas'] = 0;

        $this->service->createIndex($newIndex, $data);

        return $newIndex;
    }

    private function getVersion(string $index): int
    {
        if ((bool) preg_match('#-v(?<version>\d+)$#i', $index, $matches)) {
            return (int) $matches['version'];
        }

        return 0;
    }

    private function getIndexNameWithVersion(string $index, int $version = 1): string
    {
        $baseIndex = preg_replace('#(-v\d+)$#i', '', $index);

        return "{$baseIndex}-v{$version}";
    }
}

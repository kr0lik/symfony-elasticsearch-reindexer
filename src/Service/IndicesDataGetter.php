<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use kr0lik\ElasticSearchReindex\Exception\IndexNotConfiguredException;
use kr0lik\ElasticSearchReindex\Exception\IndicesWrongCongigurationException;

class IndicesDataGetter
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private $indicesData;

    /**
     * @param array<int, array<string, mixed>> $indicesData
     *
     * @throws IndicesWrongCongigurationException
     */
    public function __construct(array $indicesData)
    {
        $this->checkIndicesData($indicesData);
        $this->indicesData = $indicesData;
    }

    /**
     * @throws IndexNotConfiguredException
     *
     * @return array<string, mixed>
     */
    public function getIndexBody(string $indexName): array
    {
        foreach ($this->indicesData as $indexData) {
            if ($indexData['name'] === $indexName) {
                return $indexData['create_body'];
            }
        }

        throw new IndexNotConfiguredException(sprintf('Index %s not configured.', $indexName));
    }

    /**
     * @param array<int, array<string, mixed>> $indicesData
     *
     * @throws IndicesWrongCongigurationException
     */
    private function checkIndicesData(array $indicesData): void
    {
        if ([] === $indicesData) {
            throw new IndicesWrongCongigurationException('Configure at least one index in indices section.');
        }

        foreach ($indicesData as $position => $indexData) {
            if (!is_array($indexData)) {
                $message = sprintf('Wrong configured index data at position %s.', $position);

                throw new IndicesWrongCongigurationException($message);
            }

            if (!array_key_exists('name', $indexData)) {
                $message = sprintf('Wrong configured index name at position %s.', $position);

                throw new IndicesWrongCongigurationException($message);
            }

            $isValidBody = array_key_exists('create_body', $indexData) && is_array($indexData['create_body']);

            if (!$isValidBody) {
                $message = sprintf('Wrong configured index create_body at position %s.', $position);

                throw new IndicesWrongCongigurationException($message);
            }
        }
    }
}

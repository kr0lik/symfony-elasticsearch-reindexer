<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use kr0lik\ElasticSearchReindex\Dto\IndexData;
use kr0lik\ElasticSearchReindex\Exception\IndexNotConfiguredException;
use kr0lik\ElasticSearchReindex\Exception\IndicesWrongCongigurationException;

class IndicesDataGetter
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $indicesData;

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
     */
    public function getIndexData(string $indexName): IndexData
    {
        foreach ($this->indicesData as $indexData) {
            if ($indexData['name'] === $indexName) {
                $dto = new IndexData($indexData['name'], $indexData['body']);
                $dto->setScript($indexData['script'] ?? []);

                return $dto;
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
            throw new IndicesWrongCongigurationException(IndicesWrongCongigurationException::WRONG_DATA);
        }

        foreach ($indicesData as $position => $indexData) {
            if (!is_array($indexData)) {
                $message = sprintf(IndicesWrongCongigurationException::WRONG_INDEX_DATA, 'data', $position);

                throw new IndicesWrongCongigurationException($message);
            }

            if (!array_key_exists('name', $indexData)) {
                $message = sprintf(IndicesWrongCongigurationException::WRONG_INDEX_DATA, 'name', $position);

                throw new IndicesWrongCongigurationException($message);
            }

            $isValidBody = array_key_exists('body', $indexData) && is_array($indexData['body']);

            if (!$isValidBody) {
                $message = sprintf(IndicesWrongCongigurationException::WRONG_INDEX_DATA, 'body', $position);

                throw new IndicesWrongCongigurationException($message);
            }

            if (array_key_exists('script', $indexData) && !is_array($indexData['script'])) {
                $message = sprintf(IndicesWrongCongigurationException::WRONG_INDEX_DATA, 'script', $position);

                throw new IndicesWrongCongigurationException($message);
            }
        }
    }
}

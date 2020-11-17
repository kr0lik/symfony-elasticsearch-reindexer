<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Command\Fixtures;

use Exception;

class IndicesNamespaceWithOldIndex
{
    public const OLD_INDEX = 'index_name-v1';

    /**
     * @param array<string, mixed> $params
     */
    public function existsAlias($params): bool
    {
        return 'index_name' === $params['name'];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get($params): array
    {
        return [self::OLD_INDEX => []];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function exists($params): bool
    {
        if (self::OLD_INDEX === $params['index']) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create($params): array
    {
        return ['acknowledged' => true];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function delete($params = []): array
    {
        return ['acknowledged' => true];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function updateAliases($params = []): array
    {
        return ['acknowledged' => true];
    }
}

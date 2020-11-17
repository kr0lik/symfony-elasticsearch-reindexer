<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Command\Fixtures;

class IndicesNamespaceWithoutOldIndex
{
    /**
     * @param array<string, mixed> $params
     */
    public function existsAlias($params): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function exists($params): bool
    {
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
    public function updateAliases($params = []): array
    {
        return ['acknowledged' => true];
    }
}

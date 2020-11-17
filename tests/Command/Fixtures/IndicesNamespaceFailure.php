<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Command\Fixtures;

class IndicesNamespaceFailure
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
        return ['acknowledged' => false];
    }
}

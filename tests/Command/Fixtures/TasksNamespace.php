<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Command\Fixtures;

class TasksNamespace
{
    public const TOTAL_HITS = 100;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get($params = []): array
    {
        return [
            'task' => [
                'status' => [
                    'total' => self::TOTAL_HITS,
                    'created' => self::TOTAL_HITS,
                    'updated' => 0,
                    'deleted' => 0,
                ],
            ],
            'completed' => true,
        ];
    }
}

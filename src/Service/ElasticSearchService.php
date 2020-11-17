<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use kr0lik\ElasticSearchReindex\Dto\IndexInfoDto;
use kr0lik\ElasticSearchReindex\Dto\TaskInfoDto;
use kr0lik\ElasticSearchReindex\Exception\CreateAliasException;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\DeleteIndexException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;

class ElasticSearchService
{
    /**
     * @var Client
     */
    private $esClient;

    public function __construct(Client $esClient)
    {
        $this->esClient = $esClient;
    }

    public function isAliasExist(string $alias): bool
    {
        return $this->esClient->indices()->existsAlias(['name' => $alias]);
    }

    public function isIndexExist(string $index): bool
    {
        return $this->esClient->indices()->exists(['index' => $index]);
    }

    public function getIndexName(string $index): string
    {
        $indexData = $this->esClient->indices()->get(['index' => $index]);

        return (string) key($indexData);
    }

    public function getIndexDocs(string $index): int
    {
        $stats = $this->esClient->indices()->stats(['index' => $index]);

        return $stats['indices'][$index]['total']['docs']['count'];
    }

    /**
     * @throws InvalidResponseBodyException
     */
    public function reindex(string $oldIndex, string $newIndex, int $fromTime): string
    {
        $result = $this->esClient->reindex([
            'wait_for_completion' => false,
            'body' => [
                'source' => [
                    'index' => $oldIndex,
                    'query' => [
                        'range' => [
                            'meta.cas' => [
                                'gte' => $fromTime,
                            ],
                        ],
                    ],
                    'sort' => [
                        'meta.cas' => 'asc',
                    ],
                ],
                'dest' => [
                    'index' => $newIndex,
                ],
            ],
        ]);

        if (!isset($result['task'])) {
            throw new InvalidResponseBodyException("No 'task' field in result");
        }

        return $result['task'];
    }

    public function getIndexInfo(string $index): IndexInfoDto
    {
        try {
            $lastDocument = $this->esClient->search([
                'index' => $index,
                'size' => 1,
                'sort' => 'meta.cas:desc',
            ]);
            // @phpstan-ignore-next-line
        } catch (BadRequest400Exception $e) {
            // в новом индексе без документов нет поля meta.cas
            return new IndexInfoDto(0, 0);
        }

        return new IndexInfoDto(
            $lastDocument['hits']['hits'][0]['_source']['meta']['cas'] ?? 0,
            $lastDocument['hits']['total']
        );
    }

    /**
     * @throws TaskNotFoundException
     */
    public function getTaskInfo(string $taskId): TaskInfoDto
    {
        $tasks = $this->esClient->tasks();

        try {
            $task = $tasks->get(['task_id' => $taskId]);
        } catch (Missing404Exception $exception) {
            throw new TaskNotFoundException('Задача не найдена ('.$taskId.')', $exception);
        }

        $taskStatus = $task['task']['status'];

        return new TaskInfoDto(
            $task['completed'],
            $taskStatus['total'],
            $taskStatus['created'] + $taskStatus['updated'] + $taskStatus['deleted']
        );
    }

    /**
     * @param array<string, mixed> $createIndexBody
     *
     * @throws CreateIndexException
     */
    public function createIndex(string $index, array $createIndexBody): void
    {
        $result = $this->esClient->indices()->create([
            'index' => $index,
            'body' => $createIndexBody,
        ])
        ;

        if (!$this->isAcknowledged($result)) {
            throw new CreateIndexException("Index `{$index}` don't created");
        }
    }

    /**
     * @throws CreateAliasException
     */
    public function createAlias(string $alias, string $newIndex, ?string $oldIndex = null): void
    {
        $actions = [];

        if (null !== $oldIndex) {
            $actions[] = [
                'remove_index' => ['index' => $oldIndex],
            ];
        }

        $actions[] = [
            'add' => ['index' => $newIndex, 'alias' => $alias],
        ];

        $result = $this->esClient->indices()->updateAliases([
            'body' => [
                'actions' => $actions,
            ],
        ])
        ;

        if (!$this->isAcknowledged($result)) {
            throw new CreateAliasException("Alias `{$alias}` on `{$newIndex}` don't created");
        }
    }

    /**
     * @throws DeleteIndexException
     */
    public function deleteIndex(string $index): void
    {
        $result = $this->esClient->indices()->delete([
            'index' => $index,
        ])
        ;

        if (!$this->isAcknowledged($result)) {
            throw new DeleteIndexException("Index `{$index}` don't deleted");
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isAcknowledged(array $result): bool
    {
        return isset($result['acknowledged'])
            && true === $result['acknowledged'];
    }
}

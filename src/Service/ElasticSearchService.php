<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use kr0lik\ElasticSearchReindex\Dto\IndexInfo;
use kr0lik\ElasticSearchReindex\Dto\TaskInfo;
use kr0lik\ElasticSearchReindex\Exception\CreateAliasException;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\DeleteIndexException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\SettingsIndexException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;

class ElasticSearchService
{
    private Client $esClient;

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
     * @param array<string, mixed> $script Associative array of parameters
     *                                     $script['source'] = (string) The script to run to update the document source or metadata when reindexing.
     *                                     $script['lang'] = (string) The script language: painless, expression, mustache, java
     *
     * @throws InvalidResponseBodyException
     */
    public function reindex(string $oldIndex, string $newIndex, int $fromTime, array $script): string
    {
        $params = [
            'wait_for_completion' => false,
            'body' => [
                'conflicts' => 'abort',
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
                'script' => [
                    'source' => 'ctx._source.doc.remove("timestamps")',
                    'lang' => 'painless',
                ],
            ],
        ];

        if ([] !== $script) {
            $params['body']['script'] = $script;
        }

        $result = $this->esClient->reindex($params);

        if (!isset($result['task'])) {
            throw new InvalidResponseBodyException("No 'task' field in result");
        }

        return $result['task'];
    }

    public function getIndexInfo(string $index): IndexInfo
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
            return new IndexInfo($index, 0, 0);
        }

        return new IndexInfo(
            $index,
            $lastDocument['hits']['hits'][0]['_source']['meta']['cas'] ?? 0,
            $lastDocument['hits']['total']
        );
    }

    /**
     * @throws TaskNotFoundException
     */
    public function getTaskInfo(string $taskId): TaskInfo
    {
        $tasks = $this->esClient->tasks();

        try {
            $task = $tasks->get(['task_id' => $taskId]);
        } catch (Missing404Exception $exception) {
            throw new TaskNotFoundException('Task not found ('.$taskId.')', $exception);
        }

        $taskStatus = $task['task']['status'];

        return new TaskInfo(
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
        ]);

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
        ]);

        if (!$this->isAcknowledged($result)) {
            throw new CreateAliasException("Alias `{$alias}` on `{$newIndex}` don't created");
        }
    }

    /**
     * @throws SettingsIndexException
     */
    public function setRefreshInterval(string $index, string $value): void
    {
        $result = $this->esClient->indices()->putSettings([
            'index' => $index,
            'body' => [
                'index' => [
                    'refresh_interval' => $value,
                ],
            ],
        ]);

        if (!$this->isAcknowledged($result)) {
            throw new SettingsIndexException("Refresh interval don't restored");
        }
    }

    /**
     * @throws SettingsIndexException
     */
    public function restoreReplicas(string $index): void
    {
        $result = $this->esClient->indices()->putSettings([
            'index' => $index,
            'body' => [
                'index' => [
                    'number_of_replicas' => 1,
                ],
            ],
        ]);

        if (!$this->isAcknowledged($result)) {
            throw new SettingsIndexException("Refresh interval don't restored");
        }
    }

    /**
     * @throws DeleteIndexException
     */
    public function deleteIndex(string $index): void
    {
        $result = $this->esClient->indices()->delete([
            'index' => $index,
        ]);

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

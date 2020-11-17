<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use Generator;
use kr0lik\ElasticSearchReindex\Dto\IndexInfoDto;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;

class Reindexer
{
    private const CHECK_INDEX_DOCS_DELAY = 3 * 1000 * 1000;
    private const CHECK_REPLICA_DELAY = 3 * 1000 * 1000;
    private const CHECK_INDEX_DOCS_MAX_TRY = 10;

    /**
     * @var int
     */
    private $tryCount = 0;

    /**
     * @var ElasticSearchService
     */
    private $service;

    public function __construct(ElasticSearchService $service)
    {
        $this->service = $service;
    }

    /**
     * @throws TaskNotFoundException
     * @throws InvalidResponseBodyException
     *
     * @return Generator<int>
     */
    public function reindex(
        string $oldIndexName,
        string $newIndexName,
        int $oldIndexDocs,
        int $fromTime,
        int $reindexCheckTimeout
    ): Generator {
        $this->tryCount = 0;

        $taskId = $this->service->reindex($oldIndexName, $newIndexName, $fromTime);

        do {
            try {
                $task = $this->service->getTaskInfo($taskId);
            } catch (TaskNotFoundException $exception) {
                // дополнительно ждем чтобы индексация точно завершилась
                usleep(self::CHECK_INDEX_DOCS_DELAY);
                $newDocs = $this->service->getIndexDocs($newIndexName);

                if ($newDocs >= $oldIndexDocs) {
                    yield $newDocs;

                    break;
                }

                throw $exception;
            }

            yield $task->getProcessed();

            usleep($reindexCheckTimeout);
        } while (false === $task->isCompleted());
    }

    public function isNeedReindex(string $oldIndexName, string $newIndexName, int $oldIndexTotalDocuments, int $newIndexTotalDocuments): bool
    {
        // возможно индекс еще не успел обновиться  либо запаздывают реплики
        $this->checkReplicaIsLate($newIndexName, $oldIndexTotalDocuments);

        $oldIndexInfo = $this->service->getIndexInfo($oldIndexName);
        $newIndexInfo = $this->service->getIndexInfo($newIndexName);

        // встречаются ситуации, когда ES жалуется на проблемы с конвертацией типа поля.
        // но после нескольких попыток, все получается
        $tryCount = $this->getTryCount($newIndexInfo, $newIndexTotalDocuments);

        // если были проблемые документы, то доп. условие: в новый индекс ничего нового не прилетает
        return $newIndexInfo->getTotalDocuments() > $newIndexTotalDocuments
            && $oldIndexInfo->getTotalDocuments() > $newIndexInfo->getTotalDocuments()
            && $tryCount > self::CHECK_INDEX_DOCS_MAX_TRY;
    }

    private function checkReplicaIsLate(string $newIndexName, int $oldIndexTotalDocuments): void
    {
        $newIndexInfo = $this->service->getIndexInfo($newIndexName);

        if ($oldIndexTotalDocuments > $newIndexInfo->getTotalDocuments()) {
            usleep(self::CHECK_REPLICA_DELAY);
        }
    }

    private function getTryCount(IndexInfoDto $newIndexInfo, int $newIndexTotalDocuments): int
    {
        if ($newIndexInfo->getTotalDocuments() > $newIndexTotalDocuments) {
            ++$this->tryCount;
        }

        return $this->tryCount;
    }
}

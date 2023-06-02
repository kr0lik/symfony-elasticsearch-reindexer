<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Service;

use Generator;
use kr0lik\ElasticSearchReindex\Dto\IndexData;
use kr0lik\ElasticSearchReindex\Dto\IndexInfo;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;

class Reindexer
{
    private const CHECK_INDEX_DOCS_DELAY = 3 * 1000 * 1000;

    private ElasticSearchService $service;

    public function __construct(ElasticSearchService $service)
    {
        $this->service = $service;
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     *
     * @return Generator<int>
     */
    public function reindex(
        IndexInfo $oldIndexInfo,
        IndexInfo $newIndexInfo,
        IndexData $indexData,
        int $reindexCheckTimeout
    ): Generator {
        $taskId = $this->service->reindex(
            $oldIndexInfo->getName(),
            $newIndexInfo->getName(),
            $newIndexInfo->getLastUpdatedDocumentTime(),
            $indexData->getScript()
        );

        do {
            try {
                $task = $this->service->getTaskInfo($taskId);
            } catch (TaskNotFoundException $exception) {
                // дополнительно ждем чтобы индексация точно завершилась
                usleep(self::CHECK_INDEX_DOCS_DELAY);
                $newDocs = $this->service->getIndexDocs($newIndexInfo->getName());

                if ($newDocs >= $oldIndexInfo->getTotalDocuments()) {
                    yield $newDocs;

                    break;
                }

                throw $exception;
            }

            yield $task->getProcessed();

            usleep($reindexCheckTimeout);
        } while (false === $task->isCompleted());
    }

    public function isNeedReindex(string $oldIndex, string $newIndex): bool
    {
        $oldIndexInfo = $this->service->getIndexInfo($oldIndex);
        $newIndexInfo = $this->service->getIndexInfo($newIndex);

        return $oldIndexInfo->getTotalDocuments() > $newIndexInfo->getTotalDocuments()
            || $oldIndexInfo->getLastUpdatedDocumentTime() > $newIndexInfo->getLastUpdatedDocumentTime();
    }
}

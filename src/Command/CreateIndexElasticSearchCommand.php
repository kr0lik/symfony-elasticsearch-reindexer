<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Command;

use kr0lik\ElasticSearchReindex\Dto\IndexData;
use kr0lik\ElasticSearchReindex\Exception\CreateAliasException;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\DeleteIndexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotConfiguredException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\SettingsIndexException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\IndexGetter;
use kr0lik\ElasticSearchReindex\Service\IndicesDataGetter;
use kr0lik\ElasticSearchReindex\Service\Reindexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function is_string;
use function usleep;

class CreateIndexElasticSearchCommand extends Command
{
    private const REINDEX_CHECK_DEFAULT_TIMEOUT = '500'; // ms
    private const OPTION_REINDEX_CHECK_TIMEOUT = 'reindex-check-timeout';
    private const ARGUMENT_INDEX_NAME = 'index-name';

    protected static $defaultName = 'elastic-search:create-index';

    private IndexGetter $getter;
    private ElasticSearchService $service;
    private Reindexer $reindexer;
    private IndicesDataGetter $indicesDataGetter;
    private int $reindexCheckTimeout;

    public function __construct(
        IndexGetter $getter,
        ElasticSearchService $service,
        Reindexer $reindexer,
        IndicesDataGetter $indicesDataGetter
    ) {
        $this->getter = $getter;
        $this->service = $service;
        $this->reindexer = $reindexer;
        $this->indicesDataGetter = $indicesDataGetter;

        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Команда для создания индекса в ES.')
            ->addOption(
                self::OPTION_REINDEX_CHECK_TIMEOUT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Время задержки между проверкой состояния переиндексации (мс)',
                self::REINDEX_CHECK_DEFAULT_TIMEOUT
            )
            ->addArgument(
                self::ARGUMENT_INDEX_NAME,
                InputArgument::REQUIRED,
                'Название индекса'
            )
        ;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->reindexCheckTimeout = (int) $input->getOption(self::OPTION_REINDEX_CHECK_TIMEOUT) * 1000;
        $indexName = $input->getArgument(self::ARGUMENT_INDEX_NAME);
        assert(is_string($indexName));

        try {
            $indexData = $this->indicesDataGetter->getIndexData($indexName);
        } catch (IndexNotConfiguredException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return self::FAILURE;
        }

        $oldIndex = null;

        try {
            $oldIndex = $this->getter->getOldIndexName($indexName);

            $output->writeln("<comment>Old index: {$oldIndex}.</comment>");
        } catch (IndexNotExistException $exception) {
            $output->writeln('<comment>Old index not exists.</comment>');
        }

        try {
            $baseIndex = $oldIndex ?? $indexName;
            $newIndex = $this->getter->getNewIndexName($baseIndex, $indexData);

            $output->writeln("<info>New index: {$newIndex}.</info>");
        } catch (CreateIndexException $exception) {
            $output->writeln('<error>New index created error.</error>');

            return self::FAILURE;
        }

        if (null !== $oldIndex) {
            try {
                $this->reindex($output, $oldIndex, $newIndex, $indexData);
            } catch (Throwable $exception) {
                $output->writeln('');
                $output->writeln('<error>Reindexer error.</error>');

                try {
                    $this->service->deleteIndex($newIndex);

                    $output->writeln('<info>New index was deleted.</info>');
                } catch (DeleteIndexException $exception) {
                    $output->writeln('<error>New index delete error.</error>');
                }

                return self::FAILURE;
            }
        }

        try {
            $this->service->createAlias($indexName, $newIndex, $oldIndex);

            $output->writeln('<info>Alias updated.</info>');
        } catch (CreateAliasException $exception) {
            $output->writeln('<error>Alias updated error.</error>');
        }

        try {
            $this->service->restoreReplicas($newIndex);

            $output->writeln('<info>Replicas restored.</info>');
        } catch (SettingsIndexException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'.</error>');
        }

        return self::SUCCESS;
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     */
    private function reindex(OutputInterface $output, string $oldIndex, string $newIndex, IndexData $indexData): void
    {
        $firstPass = true;

        do {
            $newIndexInfo = $this->service->getIndexInfo($newIndex);

            if ($firstPass) {
                try {
                    $this->service->setRefreshInterval($newIndex, '-1');
                    $output->writeln('<comment>Refresh interval disable.</comment>');
                } catch (SettingsIndexException $exception) {
                    $output->writeln('<error>'.$exception->getMessage().'.</error>');
                }
            }

            $oldIndexInfo = $this->service->getIndexInfo($oldIndex);

            $output->writeln(sprintf('<info>Reindexing from time: %d.</info>', $newIndexInfo->getLastUpdatedDocumentTime()));

            $progressBar = new ProgressBar($output);
            $progressBar->start($oldIndexInfo->getTotalDocuments());

            foreach ($this->reindexer->reindex($oldIndexInfo, $newIndexInfo, $indexData, $this->reindexCheckTimeout) as $processed) {
                $progressBar->setProgress($processed);
            }

            $progressBar->finish();
            $output->writeln('');

            if ($firstPass) {
                try {
                    $this->service->setRefreshInterval($newIndex, '1s');
                    $output->writeln('<comment>Refresh interval restored.</comment>');
                } catch (SettingsIndexException $exception) {
                    $output->writeln('<error>'.$exception->getMessage().'.</error>');
                }
            }

            $this->waitRefresh($output, $newIndexInfo->getName(), $oldIndexInfo->getTotalDocuments());

            $firstPass = false;
        } while ($this->reindexer->isNeedReindex($oldIndexInfo->getName(), $newIndexInfo->getName()));

        $output->writeln('<comment>Reindexer done.</comment>');
    }

    private function waitRefresh(OutputInterface $output, string $index, int $total): void
    {
        $output->writeln('<info>Wait refresh.</info>');

        $progressBar = new ProgressBar($output);
        $progressBar->start($total);

        do {
            $indexInfo = $this->service->getIndexInfo($index);
            $progressBar->setProgress($indexInfo->getTotalDocuments());
            usleep($this->reindexCheckTimeout);
        } while ($indexInfo->getTotalDocuments() < $total);

        $progressBar->finish();
        $output->writeln('');
    }
}

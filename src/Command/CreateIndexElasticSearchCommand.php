<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Command;

use kr0lik\ElasticSearchReindex\Exception\CreateAliasException;
use kr0lik\ElasticSearchReindex\Exception\CreateIndexException;
use kr0lik\ElasticSearchReindex\Exception\DeleteIndexException;
use kr0lik\ElasticSearchReindex\Exception\EsReindexException;
use kr0lik\ElasticSearchReindex\Exception\IndexNotExistException;
use kr0lik\ElasticSearchReindex\Exception\InvalidResponseBodyException;
use kr0lik\ElasticSearchReindex\Exception\TaskNotFoundException;
use kr0lik\ElasticSearchReindex\Service\ElasticSearchService;
use kr0lik\ElasticSearchReindex\Service\IndexCreator;
use kr0lik\ElasticSearchReindex\Service\IndexNameGetter;
use kr0lik\ElasticSearchReindex\Service\Reindexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateIndexElasticSearchCommand extends Command
{
    public const SUCCESS = 0; // todo remove when upgrade to symfony 5
    public const FAILURE = 1; // todo remove when upgrade to symfony 5

    private const REINDEX_CHECK_DEFAULT_TIMEOUT = 300; // ms
    private const OPTION_REINDEX_CHECK_TIMEOUT = 'reindex-check-timeout';
    private const ARGUMENT_INDEX_NAME = 'index-name';

    protected static $defaultName = 'elastic-search:create-index';

    /**
     * @var IndexNameGetter
     */
    private $getter;

    /**
     * @var IndexCreator
     */
    private $creator;

    /**
     * @var ElasticSearchService
     */
    private $service;

    /**
     * @var Reindexer
     */
    private $reindexer;

    public function __construct(
        IndexNameGetter $getter,
        IndexCreator $creator,
        ElasticSearchService $service,
        Reindexer $reindexer
    ) {
        $this->getter = $getter;
        $this->service = $service;
        $this->reindexer = $reindexer;
        $this->creator = $creator;

        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Create new index command.')
            ->addOption(
                self::OPTION_REINDEX_CHECK_TIMEOUT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Delay between reindex statuses (ms)',
                self::REINDEX_CHECK_DEFAULT_TIMEOUT
            )
            ->addArgument(
                self::ARGUMENT_INDEX_NAME,
                InputArgument::REQUIRED,
                'Index name'
            )
        ;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reindexCheckTimeout = (int) $input->getOption(self::OPTION_REINDEX_CHECK_TIMEOUT);
        $baseIndexName = (string) $input->getArgument(self::ARGUMENT_INDEX_NAME);

        $reindexCheckTimeout = $reindexCheckTimeout * 1000;
        
        $oldIndexName = null;

        try {
            $oldIndexName = $this->getter->getOldIndexName($baseIndexName);
            
            $output->writeln("<comment>Old index: {$oldIndexName}.</comment>");
        } catch (IndexNotExistException $exception) {
            $output->writeln('<comment>Old index not exists.</comment>');
        }

        $newIndexName = $this->getter->getNewIndexName($oldIndexName ?? $baseIndexName);
        
        $output->writeln("<info>New index: {$newIndexName}.</info>");

        try {
            $this->creator->createNewIndex($newIndexName);
        } catch (CreateIndexException $exception) {
            $output->writeln('<error>New index create error.</error>');

            return self::FAILURE;
        }

        if (null !== $oldIndexName) {
            $output->writeln('<comment>Reindexer...</comment>');

            try {
                $this->reindex($output, $oldIndexName, $newIndexName, $reindexCheckTimeout);
            } catch (EsReindexException $exception) {
                $output->writeln('');
                $output->writeln('<error>Reindexer error.</error>');

                try {
                    $this->service->deleteIndex($newIndexName);
                    
                    $output->writeln('<info>New index was deleted.</info>');
                } catch (DeleteIndexException $exception) {
                    $output->writeln('<error>New index delete error.</error>');
                }

                return self::FAILURE;
            }

            $output->writeln('<comment>Reindexer done.</comment>');
        }

        try {
            $this->service->createAlias($baseIndexName, $newIndexName, $oldIndexName);
            
            $output->writeln('<info>Alias updated.</info>');
        } catch (CreateAliasException $exception) {
            $output->writeln('<error>Alias update error.</error>');
        }

        return self::SUCCESS;
    }

    /**
     * @throws InvalidResponseBodyException
     * @throws TaskNotFoundException
     */
    private function reindex(OutputInterface $output, string $oldIndexName, string $newIndexName, int $reindexCheckTimeout): void
    {
        $oldIndexInfo = $this->service->getIndexInfo($oldIndexName);
        $newIndexInfo = $this->service->getIndexInfo($newIndexName);

        do {
            $oldIndexTotalDocuments = $oldIndexInfo->getTotalDocuments();
            $newIndexTotalDocuments = $newIndexInfo->getTotalDocuments();

            $fromTime = $newIndexInfo->getLastUpdatedDocumentTime();
            
            $output->writeln(sprintf('<info>Reindexing from time: %d.</info>', $fromTime));

            $progressBar = new ProgressBar($output);
            $progressBar->start($oldIndexTotalDocuments);

            foreach ($this->reindexer->reindex(
                $oldIndexName,
                $newIndexName,
                $oldIndexTotalDocuments,
                $fromTime,
                $reindexCheckTimeout
            ) as $processed) {
                $progressBar->setProgress($processed);
            }

            $progressBar->finish();
            $output->writeln('');
        } while ($this->reindexer->isNeedReindex($oldIndexName, $newIndexName, $oldIndexTotalDocuments, $newIndexTotalDocuments));
    }
}

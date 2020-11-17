<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Dto;

class IndexInfoDto
{
    /**
     * @var int
     */
    private $lastUpdatedDocumentTime;

    /**
     * @var int
     */
    private $totalDocuments;

    public function __construct(int $lastUpdatedDocumentTime, int $totalDocuments)
    {
        $this->lastUpdatedDocumentTime = $lastUpdatedDocumentTime;
        $this->totalDocuments = $totalDocuments;
    }

    public function getLastUpdatedDocumentTime(): int
    {
        return $this->lastUpdatedDocumentTime;
    }

    public function getTotalDocuments(): int
    {
        return $this->totalDocuments;
    }
}

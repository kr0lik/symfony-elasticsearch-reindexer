<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Dto;

class IndexInfo
{
    private int $lastUpdatedDocumentTime;
    private int $totalDocuments;
    private string $name;

    public function __construct(string $name, int $lastUpdatedDocumentTime, int $totalDocuments)
    {
        $this->lastUpdatedDocumentTime = $lastUpdatedDocumentTime;
        $this->totalDocuments = $totalDocuments;
        $this->name = $name;
    }

    public function getLastUpdatedDocumentTime(): int
    {
        return $this->lastUpdatedDocumentTime;
    }

    public function getTotalDocuments(): int
    {
        return $this->totalDocuments;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

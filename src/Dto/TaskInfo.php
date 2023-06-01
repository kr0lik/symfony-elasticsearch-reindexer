<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Dto;

class TaskInfo
{
    private bool $isCompleted;
    private int $total;
    private int $processed;

    public function __construct(bool $isCompleted, int $total, int $processed)
    {
        $this->isCompleted = $isCompleted;
        $this->total = $total;
        $this->processed = $processed;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }
}

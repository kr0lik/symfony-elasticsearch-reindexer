<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Dto;

class TaskInfoDto
{
    /**
     * @var bool
     */
    private $completed;

    /**
     * @var int
     */
    private $total;

    /**
     * @var int
     */
    private $processed;

    public function __construct(bool $completed, int $total, int $processed)
    {
        $this->completed = $completed;
        $this->total = $total;
        $this->processed = $processed;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
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

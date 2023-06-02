<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Dto;

class IndexData
{
    private string $name;
    /**
     * @var array<string, mixed>
     */
    private array $body;
    /**
     * @var array<string, mixed>
     */
    private array $script = [];

    /**
     * @param array<string, mixed> $body
     */
    public function __construct(string $name, array $body)
    {
        $this->name = $name;
        $this->body = $body;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getScript(): array
    {
        return $this->script;
    }

    /**
     * @param array<string, mixed> $script
     */
    public function setScript(array $script): self
    {
        $this->script = $script;

        return $this;
    }
}

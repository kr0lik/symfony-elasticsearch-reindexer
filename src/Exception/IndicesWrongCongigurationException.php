<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Exception;

class IndicesWrongCongigurationException extends EsReindexCommandException
{
    public const WRONG_DATA = 'Configure at least one index in indices section.';
    public const WRONG_INDEX_DATA = 'Wrong configured index %s at position %s.';
}

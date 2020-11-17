<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Helper;

class IndexNameHelper
{
    public static function getBaseIndexName(string $indexName): string
    {
        return preg_replace('#(-v\d+)$#i', '', $indexName);
    }
}

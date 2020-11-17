<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Helper;

use kr0lik\ElasticSearchReindex\Helper\IndexNameHelper;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
 * @coversNothing
 */
class IndexNameHelperTest extends TestCase
{
    /**
     * @dataProvider getIndexNames
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testGetBaseIndexName(string $expected, string $actual): void
    {
        $result = IndexNameHelper::getBaseIndexName($actual);

        self::assertEquals($expected, $result);
    }

    /**
     * @return array<mixed>
     */
    public function getIndexNames(): array
    {
        return [
            [
                'expected' => 'some-index',
                'actual' => 'some-index-v1',
            ],
            [
                'expected' => 'some-index',
                'actual' => 'some-index-v11',
            ],
            [
                'expected' => 'some-index',
                'actual' => 'some-index-v110',
            ],
        ];
    }
}

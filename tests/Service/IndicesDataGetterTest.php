<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Tests\Service;

use kr0lik\ElasticSearchReindex\Exception\IndexNotConfiguredException;
use kr0lik\ElasticSearchReindex\Exception\IndicesWrongCongigurationException;
use kr0lik\ElasticSearchReindex\Service\IndicesDataGetter;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @internal
 */
class IndicesDataGetterTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFailIndicesDataProvider(): array
    {
        return [
            'empty indicesData array' => [
                'indicesData' => [],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Configure at least one index in indices section.',
            ],
            'indicesData at 0 position not array' => [
                'indicesData' => [
                    'not array!',
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index data at position 0.',
            ],
            'empty name at 0 position' => [
                'indicesData' => [
                    [
                        'body' => [],
                    ],
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index name at position 0.',
            ],
            'empty name at 1 position' => [
                'indicesData' => [
                    [
                        'name' => 'some name',
                        'body' => [],
                    ],
                    [
                        'body' => [],
                    ],
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index name at position 1.',
            ],
            'empty body at 0 position' => [
                'indicesData' => [
                    [
                        'name' => 'some name',
                    ],
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index body at position 0.',
            ],
            'body not array at 0 position 1' => [
                'indicesData' => [
                    [
                        'name' => 'some name',
                        'body' => 'value',
                    ],
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index body at position 0.',
            ],
            'body not array at 0 position 2' => [
                'indicesData' => [
                    [
                        'name' => 'some name',
                        'body' => null,
                    ],
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index body at position 0.',
            ],
            'body not array at 0 position 3' => [
                'indicesData' => [
                    [
                        'name' => 'some name',
                        'body' => 123,
                    ],
                ],
                'exception' => IndicesWrongCongigurationException::class,
                'exceptionMessage' => 'Wrong configured index body at position 0.',
            ],
        ];
    }

    /**
     * @dataProvider getFailIndicesDataProvider
     *
     * @param array<int, array<string, mixed>>                 $indicesData
     * @param class-string<IndicesWrongCongigurationException> $exception
     *
     * @throws IndicesWrongCongigurationException
     */
    public function testFailBuild(array $indicesData, string $exception, string $exceptionMessage): void
    {
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectException($exception);

        new IndicesDataGetter($indicesData);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getIndicesDataProvider(): array
    {
        return [
            'case 1' => [
                'indicesData' => [
                    [
                        'name' => 'index1',
                        'body' => [
                            'key1' => 'val1',
                        ],
                    ],
                    [
                        'name' => 'index2',
                        'body' => [
                            'key2' => 'val2',
                        ],
                    ],
                ],
                'index' => 'index1',
                'expectBody' => [
                    'key1' => 'val1',
                ],
            ],
            'case 2' => [
                'indicesData' => [
                    [
                        'name' => 'index1',
                        'body' => [
                            'key1' => 'val1',
                        ],
                    ],
                    [
                        'name' => 'index2',
                        'body' => [
                            'key2' => 'val2',
                        ],
                    ],
                ],
                'index' => 'index2',
                'expectBody' => [
                    'key2' => 'val2',
                ],
            ],
        ];
    }

    /**
     * @dataProvider getIndicesDataProvider
     *
     * @param array<int, array<string, mixed>> $indicesData
     * @param array<mixed>                     $expectBody
     *
     * @throws ExpectationFailedException
     * @throws IndexNotConfiguredException
     * @throws IndicesWrongCongigurationException
     * @throws InvalidArgumentException
     */
    public function testGetIndexBody(array $indicesData, string $index, array $expectBody): void
    {
        $service = new IndicesDataGetter($indicesData);

        self::assertEquals($expectBody, $service->getIndexData($index)->getBody());
    }

    /**
     * @throws IndexNotConfiguredException
     * @throws IndicesWrongCongigurationException
     */
    public function testFailGetIndexBody(): void
    {
        $indicesData = [
            [
                'name' => 'index1',
                'body' => [
                    'key1' => 'val1',
                ],
            ],
        ];

        $service = new IndicesDataGetter($indicesData);

        $this->expectExceptionMessage('Index not-exists-name not configured.');
        $this->expectException(IndexNotConfiguredException::class);

        $service->getIndexData('not-exists-name')->getBody();
    }
}

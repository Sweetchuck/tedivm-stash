<?php

declare(strict_types = 1);

namespace Stash\Validator;

use Stash\Exception\InvalidArgumentException;
use Stash\ValidatorInterface;

/**
 * Validates "key" and "tags" with regex patterns.
 *
 * With default configuration the "key" and "tags" has to be compliant to the
 * PSR-16 standards.
 *
 * @see \Stash\ValidatorInterface::PSR16_KEY_REGEX_PATTERN
 * @see \Stash\ValidatorInterface::PSR16_TAG_REGEX_PATTERN
 */
class BasicValidator implements ValidatorInterface
{

    // phpcs:disable Generic.Files.LineLength.TooLong
    /**
     * @phpstan-var array<string, array<string, string>>
     */
    protected array $messagePatterns = [
        'single' => [
            'symbol_not_string' => 'The {{ type }} identifier has to be provided as string. Actual: {{ symbol_type }}',
            'symbol_not_matches_regex' => 'A cache {{ type }} "{{ symbol }}" must match to pattern {{ pattern }}',
        ],
        'multiple' => [
            'symbol_not_string' => 'The {{ type }} identifier at "{{ index }}" index has to be provided as string. Actual: {{ symbol_type }}',
            'symbol_not_matches_regex' => 'Cache {{ type }} "{{ symbol }}" at "{{ index }}" index must match to pattern {{ pattern }}',
        ],
    ];
    // phpcs:enable Generic.Files.LineLength.TooLong

    /**
     * @phpstan-var array{
     *   key: array{
     *     pattern: string,
     *     shouldMatch: bool,
     *   },
     *   tag: array{
     *     pattern: string,
     *     shouldMatch: bool,
     *   },
     * }
     */
    protected array $symbol = [
        'key' => [
            'pattern' => ValidatorInterface::PSR16_KEY_REGEX_PATTERN,
            'shouldMatch' => ValidatorInterface::PSR16_KEY_REGEX_SHOULD_MATCH,
        ],
        'tag' => [
            'pattern' => ValidatorInterface::PSR16_TAG_REGEX_PATTERN,
            'shouldMatch' => ValidatorInterface::PSR16_TAG_REGEX_SHOULD_MATCH,
        ],
    ];

    public function getKeyRegexPattern(): ?string
    {
        return $this->symbol['key']['shouldMatch'];
    }

    public function setKeyRegexPattern(?string $regexPattern): static
    {
        $this->symbol['key']['pattern'] = $regexPattern;

        return $this;
    }

    public function getKeyRegexShouldMatch(): bool
    {
        return $this->symbol['key']['shouldMatch'];
    }

    public function setKeyRegexShouldMatch(bool $shouldMatch): static
    {
        $this->symbol['key']['shouldMatch'] = $shouldMatch;

        return $this;
    }

    public function getTagRegexPattern(): ?string
    {
        return $this->symbol['tag']['pattern'];
    }

    public function setTagRegexPattern(?string $regexPattern): static
    {
        $this->symbol['tag']['pattern'] = $regexPattern;

        return $this;
    }

    public function getTagRegexShouldMatch(): bool
    {
        return $this->symbol['tag']['shouldMatch'];
    }

    public function setTagRegexShouldMatch(bool $shouldMatch): static
    {
        $this->symbol['tag']['shouldMatch'] = $shouldMatch;

        return $this;
    }

    public function setOptions(array $options): static
    {
        if (array_key_exists('keyRegexPattern', $options)) {
            $this->setKeyRegexPattern($options['keyRegexPattern']);
        }

        if (array_key_exists('keyRegexShouldMatch', $options)) {
            $this->setKeyRegexShouldMatch($options['keyRegexShouldMatch']);
        }

        if (array_key_exists('tagRegexPattern', $options)) {
            $this->setTagRegexPattern($options['tagRegexPattern']);
        }

        if (array_key_exists('tagRegexShouldMatch', $options)) {
            $this->setTagRegexShouldMatch($options['tagRegexShouldMatch']);
        }

        return $this;
    }

    public function validateKey(string $key, ?string $index = null): array
    {
        return $this->validateSymbol('key', $key, $index);
    }

    public function assertKey(string $key, ?string $index = null): static
    {
        return $this->assert($this->validateKey($key, $index));
    }

    /**
     * @phpstan-param \iterable<string> $keys
     */
    public function assertKeys(array $keys): static
    {
        return $this->assertSymbols('key', $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function validateValues($values): array
    {
        $errors = [];
        if (!is_iterable($values)) {
            // @todo Add to $this->$messagePatterns.
            $errors[] = '$values has to be an iterable. Actual: ' . gettype($values);
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     */
    public function assertValues($values): static
    {
        return $this->assert($this->validateValues($values));
    }

    /**
     * {@inheritdoc}
     */
    public function validateTag($tag, ?string $index = null): array
    {
        return $this->validateSymbol('tag', $tag, $index);
    }

    /**
     * {@inheritdoc}
     */
    public function assertTag($key, ?string $index = null): static
    {
        return $this->assert($this->validateTag($key, $index));
    }

    /**
     * {@inheritdoc}
     */
    public function assertTags($tags): static
    {
        if ($tags === null) {
            return $this;
        }

        return $this->assertSymbols('tag', $tags);
    }

    /**
     * {@inheritdoc}
     */
    public function validateTtl($ttl): array
    {
        if (is_null($ttl) || is_int($ttl) || $ttl instanceof \DateInterval) {
            return [];
        }

        return [
            // @todo Add to $this->$messagePatterns.
            '@param null|int|\DateInterval $ttl. Actual: ' . gettype($ttl),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function assertTtl($ttl): static
    {
        $errors = $this->validateTtl($ttl);

        return $this->assert($errors);
    }

    /**
     *
     * @param null|int|float|string $index
     *
     * @return string[]
     */
    protected function validateSymbol(string $type, string $symbol, $index = null): array
    {
        assert(array_key_exists($type, $this->symbol), 'symbol type has to be one of "key" or "tag"');
        $pattern = $this->symbol[$type]['pattern'];
        $shouldMatch = $this->symbol[$type]['shouldMatch'];

        if ($pattern === null) {
            return [];
        }

        $messagePatterns = $this->messagePatterns[$index === null ? 'single' : 'multiple'];
        $errors = [];
        $context = [
            '{{ type }}' => $type,
            '{{ symbol }}' => '',
            '{{ symbol_type }}' => gettype($symbol),
            '{{ index }}' => (string) $index,
            '{{ pattern }}' => $pattern,
        ];

        if (!is_string($symbol)) {
            $errors[] = strtr($messagePatterns['symbol_not_string'], $context);

            return $errors;
        }

        $context['{{ symbol }}'] = $symbol;

        $result = preg_match($pattern, $symbol);
        assert($result !== false, "invalid regexp pattern: $pattern");
        if (boolval($result) xor $shouldMatch) {
            $errors[] = strtr($messagePatterns['symbol_not_matches_regex'], $context);
        }

        return $errors;
    }

    /**
     * @param int|float|string $index
     */
    protected function assertSymbol(string $type, string $symbol, $index): static
    {
        $errors = $this->validateSymbol($type, $symbol, $index);

        return $this->assert($errors);
    }

    /**
     * @param \iterable<string> $symbols
     */
    protected function assertSymbols(string $type, iterable $symbols): static
    {
        if (!is_iterable($symbols)) {
            $message = '${{ type }}s has to be an iterable. Actual: {{ actual }}';
            $args = [
                '{{ type }}' => $type,
                '{{ actual }}' => gettype($symbols),
            ];
            throw new \InvalidArgumentException(strtr($message, $args));
        }

        /**
         * @var int|float|string $index
         * @var mixed $symbol
         */
        foreach ($symbols as $index => $symbol) {
            $this->assertSymbol($type, $symbol, $index);
        }

        return $this;
    }

    /**
     * @param string[] $errors
     */
    protected function assert(array $errors): static
    {
        if ($errors) {
            throw new InvalidArgumentException($this->errorsToMessage($errors));
        }

        return $this;
    }

    /**
     * @param string[] $errors
     */
    protected function errorsToMessage(array $errors): string
    {
        return implode(' - ', $errors);
    }
}

<?php

declare(strict_types = 1);

namespace Stash;

interface ValidatorInterface
{
    const PSR16_KEY_REGEX_PATTERN = '/^[^\\{\\}\\(\\)\/\\\\@\\:]+$/';

    const PSR16_KEY_REGEX_SHOULD_MATCH = true;

    const PSR16_TAG_REGEX_PATTERN = '/^[^\\{\\}\\(\\)\/\\\\@\\:]+$/';

    const PSR16_TAG_REGEX_SHOULD_MATCH = true;

    /**
     * @return string[]
     */
    public function validateKey(string $key, ?string $index = null): array;

    public function assertKey(string $key, ?string $index = null): static;

    /**
     * @param string[] $keys
     */
    public function assertKeys(array $keys): static;

    /**
     * @return string[]
     */
    public function validateValues(mixed $values): array;

    public function assertValues(mixed $values): static;

    /**
     * @return string[]
     */
    public function validateTag(string $tag, ?string $index = null): array;

    public function assertTag(string $key, ?string $index = null): static;

    /**
     * @param string[] $tags
     */
    public function assertTags(array $tags): static;

    /**
     * @return string[]
     */
    public function validateTtl(null|int|float|\DateTimeInterface|\DateInterval $ttl): array;

    public function assertTtl(null|int|float|\DateTimeInterface|\DateInterval $ttl): static;
}

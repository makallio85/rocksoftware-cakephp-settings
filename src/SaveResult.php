<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

/**
 * What saving a section came to: the fields refused, or the keys changed.
 * Nothing is written when any field is refused.
 */
final class SaveResult
{
    /**
     * @param array<string, string> $errors Each refused field's name and the reason.
     * @param list<string> $changedKeys The keys whose value changed.
     */
    public function __construct(
        public readonly array $errors = [],
        public readonly array $changedKeys = [],
    ) {
    }

    /**
     * @return bool
     */
    public function isSaved(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return bool
     */
    public function hasChanges(): bool
    {
        return $this->changedKeys !== [];
    }
}

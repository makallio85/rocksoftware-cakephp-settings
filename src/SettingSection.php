<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

use Closure;

/**
 * One owner's part of the settings page: its title, the text under it and
 * where it comes on the page. An owner that declares settings without a
 * section gets one titled by its name.
 */
final class SettingSection
{
    /**
     * @param string $owner The owner, the first part of its settings' keys.
     * @param \Closure|string $title The section's title; a closure for a translated one.
     * @param \Closure|string|null $description The text under the title.
     * @param int $order Lower comes first; the built-in Application section is 0.
     */
    public function __construct(
        public readonly string $owner,
        private readonly Closure|string $title,
        private readonly Closure|string|null $description = null,
        public readonly int $order = 100,
    ) {
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return is_string($this->title) ? $this->title : (string)($this->title)();
    }

    /**
     * @return string|null
     */
    public function description(): ?string
    {
        if ($this->description === null) {
            return null;
        }

        return is_string($this->description) ? $this->description : (string)($this->description)();
    }
}

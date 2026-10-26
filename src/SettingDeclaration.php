<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

use Closure;
use InvalidArgumentException;

/**
 * One setting as its owner declares it in code: its key, type, label, help
 * text, default and whether it is required. Declarations are code; values are
 * data (`rock_software_settings_values`).
 *
 * Build one with a named constructor and hand it to `Settings::declare()`:
 *
 *     Settings::declare(SettingDeclaration::text(
 *         'reports.footer',
 *         label: fn(): string => __d('reports', 'Footer'),
 *         help: fn(): string => __d('reports', 'Printed at the bottom of every report.'),
 *     ));
 *
 * A label, help text or choice label is a string or a closure returning one.
 * Use a closure for a translated text: it is called when the page is shown,
 * in the request's language, not when the declaration is made.
 */
final class SettingDeclaration
{
    /** A key: `<owner>.<name>`, both in snake_case. */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    /**
     * @param string $key `<owner>.<name>`.
     * @param \RockSoftware\Settings\SettingType $type What the value is.
     * @param \Closure|string $label The field's label.
     * @param \Closure|string|null $help The text under the field.
     * @param mixed $default The value while none is stored; of the type's PHP type.
     * @param bool $isRequired Whether the page refuses an empty value.
     * @param array<string, \Closure|string> $choices `Choice`: each value and its label, in order.
     * @param int|null $maxLength `Text`, `LongText`: the most characters accepted.
     * @param int|null $min `WholeNumber`: the smallest number accepted.
     * @param int|null $max `WholeNumber`: the largest number accepted.
     */
    private function __construct(
        public readonly string $key,
        public readonly SettingType $type,
        private readonly Closure|string $label,
        private readonly Closure|string|null $help,
        public readonly mixed $default,
        public readonly bool $isRequired,
        private readonly array $choices = [],
        public readonly ?int $maxLength = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Setting key "%s" is not <owner>.<name> in snake_case.',
                $key,
            ));
        }
        if ($type === SettingType::Choice && $choices === []) {
            throw new InvalidArgumentException(sprintf('Setting "%s" has no choices.', $key));
        }
        if (!$this->isValueOfType($default)) {
            throw new InvalidArgumentException(sprintf(
                'The default of setting "%s" is not a value of type %s.',
                $key,
                $type->value,
            ));
        }
    }

    /**
     * One line of text.
     *
     * @param string $key `<owner>.<name>`.
     * @param \Closure|string $label The label.
     * @param \Closure|string|null $help The help text.
     * @param string $default The default.
     * @param bool $isRequired Whether an empty value is refused.
     * @param int $maxLength The most characters accepted.
     * @return self
     */
    public static function text(
        string $key,
        Closure|string $label,
        Closure|string|null $help = null,
        string $default = '',
        bool $isRequired = false,
        int $maxLength = 255,
    ): self {
        return new self($key, SettingType::Text, $label, $help, $default, $isRequired, maxLength: $maxLength);
    }

    /**
     * Several lines of text.
     *
     * @param string $key `<owner>.<name>`.
     * @param \Closure|string $label The label.
     * @param \Closure|string|null $help The help text.
     * @param string $default The default.
     * @param bool $isRequired Whether an empty value is refused.
     * @param int $maxLength The most characters accepted.
     * @return self
     */
    public static function longText(
        string $key,
        Closure|string $label,
        Closure|string|null $help = null,
        string $default = '',
        bool $isRequired = false,
        int $maxLength = 10000,
    ): self {
        return new self($key, SettingType::LongText, $label, $help, $default, $isRequired, maxLength: $maxLength);
    }

    /**
     * Yes or no. Name the key as the question it answers: `reports.is_footer_shown`.
     *
     * @param string $key `<owner>.<name>`.
     * @param \Closure|string $label The label.
     * @param \Closure|string|null $help The help text.
     * @param bool $default The default.
     * @return self
     */
    public static function yesNo(
        string $key,
        Closure|string $label,
        Closure|string|null $help = null,
        bool $default = false,
    ): self {
        return new self($key, SettingType::YesNo, $label, $help, $default, false);
    }

    /**
     * A whole number.
     *
     * @param string $key `<owner>.<name>`.
     * @param \Closure|string $label The label.
     * @param \Closure|string|null $help The help text.
     * @param int|null $default The default; null for none.
     * @param bool $isRequired Whether an empty value is refused.
     * @param int|null $min The smallest number accepted.
     * @param int|null $max The largest number accepted.
     * @return self
     */
    public static function wholeNumber(
        string $key,
        Closure|string $label,
        Closure|string|null $help = null,
        ?int $default = null,
        bool $isRequired = false,
        ?int $min = null,
        ?int $max = null,
    ): self {
        return new self($key, SettingType::WholeNumber, $label, $help, $default, $isRequired, min: $min, max: $max);
    }

    /**
     * One value from a list.
     *
     * @param string $key `<owner>.<name>`.
     * @param \Closure|string $label The label.
     * @param array<string, \Closure|string> $choices Each value and its label, in order.
     * @param \Closure|string|null $help The help text.
     * @param string|null $default The default: one of the values, or null for none.
     * @param bool $isRequired Whether an empty value is refused.
     * @return self
     */
    public static function choice(
        string $key,
        Closure|string $label,
        array $choices,
        Closure|string|null $help = null,
        ?string $default = null,
        bool $isRequired = false,
    ): self {
        return new self($key, SettingType::Choice, $label, $help, $default, $isRequired, $choices);
    }

    /**
     * The owner: the part before the dot, which is also the settings page's section.
     *
     * @return string
     */
    public function owner(): string
    {
        return explode('.', $this->key, 2)[0];
    }

    /**
     * The name within the owner: the part after the dot, and the form field's name.
     *
     * @return string
     */
    public function name(): string
    {
        return explode('.', $this->key, 2)[1];
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return self::resolve($this->label);
    }

    /**
     * @return string|null
     */
    public function help(): ?string
    {
        return $this->help === null ? null : self::resolve($this->help);
    }

    /**
     * Each choice's value and label, in order.
     *
     * @return array<string, string>
     */
    public function choices(): array
    {
        return array_map(fn(Closure|string $label): string => self::resolve($label), $this->choices);
    }

    /**
     * Whether a value is of this setting's type (and, for a choice, one of its
     * values). Range and length are checked by `parse()`.
     *
     * @param mixed $value A value.
     * @return bool
     */
    public function isValueOfType(mixed $value): bool
    {
        return match ($this->type) {
            SettingType::Text, SettingType::LongText => is_string($value),
            SettingType::YesNo => is_bool($value),
            SettingType::WholeNumber => $value === null || is_int($value),
            SettingType::Choice => $value === null || (is_string($value) && array_key_exists($value, $this->choices)),
        };
    }

    /**
     * Turns a submitted value (a form's string, or a PHP value given in code)
     * into the setting's value, or refuses it.
     *
     * @param mixed $input What was submitted.
     * @return mixed The value, of the type's PHP type.
     * @throws \RockSoftware\Settings\InvalidSettingValueException With a message for the person.
     */
    public function parse(mixed $input): mixed
    {
        return match ($this->type) {
            SettingType::Text, SettingType::LongText => $this->parseText($input),
            SettingType::YesNo => $this->parseYesNo($input),
            SettingType::WholeNumber => $this->parseWholeNumber($input),
            SettingType::Choice => $this->parseChoice($input),
        };
    }

    /**
     * @param mixed $input What was submitted.
     * @return string
     */
    private function parseText(mixed $input): string
    {
        if ($input !== null && !is_scalar($input)) {
            throw new InvalidSettingValueException(__d('rock_software_settings', 'Enter text.'));
        }
        $value = trim((string)$input);
        if ($this->type === SettingType::LongText) {
            $value = str_replace("\r\n", "\n", $value);
        }
        if ($value === '' && $this->isRequired) {
            throw new InvalidSettingValueException(__d('rock_software_settings', 'This is required.'));
        }
        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            throw new InvalidSettingValueException(__d(
                'rock_software_settings',
                'At most {0} characters.',
                $this->maxLength,
            ));
        }

        return $value;
    }

    /**
     * @param mixed $input What was submitted.
     * @return bool
     */
    private function parseYesNo(mixed $input): bool
    {
        if (is_bool($input)) {
            return $input;
        }
        if (in_array($input, [null, '', '0', 0], true)) {
            return false;
        }
        if (in_array($input, ['1', 1], true)) {
            return true;
        }

        throw new InvalidSettingValueException(__d('rock_software_settings', 'Choose yes or no.'));
    }

    /**
     * @param mixed $input What was submitted.
     * @return int|null
     */
    private function parseWholeNumber(mixed $input): ?int
    {
        if ($input === null || (is_string($input) && trim($input) === '')) {
            if ($this->isRequired) {
                throw new InvalidSettingValueException(__d('rock_software_settings', 'This is required.'));
            }

            return null;
        }
        if (is_int($input)) {
            $value = $input;
        } elseif (is_string($input) && preg_match('/^\s*-?\d{1,18}\s*$/', $input) === 1) {
            $value = (int)$input;
        } else {
            throw new InvalidSettingValueException(__d('rock_software_settings', 'Enter a whole number.'));
        }
        if ($this->min !== null && $value < $this->min) {
            throw new InvalidSettingValueException(__d('rock_software_settings', 'At least {0}.', $this->min));
        }
        if ($this->max !== null && $value > $this->max) {
            throw new InvalidSettingValueException(__d('rock_software_settings', 'At most {0}.', $this->max));
        }

        return $value;
    }

    /**
     * @param mixed $input What was submitted.
     * @return string|null
     */
    private function parseChoice(mixed $input): ?string
    {
        if ($input === null || $input === '') {
            if ($this->isRequired) {
                throw new InvalidSettingValueException(__d('rock_software_settings', 'This is required.'));
            }

            return null;
        }
        if (!is_string($input) || !array_key_exists($input, $this->choices)) {
            throw new InvalidSettingValueException(__d('rock_software_settings', 'Choose one of the list.'));
        }

        return $input;
    }

    /**
     * @param \Closure|string $text A text or a closure returning one.
     * @return string
     */
    private static function resolve(Closure|string $text): string
    {
        return is_string($text) ? $text : (string)$text();
    }
}

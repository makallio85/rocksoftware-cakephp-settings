<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Service;

use ArrayAccess;

/**
 * Who changed a setting, from the identity the application's authentication
 * left on the request, without knowing the application's users table: its
 * identifier, and a name to show (its `name`, else its `email`, else `#` and
 * its identifier).
 */
final class Actor
{
    /**
     * @param string|null $identifier The identity's identifier.
     * @param string|null $name The name to show.
     */
    public function __construct(
        public readonly ?string $identifier,
        public readonly ?string $name,
    ) {
    }

    /**
     * @param mixed $identity The request's `identity` attribute, or null for nobody (code, a migration).
     * @return self
     */
    public static function fromIdentity(mixed $identity): self
    {
        if ($identity === null) {
            return new self(null, null);
        }
        $id = is_object($identity) && method_exists($identity, 'getIdentifier')
            ? $identity->getIdentifier()
            : self::field($identity, 'id');
        $identifier = is_scalar($id) && (string)$id !== '' ? (string)$id : null;

        $name = null;
        foreach (['name', 'email'] as $field) {
            $value = self::field($identity, $field);
            if (is_string($value) && trim($value) !== '') {
                $name = trim($value);
                break;
            }
        }
        if ($name === null && $identifier !== null) {
            $name = '#' . $identifier;
        }

        return new self($identifier, $name === null ? null : mb_substr($name, 0, 255));
    }

    /**
     * @param mixed $identity The identity.
     * @param string $field A field.
     * @return mixed
     */
    private static function field(mixed $identity, string $field): mixed
    {
        if (is_array($identity) || $identity instanceof ArrayAccess) {
            return $identity[$field] ?? null;
        }
        if (is_object($identity) && method_exists($identity, 'get')) {
            return $identity->get($field);
        }

        return null;
    }
}

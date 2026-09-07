<?php

namespace Modules\Reference\Domain\Enums;

/**
 * A zone's stored status.
 *
 * The second case is `inactive` in the database while the API contract says `disabled`:
 * EP-AD-034 sends `{"status": "disabled"}` and EP-AD-032 reads the field back. The two
 * vocabularies are reconciled at the boundary — {@see self::fromContract()} on the way in
 * and {@see self::toContract()} on the way out — never in the column.
 *
 * Unifying them would mean an UPDATE over every row already storing `inactive`, which is
 * a data migration. Deferred to its own ticket and recorded in BE-R03; until then this
 * enum is the single place that knows both spellings.
 */
enum ZoneStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /**
     * The word the API contract uses for this status.
     */
    public function toContract(): string
    {
        return match ($this) {
            self::Active => 'active',
            self::Inactive => 'disabled',
        };
    }

    /**
     * The stored status for a word the contract sent.
     *
     * Returns null for anything the contract does not define, so a request carrying
     * `inactive` — the stored spelling, which is not the contract's — is refused like any
     * other unknown value rather than quietly accepted through the back door.
     */
    public static function fromContract(string $value): ?self
    {
        return match ($value) {
            'active' => self::Active,
            'disabled' => self::Inactive,
            default => null,
        };
    }

    /**
     * The words the contract accepts, for validation.
     *
     * @return list<string>
     */
    public static function contractValues(): array
    {
        return array_map(fn (self $s) => $s->toContract(), self::cases());
    }
}

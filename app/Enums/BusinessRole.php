<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Employee = 'employee';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /** Owners and managers can invite people, approve invoices and edit any record. */
    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /**
     * Roles this role is allowed to give when inviting someone.
     * Nobody can invite a second owner.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => [self::Manager, self::Employee],
            self::Manager => [self::Employee],
            self::Employee => [],
        };
    }
}

<?php

namespace App\Enums;

enum UserRole: string
{
    case Member  = 'member';
    case Creator = 'creator';
    case Admin   = 'admin';

    public function isAtLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    public function rank(): int
    {
        return match ($this) {
            self::Member  => 0,
            self::Creator => 1,
            self::Admin   => 2,
        };
    }

    /**
     * Display name for the UI. Deliberately not derived from the stored value:
     * the 'creator' role is presented as "Responder".
     */
    public function label(): string
    {
        return match ($this) {
            self::Member  => 'Member',
            self::Creator => 'Responder',
            self::Admin   => 'Admin',
        };
    }
}
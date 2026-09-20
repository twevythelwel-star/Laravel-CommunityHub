<?php

namespace App\Enums;

/**
 * Mirrors `PassCategory` from src/lib/gate-pass-engine/types.ts.
 */
enum PassCategory: string
{
    case SysAdmin = 'SYSADMIN';
    case Admin = 'ADMIN';
    case Homeowner = 'HOMEOWNER';
    case Renter = 'RENTER';
    case Staff = 'STAFF';
    case Security = 'SECURITY';
    case HomeownerStaff = 'HOMEOWNER_STAFF';

    public function shape(): QRShape
    {
        return match ($this) {
            self::SysAdmin => QRShape::Star8,
            self::Admin => QRShape::Octagon,
            self::Homeowner => QRShape::Hexagon,
            self::Renter => QRShape::RoundedSquare,
            self::Staff => QRShape::Diamond,
            self::Security => QRShape::Shield,
            self::HomeownerStaff => QRShape::HouseHex,
        };
    }

    /** Pass ID prefix, per formatPassId() in the original engine. */
    public function passIdPrefix(): string
    {
        return match ($this) {
            self::SysAdmin => 'GP-SYS',
            self::Admin => 'GP-ADM',
            self::Homeowner => 'GP-HO',
            self::Renter => 'GP-RNT',
            self::Staff => 'GP-STF',
            self::Security => 'GP-SEC',
            self::HomeownerStaff => 'GP-HST',
        };
    }

    /** Default access zone assigned when none is supplied. */
    public function defaultZone(): string
    {
        return match ($this) {
            self::SysAdmin => 'ZONE-ROOT-CORE',
            self::Security => 'ZONE-ALL-PERIMETER',
            self::Admin => 'ZONE-ADMIN-COMMON',
            self::Staff => 'ZONE-FACILITIES-WORKSHOP',
            default => 'ZONE-RESIDENTIAL-AMENITIES',
        };
    }
}

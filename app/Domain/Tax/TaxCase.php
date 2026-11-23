<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Tax;

/**
 * Tax cases of ADR-0004 section 5 that are built. The legal notices still need
 * the confirmation of an accountant (A-04).
 */
enum TaxCase: string
{
    case DeStandard = 'DE_DOMESTIC_STANDARD';
    case DeReduced = 'DE_DOMESTIC_REDUCED';
    case EuB2bReverseCharge = 'EU_B2B_REVERSE_CHARGE';
    case NonEuB2bOutsideScope = 'NON_EU_B2B_OUTSIDE_SCOPE';
    case EuB2cSellerCountry = 'EU_B2C_SELLER_COUNTRY';
    case DeSmallBusiness = 'DE_SMALL_BUSINESS';
    case Exempt = 'EXEMPT';
    case BaStandard = 'BA_DOMESTIC_STANDARD';
    case BaExportService = 'BA_EXPORT_SERVICE_B2B';
    case BaNotRegistered = 'BA_NOT_REGISTERED';

    /** Identifier of the notice printed on the document, if any. */
    public function notice(): ?string
    {
        return match ($this) {
            self::EuB2bReverseCharge => 'invoices:notice.reverse_charge',
            self::NonEuB2bOutsideScope => 'invoices:notice.outside_scope_de',
            self::DeSmallBusiness => 'invoices:notice.small_business_de',
            self::BaExportService => 'invoices:notice.ba_export_service',
            self::BaNotRegistered => 'invoices:notice.ba_not_registered',
            self::Exempt => 'invoices:notice.exempt',
            default => null,
        };
    }
}

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
export type AddressData = {
    line1: string;
    line2: string | null;
    postal_code: string | null;
    city: string;
    region: string | null;
    country_code: string;
};

export type ContactData = {
    id?: string;
    first_name: string | null;
    last_name: string;
    email: string | null;
    phone: string | null;
    position: string | null;
    invoice_recipient: boolean;
};

export type SiteData = {
    id?: string;
    name: string;
    address: AddressData | null;
};

export type CompanyData = {
    id: string;
    number: string;
    name: string;
    legal_name: string | null;
    kind: string;
    status: string;
    document_locale: string;
    currency: string;
    payment_days: number;
    einvoice_format: string;
    leitweg_id: string | null;
    tax: {
        tax_status: string;
        country_code: string;
        vat_id: string | null;
        tax_number: string | null;
    } | null;
    address: AddressData | null;
    contacts: ContactData[];
    sites: SiteData[];
};

/** Choices and rights of the dialog (CompanyController::form). */
export type CompanyFormMeta = {
    kinds: string[];
    taxStatuses: string[];
    einvoiceFormats: string[];
    locales: { code: string; name: string }[];
    defaults: { country: string; currency: string; locale: string };
    can: { manage: boolean; create: boolean; contacts: boolean; tax: boolean };
};

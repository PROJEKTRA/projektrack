/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
export type Option = { value: string; text: string };

export type ProjectRow = {
    id: string;
    number: string;
    name: string;
    company: string;
    status: string;
    billing_model: string;
    budget_minutes: number | null;
    booked_minutes: number;
    health: 'green' | 'amber' | 'red';
};

export type ContractData = {
    id: string;
    number: string;
    contract_type: string;
    status: string;
    start_date: string | null;
    end_date: string | null;
    budget_minutes: number | null;
    rounding_minutes: number;
    minimum_minutes: number;
};

export type PackageData = {
    id: string;
    parent_id: string | null;
    contract_id: string | null;
    wbs_code: string;
    name: string;
    status: string;
    planned_minutes: number | null;
    billable: boolean;
    service_category: string | null;
    start_date: string | null;
    end_date: string | null;
    booked_minutes: number;
};

export type ProjectDetails = ProjectRow & {
    company_id: string;
    currency: string;
    amber_percent: number;
    start_date: string | null;
    end_date: string | null;
    budget_minor?: number | null;
    members: {
        id: string;
        name: string;
        role: string;
        left_on: string | null;
    }[];
    contracts: ContractData[];
    work_packages: PackageData[];
};

export type ProjectFormMeta = {
    companies: Option[];
    billingModels: string[];
    statuses: string[];
    currency: string;
    can: {
        edit: boolean;
        money: boolean;
        members: boolean;
        contracts: boolean;
        wbs: boolean;
    };
};

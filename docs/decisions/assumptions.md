# Assumptions and confirmed decisions

Status as of 17.11.2026 (Phase 0). Each entry is either **confirmed** by the product owner or an **assumption** made to keep moving. Assumptions can be overturned; overturning one is logged here with date and impact.

## Confirmed decisions

| ID | Decision | Date | Source |
|---|---|---|---|
| C-01 | Development runs on a dedicated dev server or VM, not on the workstation. | 16.11.2026 | Product owner |
| C-02 | Source code lives in a **private repository in a PROJEKTRA GitHub organization**; CI on GitHub Actions, images on GHCR. Community edition is published as open source at 1.0. | 16.11.2026 | Product owner |
| C-03 | The first customer license (1.12.2026) is delivered as a **managed dedicated instance on PROJEKTRA servers**. The AMP ZIP and Docker paths still ship in 0.9 but the managed instance is the delivery critical path. | 16.11.2026 | Product owner |
| C-04 | Projektrack is **AI native**, but AI is **optional and off by default**. No data reaches an AI provider until a tenant admin enables it; customer-facing or financial output needs human approval. First AI features ship in 1.0 (ADR-0009). | 16.11.2026 | Product owner |
| C-05 | Product documentation, release notes, commits and pull requests never name the development tooling used to write them. Local working files for tooling are excluded from the repository via `.gitignore`. | 16.11.2026 | Product owner |
| C-06 | **0.9 is delivered as early access plus staged releases.** On 1.12.2026 the Founding Customer license is sold and signed, and the managed instance is delivered with a "0.9.0 Early Access" build containing only what passed the full Definition of Done by the go/no-go. Function follows in 0.9.1 to 0.9.4. The 12 low-risk cuts (212 h) in `docs/stories/0.9/README.md` are applied. Should items move out of 0.9. Quality and security scope is unchanged. 0.10 and 1.0 are replanned after the estimate recalibration on 21.11.2026. The phased items P1 (e-invoicing) and P2 (manual sale flow) remain open. | 17.11.2026 | Product owner |
| C-07 | **Development runs on the PROJEKTRA development workstation** (Windows, per-user toolchain: PHP 8.4 with Composer, Node LTS, Git, GitHub CLI). The full database matrix (MySQL, MariaDB, PostgreSQL) runs in GitHub Actions only. Replaces the dev server of C-01. | 17.11.2026 | Product owner |
| C-08 | Repository: `PROJEKTRA/projektrack` on GitHub, private. | 17.11.2026 | Product owner |
| C-09 | **PROJEKTRA's existing 1blu webhosting is the test and production environment for the PROJEKTRA instance**, running the AMP profile (no Docker, no Redis, cron-driven queue). This makes PROJEKTRA's own instance the reference installation for plain hosting. The license signing private key is never stored on this host; licenses are signed offline or on a separate machine. | 17.11.2026 | Product owner |
| C-10 | **Releases are approved by signing on a maintainer's machine**, not by an approval on GitHub, whose plan for this private repository cannot enforce one. One Ed25519 release key covers ZIP and image; cosign is not used (S-0924). | 23.11.2026 | Product owner |
| C-11 | **Online activation and the daily check-in are deferred** (cut 4 of 17.11.2026 confirmed for S-0976 and S-0977). On 1.12.2026 a license is installed from a file. Seat warnings, soft enforcement and edition gating stay in S-0977. | 23.11.2026 | Product owner |

## Assumptions

| ID | Assumption | Impact if wrong | Owner to confirm |
|---|---|---|---|
| A-01 | Dev server details (host, OS, access) are provided before Sprint L1 starts on 18.11.2026. Ubuntu 24.04 LTS with Docker is the reference. **Resolved 17.11.2026** by C-07 (workstation) and C-09 (1blu hosting for the PROJEKTRA instance). | None. | Product owner |
| A-02 | Logo SVGs ("P" mark, PROJEKTRA wordmark) are supplied by PROJEKTRA. Until then a neutral placeholder is used and listed as open item. | Brand theme incomplete at 0.9. | PROJEKTRA |
| A-03 | The first paying customer is the pilot customer Learnteq UG (haftungsbeschränkt), Stuttgart, buying the Founding Customer plan (Business edition) through sales channel 1 (invoice and bank transfer). | License, invoice and tax case (BA seller to DE business: reverse charge notice) need adjustment. | Product owner |
| A-04 | PROJEKTRA invoices to EU business customers are issued in EUR, in English, without BiH PDV, with a place-of-supply notice. The exact legal wording is confirmed by PROJEKTRA's accountant before the first real invoice. | Invoice template change before 1.12. | finance-tax-expert, accountant |
| A-05 | Fiscalization in the Federation of BiH is not required for PROJEKTRA's B2B export service invoices; until confirmed, invoices can be marked "issued via external fiscal system" with a fiscal reference. | A fiscal adapter becomes a 0.9 blocker. | Accountant |
| A-06 | Stack versions as pinned in ADR-0001 (checked 16.11.2026): Laravel 13 on PHP 8.4 minimum (8.5 recommended), Vue 3.5, Inertia 3, Tailwind 4, MySQL 8.4 LTS, MariaDB 10.11 or 11.4 LTS, PostgreSQL 16 or newer. Patch versions are re-checked when the repo is scaffolded on the dev server. | ADR-0001 updated; no design impact. | solution-architect |
| A-07 | The PROJEKTRA production instance runs on a VPS in an EU data center (Germany) operated by PROJEKTRA, with Docker Compose. **Superseded 17.11.2026 by C-09** for the PROJEKTRA instance (1blu webhosting, AMP profile). Still open: where the first customer's managed dedicated instance runs (C-03). A shared webhosting package cannot isolate a customer instance from PROJEKTRA's own; a separate hosting package or a VPS per customer is needed before hardening (29.11.2026). | Infrastructure profile and backup plan change. | Product owner |
| A-08 | `bs` uses Latin script. Cyrillic is out of scope for 0.9. | Additional locale `bs-Cyrl` later. | i18n-localization-expert |
| A-09 | Default document numbering for PROJEKTRA: `{YYYY}-{####}` per document type, reset yearly (e.g. invoice `INV-2026-0001`). | Numbering sequence configuration changes. | Product owner |
| A-10 | Exchange rates BAM/EUR use the fixed currency board rate 1 EUR = 1.95583 BAM; other currencies are entered manually in 0.9. | Rate import needed earlier. | finance-tax-expert |
| A-11 | The 30-day Business trial and the self-service checkout are not part of 0.9; channel 1 (direct sale) only. | None for 0.9. | product-owner |
| A-12 | Peak load for 0.9: fewer than 50 users per tenant and fewer than 10 tenants per instance. Performance budget of section 8 is verified from 1.0. | Earlier performance work. | solution-architect |

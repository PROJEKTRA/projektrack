<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Documents\Pdf;

final class InvoicePdf
{
    public function __construct(private readonly Pdf $pdf) {}

    /** The issued document, with its sealed words. */
    public function render(Invoice $invoice): string
    {
        $content = $invoice->content();
        $format = $content['format'] ?? null;

        return $this->pdf->render('invoices::invoice', ['i' => $content], $invoice->locale, (string) $invoice->number, is_array($content['labels'] ?? null) ? $content['labels'] : [], is_string($format) ? $format : null);
    }
}

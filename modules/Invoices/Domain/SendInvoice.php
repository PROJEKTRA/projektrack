<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\I18n\Strings;
use App\Models\Tenant;
use App\Notifications\AppNotice;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Renders and mails one invoice. Texts are in the language of the document.
 */
class SendInvoice implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $deliveryId,
    ) {
        $this->afterCommit();
    }

    public function handle(TenantContext $tenants): void
    {
        $tenants->run(Tenant::query()->findOrFail($this->tenantId), function (): void {
            $delivery = InvoiceDelivery::query()->findOrFail($this->deliveryId);
            if ($delivery->status === 'sent') {
                return;
            }
            $invoice = Invoice::query()->findOrFail($delivery->invoice_id);
            $content = $invoice->content();
            $strings = app(Strings::class);
            $args = AppNotice::escapeMarkdown(['number' => (string) $invoice->number, 'seller' => is_array($content['seller'] ?? null) && is_string($content['seller']['name'] ?? null) ? $content['seller']['name'] : '']);
            $mail = (new MailMessage)
                ->subject($strings->get('invoices:send.subject', ['number' => (string) $invoice->number], $invoice->locale))
                ->greeting($strings->get('invoices:send.greeting', [], $invoice->locale))
                ->line($strings->get('invoices:send.body', $args, $invoice->locale))
                ->salutation(is_string($args['seller']) ? $args['seller'] : '')
                ->attachData(app(InvoicePdf::class)->render($invoice), $invoice->number.'.pdf', ['mime' => 'application/pdf']);
            // An XRechnung is the XML; the PDF is only its picture.
            if (is_string($content['xml'] ?? null) && is_array($content['buyer'] ?? null) && in_array($content['buyer']['einvoice_format'] ?? null, ['xrechnung_cii', 'xrechnung_ubl'], true)) {
                $mail->attachData($content['xml'], $invoice->number.'.xml', ['mime' => 'application/xml']);
            }
            Notification::route('mail', $delivery->recipient)->notifyNow(new InvoiceMail($mail));
            $delivery->update(['status' => 'sent']);
        });
    }

    public function failed(?Throwable $e): void
    {
        $tenants = app(TenantContext::class);
        $tenants->run(Tenant::query()->findOrFail($this->tenantId), fn () => InvoiceDelivery::query()->whereKey($this->deliveryId)->update(['status' => 'failed']));
    }
}

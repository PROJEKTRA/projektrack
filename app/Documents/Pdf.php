<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Documents;

use App\I18n\Formats;
use App\I18n\RegionalFormat;
use App\I18n\Strings;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use NumberFormatter;

/**
 * A Blade view as PDF in the language of the document, without any service
 * outside PHP (S-0954, S-0964). Right to left languages are mirrored.
 */
final class Pdf
{
    public function __construct(
        private readonly Strings $strings,
        private readonly TenantContext $tenant,
    ) {}

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data, string $locale, string $title): string
    {
        $format = $this->format($locale);
        $temp = storage_path('framework/pdf');
        File::ensureDirectoryExists($temp, 0700);
        $html = View::make($view, [
            ...$data,
            'format' => $format,
            'dir' => $this->strings->direction($locale),
            't' => fn (string $id, array $args = []): string => $this->strings->get($id, $args, $locale),
            'decimal' => fn (string $value, int $least = 2): string => self::decimal($value, $least, $format->locale),
            'hours' => fn (int $minutes): string => ($minutes < 0 ? '-' : '').intdiv(abs($minutes), 60).':'.sprintf('%02d', abs($minutes) % 60),
        ])->render();

        $pdf = new Mpdf(['tempDir' => $temp, 'default_font' => 'dejavusans', 'margin_top' => 18, 'margin_bottom' => 22, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $pdf->SetTitle($title);
        // The product name stays out of the file.
        $pdf->SetCreator('');
        $pdf->SetDirectionality($this->strings->direction($locale));
        $pdf->SetFooter('{PAGENO} / {nbpg}');
        $pdf->WriteHTML($html);

        return (string) $pdf->Output('', Destination::STRING_RETURN);
    }

    public function format(string $locale): RegionalFormat
    {
        return new RegionalFormat(Formats::defaultFor($locale), $this->tenant->current()->timezone);
    }

    /** A decimal string with the separator of the language; never through a float. */
    private static function decimal(string $value, int $least, string $locale): string
    {
        [$whole, $fraction] = [...explode('.', $value, 2), ''];
        $fraction = str_pad(rtrim($fraction, '0'), $least, '0');
        $separator = (new NumberFormatter($locale, NumberFormatter::DECIMAL))->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

        return $fraction === '' ? $whole : $whole.(is_string($separator) ? $separator : '.').$fraction;
    }
}

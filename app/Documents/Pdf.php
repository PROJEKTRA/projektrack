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
    /** Tells readers that the file holds an e-invoice (Factur-X 1.0, profile EN 16931). */
    private const string FACTUR_X = <<<'XML'
        <rdf:Description rdf:about="" xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#">
          <fx:DocumentType>INVOICE</fx:DocumentType><fx:DocumentFileName>factur-x.xml</fx:DocumentFileName><fx:Version>1.0</fx:Version><fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>
        </rdf:Description>
        <rdf:Description rdf:about="" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#" xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">
          <pdfaExtension:schemas><rdf:Bag><rdf:li rdf:parseType="Resource">
            <pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema><pdfaSchema:namespaceURI>urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#</pdfaSchema:namespaceURI><pdfaSchema:prefix>fx</pdfaSchema:prefix>
            <pdfaSchema:property><rdf:Seq>
              <rdf:li rdf:parseType="Resource"><pdfaProperty:name>DocumentFileName</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>name of the embedded XML invoice file</pdfaProperty:description></rdf:li>
              <rdf:li rdf:parseType="Resource"><pdfaProperty:name>DocumentType</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>INVOICE</pdfaProperty:description></rdf:li>
              <rdf:li rdf:parseType="Resource"><pdfaProperty:name>Version</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>version of the XML data</pdfaProperty:description></rdf:li>
              <rdf:li rdf:parseType="Resource"><pdfaProperty:name>ConformanceLevel</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>conformance level of the XML data</pdfaProperty:description></rdf:li>
            </rdf:Seq></pdfaSchema:property>
          </rdf:li></rdf:Bag></pdfaExtension:schemas>
        </rdf:Description>
        XML;

    public function __construct(
        private readonly Strings $strings,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * The texts of a document as they are now, to seal them with it.
     *
     * @param  list<string>  $prefixes
     * @return array<string, string> identifier => message; missing texts are left out
     */
    public function labels(array $prefixes, string $locale): array
    {
        $labels = [];
        foreach (array_keys($this->strings->catalogMessages('en')) as $id) {
            if (array_any($prefixes, fn (string $p): bool => str_starts_with($id, $p))) {
                $found = $this->strings->resolve($id, [$locale], false);
                if ($found !== null) {
                    $labels[$id] = $found[0];
                }
            }
        }

        return $labels;
    }

    /**
     * Whether the language has every text of the document, so no other language slips in.
     *
     * @param  list<string>  $prefixes
     */
    public function speaks(array $prefixes, string $locale): bool
    {
        return count($this->labels($prefixes, $locale)) === count($this->labels($prefixes, 'en'));
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     * @param  array<array-key, mixed>  $labels  sealed texts; others are read now
     */
    public function render(string $view, array $data, string $locale, string $title, array $labels = [], ?string $formatLocale = null, ?string $facturX = null): string
    {
        $format = $formatLocale === null ? $this->format($locale) : new RegionalFormat($formatLocale, $this->tenant->current()->timezone, Formats::datePattern($formatLocale));
        $temp = storage_path('framework/pdf');
        File::ensureDirectoryExists($temp, 0700);
        $html = View::make($view, [
            ...$data,
            'format' => $format,
            'dir' => $this->strings->direction($locale),
            // The PDF library does not know "end".
            'end' => $this->strings->direction($locale) === 'rtl' ? 'left' : 'right',
            't' => function (string $id, array $args = []) use ($labels, $locale): string {
                /** @var array<string, mixed> $args */
                $sealed = $labels[$id] ?? null;

                return is_string($sealed) ? ($this->strings->format($id, $sealed, $args, $locale) ?? $sealed) : $this->strings->get($id, $args, $locale);
            },
            'decimal' => fn (string $value, int $least = 2): string => self::decimal($value, $least, $format->locale),
            'hours' => fn (int $minutes): string => ($minutes < 0 ? '-' : '').intdiv(abs($minutes), 60).':'.sprintf('%02d', abs($minutes) % 60),
        ])->render();

        $pdf = new Mpdf(['tempDir' => $temp, 'default_font' => 'dejavusans', 'exposeVersion' => false, 'margin_top' => 18, 'margin_bottom' => 22, 'autoScriptToLang' => true, 'autoLangToFont' => true, ...($facturX === null ? [] : ['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B'])]);
        if ($facturX !== null) {
            $pdf->SetAssociatedFiles([['name' => 'factur-x.xml', 'mime' => 'text/xml', 'description' => 'Factur-X', 'AFRelationship' => 'Alternative', 'content' => $facturX]]);
            $pdf->SetAdditionalXmpRdf(self::FACTUR_X);
        }
        $pdf->SetTitle($title);
        // The product name stays out of the file.
        $pdf->SetCreator('');
        $pdf->SetDirectionality($this->strings->direction($locale));
        $pdf->SetFooter('{PAGENO} / {nbpg}');
        $pdf->WriteHTML($html);

        /** @var string $bytes */
        $bytes = $pdf->Output('', Destination::STRING_RETURN);

        return $bytes;
    }

    public function format(string $locale): RegionalFormat
    {
        $format = Formats::defaultFor($locale);

        return new RegionalFormat($format, $this->tenant->current()->timezone, Formats::datePattern($format));
    }

    /** A decimal string with the separator of the language; never through a float. */
    public static function decimal(string $value, int $least, string $locale): string
    {
        [$whole, $fraction] = [...explode('.', $value, 2), ''];
        $fraction = str_pad(rtrim($fraction, '0'), $least, '0');
        $symbols = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $separator = $symbols->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $whole = (string) preg_replace('/(?<=\d)(?=(\d{3})+\z)/', $symbols->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL), $whole);

        return $fraction === '' ? $whole : $whole.$separator.$fraction;
    }
}

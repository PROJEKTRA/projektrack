<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports\Domain;

use App\Documents\Pdf;
use App\I18n\Formats;
use App\I18n\RegionalFormat;
use App\I18n\Strings;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * A service report as a Word document (S-0955, built-in template). It shows
 * the sealed content; the PDF stays the reference.
 */
final class ReportDoc
{
    public function __construct(
        private readonly Strings $strings,
        private readonly Pdf $pdf,
        private readonly TenantContext $tenant,
    ) {}

    public function render(ServiceReport $report): string
    {
        Settings::setOutputEscapingEnabled(true);
        $c = $report->content();
        $labels = is_array($c['labels'] ?? null) ? $c['labels'] : [];
        $t = fn (string $id): string => is_string($labels[$id] ?? null) ? $labels[$id] : $this->strings->get($id, [], $report->locale);
        $format = is_string($c['format'] ?? null) ? new RegionalFormat($c['format'], $this->tenant->current()->timezone, Formats::datePattern($c['format'])) : $this->pdf->format($report->locale);
        $rtl = $this->strings->direction($report->locale) === 'rtl';

        $word = new PhpWord;
        $word->getDocInfo()->setCreator('')->setLastModifiedBy('')->setTitle($report->number);
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);
        $section = $word->addSection(['bidi' => $rtl]);
        $section->addText($t('service_reports:document.title').' '.$report->number, ['size' => 16, 'bold' => true, 'rtl' => $rtl], ['bidi' => $rtl]);
        foreach (['from' => self::text($c, 'seller'), 'customer' => self::text($c, 'company'), 'project' => self::text($c, 'project'), 'period' => $format->day(self::text($c, 'from')).' - '.$format->day(self::text($c, 'to'))] as $key => $value) {
            $section->addText($t("service_reports:document.{$key}").': '.$value, ['rtl' => $rtl], ['bidi' => $rtl]);
        }
        $section->addTextBreak();

        $heads = ['date', 'user', 'package', 'description', 'hours', ...($report->with_amounts ? ['rate', 'amount'] : [])];
        $table = $this->table($section, $rtl);
        $this->row($table, array_map(fn (string $h): string => $t("service_reports:column.{$h}"), $heads), true, $rtl, 4);
        foreach (is_array($c['lines'] ?? null) ? $c['lines'] : [] as $line) {
            if (is_array($line)) {
                $minor = $line['amount_minor'] ?? null;
                $currency = $line['currency'] ?? null;
                $cells = [$format->day(self::text($line, 'date')), self::text($line, 'user'), self::text($line, 'package'), self::text($line, 'description'), self::hours($line['minutes'] ?? 0)];
                $this->row($table, $report->with_amounts ? [...$cells, self::text($line, 'rate') === '' ? '' : Pdf::decimal(self::text($line, 'rate'), 2, $format->locale), is_int($minor) && is_string($currency) ? $format->money($minor, $currency) : ''] : $cells, false, $rtl, 4);
            }
        }
        foreach (['package', 'user'] as $by) {
            $section->addTextBreak();
            $table = $this->table($section, $rtl);
            $this->row($table, [$by === 'package' ? $t('service_reports:document.per_package') : $t('service_reports:document.per_user'), $t('service_reports:column.hours')], true, $rtl);
            $totals = is_array($c['totals'] ?? null) && is_array($c['totals'][$by] ?? null) ? $c['totals'][$by] : [];
            foreach ($totals as $name => $minutes) {
                $this->row($table, [(string) $name, self::hours($minutes)], false, $rtl);
            }
            $this->row($table, [$t('service_reports:document.total'), self::hours($c['minutes'] ?? 0)], true, $rtl);
        }
        foreach (is_array($c['amounts'] ?? null) ? $c['amounts'] : [] as $currency => $minor) {
            if (is_int($minor)) {
                $section->addText($t('service_reports:document.amount').': '.$format->money($minor, (string) $currency), ['bold' => true, 'rtl' => $rtl], ['bidi' => $rtl]);
            }
        }
        $section->addTextBreak(3);
        $sign = $section->addTable(['bidiVisual' => $rtl, 'width' => 5000, 'unit' => 'pct']);
        foreach ([['____________________________', '____________________________'], [self::text($c, 'seller'), self::text($c, 'company')]] as $cells) {
            $this->row($sign, $cells, false, $rtl, 2);
        }

        $folder = storage_path('framework/sheets');
        File::ensureDirectoryExists($folder, 0700);
        Settings::setTempDir($folder);
        $file = (string) tempnam($folder, 'doc');
        try {
            (new Word2007($word))->save($file);

            return (string) file_get_contents($file);
        } finally {
            @unlink($file);
        }
    }

    private function table(Section $section, bool $rtl): Table
    {
        return $section->addTable(['borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 60, 'bidiVisual' => $rtl, 'width' => 5000, 'unit' => 'pct']);
    }

    /**
     * @param  list<string>  $cells
     * @param  int  $numbers  from this column on numbers stand at the end
     */
    private function row(Table $table, array $cells, bool $bold, bool $rtl, int $numbers = 1): void
    {
        $table->addRow(null, ['cantSplit' => true]);
        foreach ($cells as $i => $text) {
            $table->addCell()->addText($text, ['bold' => $bold, 'rtl' => $rtl], ['bidi' => $rtl, 'spaceAfter' => 0, 'alignment' => $i >= $numbers ? Jc::END : Jc::START]);
        }
    }

    /** @param array<array-key, mixed> $data */
    private static function text(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : '';
    }

    private static function hours(mixed $minutes): string
    {
        $value = is_int($minutes) ? $minutes : 0;

        return ($value < 0 ? '-' : '').intdiv(abs($value), 60).':'.sprintf('%02d', abs($value) % 60);
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports\Domain;

use App\Domain\Money\Amount;
use App\I18n\Strings;
use Brick\Money\Currency;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * A service report as a workbook. Totals are formulas; every text is written
 * as text, so nothing a person typed runs as a formula.
 */
final class ReportSheet
{
    public function __construct(private readonly Strings $strings) {}

    public function render(ServiceReport $report): string
    {
        $content = $report->content();
        $text = fn (string $id): string => $this->strings->get($id, [], $report->locale);
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('')->setLastModifiedBy('')->setTitle($report->number);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($report->number);
        $sheet->setRightToLeft($this->strings->direction($report->locale) === 'rtl');

        $heads = ['date', 'user', 'package', 'description', 'hours', ...($report->with_amounts ? ['rate', 'amount'] : [])];
        foreach ($heads as $i => $head) {
            $sheet->getCell([$i + 1, 1])->setValueExplicit($text("service_reports:column.{$head}"), DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $row = 1;
        $amounts = [];
        foreach (is_array($content['lines'] ?? null) ? $content['lines'] : [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            $row++;
            foreach (['date', 'user', 'package', 'description'] as $i => $key) {
                $value = $line[$key] ?? '';
                $sheet->getCell([$i + 1, $row])->setValueExplicit(is_scalar($value) ? (string) $value : '', DataType::TYPE_STRING);
            }
            // A fraction of a day, shown as hours and minutes.
            $minutes = is_int($line['minutes'] ?? null) ? $line['minutes'] : 0;
            $sheet->getCell([5, $row])->setValue("={$minutes}/1440");
            $minor = $line['amount_minor'] ?? null;
            $currency = $line['currency'] ?? null;
            if (is_int($minor) && is_string($currency) && is_string($line['rate'] ?? null)) {
                $sheet->getCell([6, $row])->setValueExplicit($line['rate'], DataType::TYPE_NUMERIC);
                $sheet->getCell([7, $row])->setValueExplicit(Amount::decimal($minor, $currency), DataType::TYPE_NUMERIC);
                $sheet->getCell([7, $row])->getStyle()->getNumberFormat()->setFormatCode('#,##0'.(($digits = Currency::of($currency)->getDefaultFractionDigits()) > 0 ? '.'.str_repeat('0', $digits) : '').' "'.$currency.'"');
                $amounts[$currency] = true;
            }
        }
        $total = $row + 1;
        $sheet->getCell([1, $total])->setValueExplicit($text('service_reports:document.total'), DataType::TYPE_STRING);
        $sheet->getCell([5, $total])->setValue("=SUM(E2:E{$row})");
        // Amounts of several currencies are never added up.
        if (count($amounts) === 1) {
            $sheet->getCell([7, $total])->setValue("=SUM(G2:G{$row})");
            $sheet->getCell([7, $total])->getStyle()->getNumberFormat()->setFormatCode($sheet->getCell([7, 2])->getStyle()->getNumberFormat()->getFormatCode() ?? NumberFormat::FORMAT_GENERAL);
        }
        $sheet->getStyle("E2:E{$total}")->getNumberFormat()->setFormatCode('[h]:mm');
        $sheet->getStyle("A{$total}:G{$total}")->getFont()->setBold(true);
        foreach (['A' => 12, 'B' => 24, 'C' => 30, 'D' => 50, 'E' => 10, 'F' => 12, 'G' => 16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A2');

        $file = tempnam(sys_get_temp_dir(), 'sheet');
        try {
            (new Xlsx($book))->save((string) $file);

            return (string) file_get_contents((string) $file);
        } finally {
            @unlink((string) $file);
            $book->disconnectWorksheets();
        }
    }
}

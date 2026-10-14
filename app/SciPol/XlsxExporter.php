<?php

namespace App\SciPol;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class XlsxExporter
{
    /**
     * @param  array{
     *   criteria: string,
     *   subjects: list<array<string, mixed>>,
     *   axes: list<array<string, mixed>>,
     *   stances: list<array<string, mixed>>,
     *   affiliations: list<array<string, mixed>>,
     *   records: list<array<string, mixed>>
     * }  $bundle
     */
    public function download(array $bundle, string $filename = 'scipol-extract.xlsx'): BinaryFileResponse
    {
        $retrievedAt = now()->toIso8601String();
        $codebook = Codebook::rows();

        $tmp = tempnam(sys_get_temp_dir(), 'scipol');
        $path = $tmp.'.xlsx';
        @unlink($tmp);

        $writer = new Writer;
        $writer->openToFile($path);

        $metaSheet = $writer->getCurrentSheet();
        $metaSheet->setName('Meta');
        $this->writeRows($writer, [
            ['key', 'value'],
            ['product', 'PolitiSEE SciPol'],
            ['retrieved_at', $retrievedAt],
            ['archive_url', url('/')],
            ['scipol_url', url('/scipol')],
            ['criteria', $bundle['criteria']],
            ['attribution', 'Extract from published PolitiSEE archive data.'],
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Codebook');
        $this->writeRows($writer, array_merge(
            [['field', 'type', 'values', 'description']],
            array_map(fn (array $row) => [
                $row['field'],
                $row['type'],
                $row['values'],
                $row['description'],
            ], $codebook)
        ));

        $writer->addNewSheetAndMakeItCurrent()->setName('Subjects');
        $this->writeAssociativeSheet($writer, $bundle['subjects'], [
            'kind', 'id', 'name', 'href', 'position', 'institution', 'party', 'record_total',
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Axes');
        $this->writeAssociativeSheet($writer, $bundle['axes'], [
            'subject_kind', 'subject_id', 'subject_name', 'axis', 'axis_label', 'score', 'samples', 'note', 'source_url', 'source_type',
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Stances');
        $this->writeAssociativeSheet($writer, $bundle['stances'], [
            'subject_kind', 'subject_id', 'subject_name', 'issue', 'position', 'note', 'source_url', 'source_type', 'occurred_on',
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Affiliations');
        $this->writeAssociativeSheet($writer, $bundle['affiliations'], [
            'subject_kind', 'subject_id', 'subject_name', 'business_id', 'business_name', 'sector', 'type', 'ownership_percent', 'summary', 'source_url', 'source_type', 'occurred_on',
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Records');
        $this->writeAssociativeSheet($writer, $bundle['records'], [
            'record_id', 'subject_kind', 'subject_id', 'subject_name', 'category', 'summary', 'quote', 'source_url', 'source_type', 'date', 'topics',
        ]);

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function writeRows(Writer $writer, array $rows): void
    {
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(
                fn ($value) => $this->cell($value),
                $row
            )));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function writeAssociativeSheet(Writer $writer, array $rows, array $columns): void
    {
        $this->writeRows($writer, [$columns]);
        foreach ($rows as $row) {
            $this->writeRows($writer, [array_map(
                fn (string $column) => $row[$column] ?? null,
                $columns
            )]);
        }
    }

    private function cell(mixed $value): bool|float|int|string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        return (string) $value;
    }
}

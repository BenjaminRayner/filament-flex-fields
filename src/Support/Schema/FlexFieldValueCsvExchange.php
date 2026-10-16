<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\Values\Csv\CsvValueCodec;
use Generator;
use InvalidArgumentException;
use RuntimeException;

/** CSV transport; typed values preserve null, strings, booleans and nested structures. */
final class FlexFieldValueCsvExchange
{
    private const MAX_IMPORT_BYTES = 16 * 1024 * 1024;

    private const MAX_IMPORT_ROWS = 100000;

    /**
     * @param  iterable<FlexFieldDefinition>  $definitions
     * @param  iterable<object>  $records
     */
    public function export(iterable $definitions, iterable $records, string $valuesColumn = '', string $profile = 'typed'): string
    {
        return implode('', iterator_to_array($this->exportRows($definitions, $records, $valuesColumn, $profile), false));
    }

    /**
     * Streaming counterpart: emits one correctly quoted CSV record at a time.
     *
     * @param  iterable<FlexFieldDefinition>  $definitions
     * @param  iterable<object>  $records
     * @return Generator<int, string>
     */
    public function exportRows(iterable $definitions, iterable $records, string $valuesColumn = '', string $profile = 'typed'): Generator
    {
        if (! in_array($profile, ['typed', 'legacy', 'spreadsheet'], true)) {
            throw new InvalidArgumentException('Unknown CSV export profile.');
        }

        $definitions = $this->definitionsBySlug($definitions);
        $column = $valuesColumn !== '' ? $valuesColumn : FlexFieldsConfig::getValuesColumn();
        $headers = ['id', ...array_map(strval(...), array_keys($definitions))];
        yield $this->row($profile === 'spreadsheet' ? array_map(CsvValueCodec::spreadsheetSafe(...), $headers) : $headers);

        foreach ($records as $record) {
            $id = (string) (method_exists($record, 'getKey') ? $record->getKey() : ($record->id ?? ''));
            $row = [$profile === 'spreadsheet' ? CsvValueCodec::spreadsheetSafe($id) : $id];

            foreach ($definitions as $definition) {
                $row[] = CsvValueCodec::encode(data_get($record, "{$column}.{$definition->slug}"), $definition, $profile);
            }

            yield $this->row($row);
        }
    }

    /**
     * @param  iterable<FlexFieldDefinition>  $definitions
     * @return array<string, array<string, mixed>>
     */
    public function import(string $csv, iterable $definitions, bool $ignoreUnknownColumns = false): array
    {
        if (strlen($csv) > self::MAX_IMPORT_BYTES) {
            throw new InvalidArgumentException('CSV import exceeds 16 MiB.');
        }

        $definitions = $this->definitionsBySlug($definitions);
        $stream = fopen('php://temp/maxmemory:2097152', 'r+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to allocate CSV parser stream.');
        }

        try {
            if (fwrite($stream, $csv) !== strlen($csv) || ! rewind($stream)) {
                throw new RuntimeException('Unable to prepare CSV parser stream.');
            }

            $header = fgetcsv($stream, null, ',', '"', '');

            if ($header === false) {
                return [];
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));

            if ($header[0] !== 'id' || count(array_unique($header)) !== count($header)) {
                throw new InvalidArgumentException('CSV needs a unique id header and unique field headers.');
            }

            foreach (array_slice($header, 1) as $slug) {
                if (! is_string($slug) || (! isset($definitions[$slug]) && ! $ignoreUnknownColumns)) {
                    throw new InvalidArgumentException('CSV contains an empty or unknown field header.');
                }
            }

            $payload = [];
            $rowNumber = 1;

            while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($cells === [null]) {
                    continue;
                }

                $rowNumber++;

                if ($rowNumber > self::MAX_IMPORT_ROWS + 1 || count($cells) !== count($header)) {
                    throw new InvalidArgumentException("Invalid CSV width or row limit at record {$rowNumber}.");
                }

                $id = (string) ($cells[0] ?? '');

                if ($id === '' || array_key_exists($id, $payload)) {
                    throw new InvalidArgumentException("Missing or duplicate record id at CSV record {$rowNumber}.");
                }

                $values = [];

                foreach (array_slice($header, 1, null, true) as $index => $slug) {
                    if (is_string($slug) && isset($definitions[$slug])) {
                        $values[$slug] = CsvValueCodec::decode($cells[$index], $definitions[$slug]);
                    }
                }

                $payload[$id] = $values;
            }

            return $payload;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  iterable<FlexFieldDefinition>  $definitions
     * @return array<string, FlexFieldDefinition>
     */
    private function definitionsBySlug(iterable $definitions): array
    {
        $indexed = [];

        foreach ($definitions as $definition) {
            if ($definition->slug === '' || $definition->slug === 'id' || isset($indexed[$definition->slug])) {
                throw new InvalidArgumentException('CSV field slugs must be unique and cannot be id.');
            }

            $indexed[$definition->slug] = $definition;
        }

        return $indexed;
    }

    /** @param list<string> $values */
    private function row(array $values): string
    {
        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to allocate CSV writer.');
        }

        try {
            if (fputcsv($stream, $values, ',', '"', '', "\n") === false || ! rewind($stream)) {
                throw new RuntimeException('Unable to encode CSV record.');
            }

            $row = stream_get_contents($stream);

            if ($row === false) {
                throw new RuntimeException('Unable to read encoded CSV record.');
            }

            return $row;
        } finally {
            fclose($stream);
        }
    }
}

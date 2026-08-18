<?php
namespace App\Domain\Imports;

use Generator;
use RuntimeException;

final class CsvStreamReader
{
    /** @return Generator<int,array<string,string|null>> */
    public function rows(string $path, string $delimiter = ','): Generator
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException("Unable to open {$path}");
        }
        try {
            yield from $this->rowsFromStream($handle, $delimiter);
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle @return Generator<int,array<string,string|null>> */
    public function rowsFromStream($handle, string $delimiter = ','): Generator
    {
        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            return;
        }
        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        if (count($headers) !== count(array_unique($headers))) {
            throw new RuntimeException('CSV contains duplicate header names');
        }
        $rowNumber = 1;
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if (count($values) !== count($headers)) {
                throw new RuntimeException("Column count mismatch at row {$rowNumber}");
            }
            yield $rowNumber => array_combine($headers, $values);
        }
    }

    /** @param resource $handle @return array{headers:list<string>,sample:list<array<string,string|null>>,count:int} */
    public function analyzeStream($handle, int $sampleSize = 100): array
    {
        $sample = [];
        $count = 0;
        $headers = [];
        foreach ($this->rowsFromStream($handle) as $row) {
            $count++;
            if ($headers === []) {
                $headers = array_keys($row);
            }
            if (count($sample) < $sampleSize) {
                $sample[] = $row;
            }
        }
        return ['headers' => $headers, 'sample' => $sample, 'count' => $count];
    }

    /** @return array{headers:list<string>,sample:list<array<string,string|null>>,count:int} */
    public function analyze(string $path, int $sampleSize = 100): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException("Unable to open {$path}");
        }
        try {
            return $this->analyzeStream($handle, $sampleSize);
        } finally {
            fclose($handle);
        }
    }
}

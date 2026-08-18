<?php
namespace App\Domain\Imports;use InvalidArgumentException;
final class SourceReaderFactory{public function make(string $type):SourceReader{return match(strtolower($type)){'csv'=>new CsvSourceReader(),'ndjson','jsonl'=>new NdjsonSourceReader(),'xlsx'=>new XlsxSourceReader(),default=>throw new InvalidArgumentException("Unsupported source type: {$type}")};}}

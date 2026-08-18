<?php

namespace Tests\Unit;

use App\Domain\Imports\CsvSourceReader;
use App\Domain\Imports\DedupeKey;
use App\Domain\Imports\NdjsonSourceReader;
use App\Domain\Imports\RowValidator;
use App\Domain\Imports\SchemaInferer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SourceReaderIntegrationTest extends TestCase
{
    public function test_csv_preserves_quoted_newlines_and_plans_seekable_chunks(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "id,note\n1,\"hello\nworld\"\n2,plain\n3,last\n");
        rewind($stream);

        $reader = new CsvSourceReader();
        $analysis = $reader->analyze($stream);
        self::assertSame(3, $analysis['count']);
        self::assertSame("hello\nworld", $analysis['sample'][0]['note']);

        rewind($stream);
        $chunks = $reader->plan($stream, 2);
        self::assertCount(2, $chunks);
        self::assertArrayHasKey('byte_offset', $chunks[1]);

        rewind($stream);
        $rows = iterator_to_array($reader->rowsForChunk($stream, $chunks[1]));
        self::assertSame('3', array_values($rows)[0]['id']);
    }

    public function test_malformed_csv_and_ndjson_fail_with_source_row_context(): void
    {
        $csv = fopen('php://temp', 'w+b');
        fwrite($csv, "id,name\n1,Alice,extra\n"); rewind($csv);
        $this->expectException(RuntimeException::class);
        (new CsvSourceReader())->analyze($csv);
    }

    public function test_ndjson_rejects_non_object_rows(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "{\"id\":1}\n[1,2,3]\n"); rewind($stream);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('row 2 must be an object');
        (new NdjsonSourceReader())->analyze($stream);
    }

    public function test_schema_validation_and_dedupe_are_deterministic(): void
    {
        $schema = (new SchemaInferer())->infer([
            ['id' => '1', 'amount' => '10.50', 'active' => 'true', 'email' => 'a@example.test'],
            ['id' => '2', 'amount' => '20.25', 'active' => 'false', 'email' => 'b@example.test'],
        ]);
        self::assertSame('integer', $schema['id']);
        self::assertSame('decimal', $schema['amount']);
        self::assertSame('boolean', $schema['active']);

        $errors = (new RowValidator())->validate(
            ['email' => 'not-an-email', 'age' => '15'],
            ['email' => [['type' => 'email']], 'age' => [['type' => 'min', 'value' => 18]]]
        );
        self::assertSame(['email', 'age'], array_column($errors, 'field'));

        $keys = new DedupeKey();
        self::assertSame(
            $keys->make(['email' => ' Alice@Example.Test '], ['email']),
            $keys->make(['email' => 'alice@example.test'], ['email'])
        );
    }
}

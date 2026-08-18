<?php
namespace App\Domain\Imports;
use Generator;
interface SourceReader
{
 /** @param resource $stream @return array<string,mixed> */ public function analyze($stream,array $options=[]):array;
 /** @param resource $stream @return Generator<int,array<string,mixed>> */ public function rowsForChunk($stream,array $range,array $options=[]):Generator;
 /** @param resource $stream @return list<array<string,mixed>> */ public function plan($stream,int $chunkSize,array $options=[]):array;
}

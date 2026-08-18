<?php
namespace App\Domain\Imports;
final class DedupeKey {/** @param array<string,mixed> $row @param list<string> $fields */ public function make(array $row,array $fields):string {$parts=array_map(fn($f)=>(function_exists('mb_strtolower') ? mb_strtolower(trim((string)($row[$f]??''))) : strtolower(trim((string)($row[$f]??'')))),$fields);return hash('sha256',implode("\x1f",$parts));}}

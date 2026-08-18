<?php
namespace App\Services;
use InvalidArgumentException;
final class FileTypeInspector
{
 /** @param resource $stream */
 public function assertMatches($stream,string $sourceType):array{if(!is_resource($stream))throw new InvalidArgumentException('Invalid file stream');$head=fread($stream,8192)?:'';if(stream_get_meta_data($stream)['seekable']??false)rewind($stream);$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($head)?:'application/octet-stream';$type=strtolower($sourceType);
  if($type==='xlsx'&&!str_starts_with($head,"PK"))throw new InvalidArgumentException('XLSX must be a ZIP-based workbook');
  if(in_array($type,['ndjson','jsonl'],true)){foreach(preg_split('/\r?\n/',$head) as $line){if(trim($line)==='')continue;if(!str_starts_with(ltrim($line),'{'))throw new InvalidArgumentException('NDJSON must contain one JSON object per line');break;}}
  if($type==='csv'&&str_contains($head,"\0"))throw new InvalidArgumentException('CSV appears to contain binary data');
  return ['mime'=>$mime,'head_sha256'=>hash('sha256',$head)];}
 public function extensionFor(string $type):string{return match($type){'xlsx'=>'xlsx','ndjson','jsonl'=>'ndjson',default=>'csv'};}
}

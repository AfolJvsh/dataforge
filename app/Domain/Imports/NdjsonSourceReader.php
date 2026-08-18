<?php
namespace App\Domain\Imports;
use Generator;use JsonException;use RuntimeException;
final class NdjsonSourceReader implements SourceReader
{
 public function analyze($stream,array $options=[]):array{$sample=[];$count=0;$headers=[];while(($line=fgets($stream))!==false){if(trim($line)==='')continue;$row=$this->decode($line,$count+1);$count++;$headers=array_values(array_unique([...$headers,...array_keys($row)]));if(count($sample)<100)$sample[]=$row;}return ['format'=>'ndjson','headers'=>$headers,'sample'=>$sample,'count'=>$count,'options'=>[]];}
 public function rowsForChunk($stream,array $range,array $options=[]):Generator{$seekable=(stream_get_meta_data($stream)['seekable']??false)===true;if($seekable&&isset($range['byte_offset']))fseek($stream,(int)$range['byte_offset']);else for($i=0;$i<(int)($range['start_index']??0);){$line=fgets($stream);if($line===false)return;if(trim($line)!=='')$i++;}$read=0;$source=(int)($range['start_index']??0)+1;while($read<(int)($range['limit']??1000)&&($line=fgets($stream))!==false){if(trim($line)==='')continue;yield $source=>$this->decode($line,$source);$source++;$read++;}}
 public function plan($stream,int $chunkSize,array $options=[]):array{$chunks=[];$row=0;while(true){$offset=ftell($stream);$line=fgets($stream);if($line===false)break;if(trim($line)==='')continue;if($row%$chunkSize===0)$chunks[]=['start_index'=>$row,'limit'=>$chunkSize,'byte_offset'=>$offset];$row++;}return $chunks;}
 private function decode(string $line,int $row):array{try{$value=json_decode($line,true,512,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException("Invalid NDJSON at row {$row}: {$e->getMessage()}",0,$e);}if(!is_array($value)||array_is_list($value))throw new RuntimeException("NDJSON row {$row} must be an object");return $value;}
}

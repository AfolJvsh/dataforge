<?php
namespace App\Domain\Imports;
use Generator;use RuntimeException;
final class CsvSourceReader implements SourceReader
{
 public function analyze($stream,array $options=[]):array{
  $delimiter=$options['delimiter']??$this->detectDelimiter($stream);rewind($stream);$headers=$this->headers($stream,$delimiter);$sample=[];$count=0;
  while(($values=fgetcsv($stream,0,$delimiter))!==false){$count++;if(count($values)!==count($headers))throw new RuntimeException("Column count mismatch at row ".($count+1));if(count($sample)<100)$sample[]=array_combine($headers,$values);}
  return ['format'=>'csv','headers'=>$headers,'sample'=>$sample,'count'=>$count,'options'=>['delimiter'=>$delimiter,'encoding'=>'UTF-8']];
 }
 public function rowsForChunk($stream,array $range,array $options=[]):Generator{
  $delimiter=$range['delimiter']??$options['delimiter']??',';$headers=$range['headers']??null;$seekable=(stream_get_meta_data($stream)['seekable']??false)===true;
  if($headers===null){$headers=$this->headers($stream,$delimiter);}elseif($seekable&&isset($range['byte_offset'])){fseek($stream,(int)$range['byte_offset']);}else{$this->headers($stream,$delimiter);for($i=0;$i<(int)($range['start_index']??0);$i++)if(fgetcsv($stream,0,$delimiter)===false)return;}
  $row=(int)($range['start_index']??0)+2;$read=0;$limit=(int)($range['limit']??1000);
  while($read<$limit&&($values=fgetcsv($stream,0,$delimiter))!==false){if(count($values)!==count($headers))throw new RuntimeException("Column count mismatch at row {$row}");yield $row=>array_combine($headers,$values);$row++;$read++;}
 }
 public function plan($stream,int $chunkSize,array $options=[]):array{
  $delimiter=$options['delimiter']??$this->detectDelimiter($stream);rewind($stream);$headers=$this->headers($stream,$delimiter);$chunks=[];$index=0;$row=0;
  while(true){$offset=ftell($stream);$values=fgetcsv($stream,0,$delimiter);if($values===false)break;if($row%$chunkSize===0)$chunks[]=['start_index'=>$row,'limit'=>$chunkSize,'byte_offset'=>$offset,'headers'=>$headers,'delimiter'=>$delimiter];$row++;}
  return $chunks;
 }
 private function headers($stream,string $delimiter):array{$headers=fgetcsv($stream,0,$delimiter);if($headers===false)throw new RuntimeException('CSV is empty');$headers=array_map(function($h){$v=trim((string)$h);return str_starts_with($v,"\xEF\xBB\xBF")?substr($v,3):$v;},$headers);if(in_array('', $headers,true))throw new RuntimeException('CSV contains an empty header');if(count($headers)!==count(array_unique($headers)))throw new RuntimeException('CSV contains duplicate header names');return $headers;}
 private function detectDelimiter($stream):string{rewind($stream);$line=fgets($stream);if($line===false)return ',';$scores=[];foreach([',',';',"\t",'|'] as $d)$scores[$d]=substr_count($line,$d);arsort($scores);$d=(string)array_key_first($scores);return ($scores[$d]??0)>0?$d:',';}
}

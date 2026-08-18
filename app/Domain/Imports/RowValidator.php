<?php
namespace App\Domain\Imports;
use DateTimeImmutable;
final class RowValidator
{
 /** @return list<array{field:string,code:string,message:string}> */
 public function validate(array $row,array $rules):array{$errors=[];foreach($rules as $field=>$fieldRules){$value=$row[$field]??null;foreach($fieldRules as $rule){$type=$rule['type']??'';$ok=$this->passes($type,$value,$rule,$row);if(!$ok)$errors[]=['field'=>$field,'code'=>$type,'message'=>$rule['message']??"{$field} failed {$type} validation"];}}return $errors;}
 private function passes(string $type,mixed $v,array $r,array $row):bool{return match($type){
  'required'=>!($v===null||$v===''),'nullable'=>true,'integer'=>$this->empty($v)||filter_var($v,FILTER_VALIDATE_INT)!==false,'numeric'=>$this->empty($v)||is_numeric($v),'boolean'=>$this->empty($v)||in_array(strtolower((string)$v),['1','0','true','false','yes','no'],true),'string'=>$this->empty($v)||is_string($v),'email'=>$this->empty($v)||filter_var($v,FILTER_VALIDATE_EMAIL)!==false,
  'min'=>$this->empty($v)||$this->measure($v)>=(float)$r['value'],'max'=>$this->empty($v)||$this->measure($v)<=(float)$r['value'],'min_length'=>$this->empty($v)||$this->length((string)$v)>=(int)$r['value'],'max_length'=>$this->empty($v)||$this->length((string)$v)<=(int)$r['value'],'regex'=>$this->empty($v)||@preg_match((string)$r['pattern'],(string)$v)===1,'enum'=>$this->empty($v)||in_array($v,$r['values']??[],true),
  'date'=>$this->empty($v)||$this->date($v)!==null,'date_range'=>$this->empty($v)||$this->inDateRange($v,$r),'equals_field'=>$v===($row[$r['field']??'']??null),'different_field'=>$v!==($row[$r['field']??'']??null),default=>true};}
 private function empty(mixed $v):bool{return $v===null||$v==='';}private function length(string $v):int{return function_exists('mb_strlen')?mb_strlen($v):strlen($v);}private function measure(mixed $v):float{return is_numeric($v)?(float)$v:(float)$this->length((string)$v);}private function date(mixed $v):?DateTimeImmutable{try{return new DateTimeImmutable((string)$v);}catch(\Throwable){return null;}}
 private function inDateRange(mixed $v,array $r):bool{$d=$this->date($v);if(!$d)return false;$min=isset($r['min'])?$this->date($r['min']):null;$max=isset($r['max'])?$this->date($r['max']):null;return(!$min||$d>=$min)&&(!$max||$d<=$max);}
}

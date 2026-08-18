<?php
namespace App\Domain\Imports;

final class SchemaInferer
{
    /** @param list<array<string,mixed>> $rows @return array<string,string> */
    public function infer(array $rows): array
    {
        $columns=[];
        foreach ($rows as $row) foreach ($row as $key=>$value) $columns[$key][]=$value;
        $result=[];
        foreach ($columns as $key=>$values) $result[$key]=$this->inferColumn($values);
        return $result;
    }
    /** @param list<mixed> $values */
    private function inferColumn(array $values): string
    {
        $values=array_values(array_filter($values,fn($v)=>$v!==null && $v!==''));
        if ($values===[]) return 'string';
        if ($this->all($values,fn($v)=>filter_var($v,FILTER_VALIDATE_INT)!==false)) return 'integer';
        if ($this->all($values,fn($v)=>is_numeric($v))) return 'decimal';
        if ($this->all($values,fn($v)=>in_array(strtolower((string)$v),['true','false','yes','no','0','1'],true))) return 'boolean';
        if ($this->all($values,fn($v)=>strtotime((string)$v)!==false)) return 'datetime';
        return 'string';
    }
    private function all(array $values, callable $test): bool {foreach($values as $v) if(!$test($v)) return false; return true;}
}

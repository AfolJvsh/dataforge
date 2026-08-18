<?php
namespace App\Domain\Imports;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

final class XlsxSourceReader implements SourceReader
{
    public function analyze($stream, array $options=[]): array
    {
        return $this->withWorkbook($stream, function (string $dir, array $sheets, array $shared) use ($options) {
            $sheet = $this->selectSheet($sheets, $options);
            $sample=[];$count=0;$headers=[];
            foreach ($this->worksheetRows($dir.'/'.$sheet['path'], $shared) as $rowNumber=>$values) {
                if ($headers===[]) {$headers=$this->normalizeHeaders($values);continue;}
                $count++; if(count($sample)<100)$sample[]=array_combine($headers,$this->pad($values,count($headers)));
            }
            return ['format'=>'xlsx','headers'=>$headers,'sample'=>$sample,'count'=>$count,'sheets'=>array_map(fn($s)=>['index'=>$s['index'],'name'=>$s['name']],$sheets),'options'=>['sheet_index'=>$sheet['index'],'sheet_name'=>$sheet['name']]];
        });
    }

    public function rowsForChunk($stream, array $range, array $options=[]): Generator
    {
        $rows=$this->withWorkbook($stream,function(string $dir,array $sheets,array $shared) use($range,$options){
            $sheet=$this->selectSheet($sheets,['sheet_index'=>$range['sheet_index']??$options['sheet_index']??0]);$headers=[];$out=[];$dataIndex=0;$start=(int)($range['start_index']??0);$limit=(int)($range['limit']??1000);
            foreach($this->worksheetRows($dir.'/'.$sheet['path'],$shared) as $rowNumber=>$values){if($headers===[]){$headers=$this->normalizeHeaders($values);continue;}if($dataIndex++<$start)continue;if(count($out)>=$limit)break;$out[$rowNumber]=array_combine($headers,$this->pad($values,count($headers)));}
            return $out;
        });
        foreach($rows as $n=>$row)yield $n=>$row;
    }

    public function plan($stream, int $chunkSize, array $options=[]): array
    {
        $analysis=$this->analyze($stream,$options);$chunks=[];$count=(int)$analysis['count'];$sheet=(int)$analysis['options']['sheet_index'];
        for($start=0;$start<$count;$start+=$chunkSize)$chunks[]=['start_index'=>$start,'limit'=>$chunkSize,'sheet_index'=>$sheet];
        return $chunks;
    }

    private function withWorkbook($stream, callable $callback): mixed
    {
        if(!class_exists(ZipArchive::class)||!class_exists(XMLReader::class))throw new RuntimeException('XLSX support requires ext-zip and ext-xmlreader');
        $tmp=tempnam(sys_get_temp_dir(),'dataforge-xlsx-');$out=fopen($tmp,'wb');if(!$out)throw new RuntimeException('Unable to create XLSX temp file');stream_copy_to_stream($stream,$out);fclose($out);
        $dir=sys_get_temp_dir().'/dataforge-xlsx-'.bin2hex(random_bytes(8));mkdir($dir,0700,true);$zip=new ZipArchive();
        try{if($zip->open($tmp)!==true)throw new RuntimeException('Invalid XLSX zip container');$targets=['xl/workbook.xml','xl/_rels/workbook.xml.rels'];foreach($targets as $target)if($zip->locateName($target)!==false)$zip->extractTo($dir,$target);foreach(range(1,200) as $i){$name="xl/worksheets/sheet{$i}.xml";if($zip->locateName($name)!==false)$zip->extractTo($dir,$name);}if($zip->locateName('xl/sharedStrings.xml')!==false)$zip->extractTo($dir,'xl/sharedStrings.xml');$zip->close();$sheets=$this->sheets($dir);$shared=$this->sharedStrings($dir.'/xl/sharedStrings.xml');return $callback($dir,$sheets,$shared);}finally{@unlink($tmp);$this->removeDirectory($dir);}
    }

    private function sheets(string $dir): array
    {
        $workbook=$dir.'/xl/workbook.xml';$rels=$dir.'/xl/_rels/workbook.xml.rels';$relationMap=[];
        if(is_file($rels)){ $xml=simplexml_load_file($rels); if($xml){foreach($xml->Relationship as $rel)$relationMap[(string)$rel['Id']]=(string)$rel['Target'];} }
        $result=[]; if(is_file($workbook)){ $xml=simplexml_load_file($workbook); if($xml){$xml->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$xml->registerXPathNamespace('r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');$i=0;foreach($xml->xpath('//m:sheets/m:sheet')?:[] as $sheet){$attrs=$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$rid=(string)$attrs['id'];$target=$relationMap[$rid]??('worksheets/sheet'.($i+1).'.xml');$result[]=['index'=>$i,'name'=>(string)$sheet['name'],'path'=>'xl/'.ltrim($target,'/')];$i++;}}}
        if($result===[])foreach(glob($dir.'/xl/worksheets/sheet*.xml')?:[] as $i=>$path)$result[]=['index'=>$i,'name'=>'Sheet '.($i+1),'path'=>'xl/worksheets/'.basename($path)];
        return $result;
    }

    private function sharedStrings(string $path): array
    {
        if(!is_file($path))return [];$reader=new XMLReader();$reader->open($path,null,LIBXML_NONET|LIBXML_COMPACT);$values=[];
        while($reader->read()){if($reader->nodeType===XMLReader::ELEMENT&&$reader->localName==='si'){$node=$reader->expand();$text='';if($node)foreach($node->getElementsByTagName('t') as $t)$text.=$t->textContent;$values[]=$text;}}
        $reader->close();return $values;
    }

    private function worksheetRows(string $path,array $shared): Generator
    {
        if(!is_file($path))throw new RuntimeException('Selected worksheet not found');$r=new XMLReader();$r->open($path,null,LIBXML_NONET|LIBXML_COMPACT);
        while($r->read()){if($r->nodeType!==XMLReader::ELEMENT||$r->localName!=='row')continue;$rowNumber=(int)($r->getAttribute('r')?:0);$rowNode=$r->expand();$cells=[];if($rowNode){foreach($rowNode->getElementsByTagName('c') as $cell){$ref=$cell->getAttribute('r');$idx=$this->columnIndex($ref);$type=$cell->getAttribute('t');$v='';$vs=$cell->getElementsByTagName('v');if($vs->length)$v=$vs->item(0)?->textContent??'';elseif($type==='inlineStr'){$ts=$cell->getElementsByTagName('t');foreach($ts as $t)$v.=$t->textContent;}if($type==='s')$v=$shared[(int)$v]??'';elseif($type==='b')$v=$v==='1';$cells[$idx]=$v;}}if($cells===[])continue;$max=max(array_keys($cells));$values=[];for($i=0;$i<=$max;$i++)$values[]=$cells[$i]??null;yield $rowNumber=>$values;}
        $r->close();
    }

    private function selectSheet(array $sheets,array $options):array{if($sheets===[])throw new RuntimeException('XLSX has no worksheets');$idx=(int)($options['sheet_index']??0);foreach($sheets as $sheet)if($sheet['index']===$idx)return $sheet;throw new RuntimeException("Worksheet index {$idx} not found");}
    private function normalizeHeaders(array $values):array{$headers=array_map(fn($v)=>trim((string)$v),$values);if(in_array('',$headers,true))throw new RuntimeException('XLSX header row contains an empty header');if(count($headers)!==count(array_unique($headers)))throw new RuntimeException('XLSX contains duplicate header names');return $headers;}
    private function pad(array $values,int $count):array{return array_slice(array_pad($values,$count,null),0,$count);}
    private function columnIndex(string $ref):int{preg_match('/^[A-Z]+/i',$ref,$m);$letters=strtoupper($m[0]??'A');$n=0;foreach(str_split($letters) as $c)$n=$n*26+(ord($c)-64);return max(0,$n-1);}
    private function removeDirectory(string $dir):void{if(!is_dir($dir))return;$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}@rmdir($dir);}
}

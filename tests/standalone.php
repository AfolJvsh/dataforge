<?php
require __DIR__.'/../app/Domain/Imports/SchemaInferer.php';require __DIR__.'/../app/Domain/Imports/TransformPipeline.php';require __DIR__.'/../app/Domain/Imports/RowValidator.php';require __DIR__.'/../app/Domain/Imports/DedupeKey.php';require __DIR__.'/../app/Domain/Imports/CsvStreamReader.php';
use App\Domain\Imports\{SchemaInferer,TransformPipeline,RowValidator,DedupeKey,CsvStreamReader};
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$infer=(new SchemaInferer)->infer([['id'=>'1','amount'=>'10.5','active'=>'true'],['id'=>'2','amount'=>'2','active'=>'false']]);ok($infer['id']==='integer','id inference');ok($infer['amount']==='decimal','decimal inference');
$p=new TransformPipeline;ok($p->apply('  HELLO  ',[['type'=>'trim'],['type'=>'lower']])==='hello','pipeline');
$errors=(new RowValidator)->validate(['email'=>'bad'],['email'=>[['type'=>'email']]]);ok(count($errors)===1,'validator');
$d=new DedupeKey;ok($d->make(['email'=>' A@B.COM '],['email'])===$d->make(['email'=>'a@b.com'],['email']),'dedupe normalization');
$tmp=tempnam(sys_get_temp_dir(),'df');file_put_contents($tmp,"id,name\n1,A\n2,B\n");$rows=iterator_to_array((new CsvStreamReader)->rows($tmp));ok(count($rows)===2,'csv stream');unlink($tmp);echo "DataForge standalone domain tests passed\n";

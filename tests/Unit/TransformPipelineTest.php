<?php
namespace Tests\Unit;use App\Domain\Imports\TransformPipeline;use PHPUnit\Framework\TestCase;
final class TransformPipelineTest extends TestCase {public function test_pipeline_is_deterministic():void{$p=new TransformPipeline;$config=[['type'=>'trim'],['type'=>'lower'],['type'=>'regex_replace','config'=>['pattern'=>'/\s+/','replacement'=>'-']]];$this->assertSame('hello-world',$p->apply(' Hello World ',$config));}}

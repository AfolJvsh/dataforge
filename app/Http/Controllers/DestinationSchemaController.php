<?php
namespace App\Http\Controllers;
use App\Models\{DestinationField,DestinationSchema};use Illuminate\Http\{JsonResponse,Request};use Illuminate\Support\Facades\DB;
final class DestinationSchemaController
{
 public function index(Request $r):JsonResponse{$ids=$r->user()->organizations()->pluck('organizations.id');return response()->json(DestinationSchema::whereIn('organization_id',$ids)->with('fields')->orderBy('name')->orderByDesc('version')->get());}
 public function store(Request $r):JsonResponse{$d=$r->validate(['organization_id'=>'required|uuid','name'=>'required|string|max:120','fields'=>'required|array|min:1','fields.*.key'=>'required|string|max:120','fields.*.type'=>'required|in:string,integer,decimal,boolean,date,datetime,email,phone','fields.*.nullable'=>'boolean','fields.*.constraints'=>'array']);$this->auth($r,$d['organization_id']);$schema=DB::transaction(function()use($d){$version=(int)DestinationSchema::where('organization_id',$d['organization_id'])->where('name',$d['name'])->max('version')+1;$s=DestinationSchema::create(['organization_id'=>$d['organization_id'],'name'=>$d['name'],'version'=>$version]);foreach($d['fields'] as $i=>$f)DestinationField::create(['schema_id'=>$s->id,'key'=>$f['key'],'type'=>$f['type'],'nullable'=>$f['nullable']??true,'constraints_json'=>$f['constraints']??null,'position'=>$i]);return $s;},3);return response()->json($schema->load('fields'),201);}
 private function auth(Request $r,string $org):void{abort_unless($r->user()->organizations()->whereKey($org)->exists(),403);}
}

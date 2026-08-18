<?php
namespace App\Services;
use App\Models\DataImport;
final class BuildImportSnapshot
{
 public function build(DataImport $import):array{$import->load('mappings.field','destinationSchema.fields');$mappings=[];foreach($import->mappings as $m)$mappings[$m->field->key]=['source'=>$m->source_column,'transforms'=>$m->transform_pipeline_json??[]];$policy=$import->import_policy_json??[];return ['destination_schema'=>['id'=>$import->destinationSchema?->id,'version'=>$import->destinationSchema?->version,'fields'=>$import->destinationSchema?->fields?->map(fn($f)=>['key'=>$f->key,'type'=>$f->type,'nullable'=>$f->nullable,'constraints'=>$f->constraints_json])->all()??[]],'mappings'=>$mappings,'validation'=>$policy['validation']??[],'dedupe_fields'=>$policy['dedupe_fields']??array_keys($mappings),'duplicate_strategy'=>$policy['duplicate_strategy']??'keep_first','db_batch_size'=>$policy['db_batch_size']??250,'error_source_fields'=>$policy['error_source_fields']??[]];}
}

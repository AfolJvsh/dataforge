<?php
namespace App\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class ImportMapping extends Model{use HasUuids;protected $guarded=[];protected function casts():array{return ['transform_pipeline_json'=>'array'];}public function field(){return $this->belongsTo(DestinationField::class,'destination_field_id');}}

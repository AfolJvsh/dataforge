<?php
namespace App\Models;use App\Domain\Imports\ChunkStatus;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class ImportChunk extends Model{use HasUuids;protected $guarded=[];protected function casts():array{return ['status'=>ChunkStatus::class,'range_metadata_json'=>'array'];}public function execution(){return $this->belongsTo(ImportExecution::class,'execution_id');}}

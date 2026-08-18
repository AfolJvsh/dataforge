<?php
namespace App\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class DestinationSchema extends Model{use HasUuids;protected $guarded=[];public function fields(){return $this->hasMany(DestinationField::class,'schema_id')->orderBy('position');}}

<?php
namespace App\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class DestinationField extends Model{use HasUuids;protected $guarded=[];protected function casts():array{return ['constraints_json'=>'array','nullable'=>'boolean'];}}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class ImportRowError extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['raw_row_json'=>'array'];}}

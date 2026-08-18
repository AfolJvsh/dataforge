<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('destination_schemas',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('organization_id')->index();$t->string('name');$t->unsignedInteger('version');$t->timestamps();$t->unique(['organization_id','name','version']);$t->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();});
  Schema::create('destination_fields',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('schema_id')->index();$t->string('key');$t->string('type');$t->boolean('nullable')->default(true);$t->jsonb('constraints_json')->nullable();$t->unsignedInteger('position')->default(0);$t->timestamps();$t->unique(['schema_id','key']);$t->foreign('schema_id')->references('id')->on('destination_schemas')->cascadeOnDelete();});
  Schema::create('import_mappings',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('import_id')->index();$t->uuid('destination_field_id')->index();$t->string('source_column')->nullable();$t->jsonb('transform_pipeline_json')->nullable();$t->timestamps();$t->unique(['import_id','destination_field_id']);$t->foreign('import_id')->references('id')->on('imports')->cascadeOnDelete();$t->foreign('destination_field_id')->references('id')->on('destination_fields')->cascadeOnDelete();});
  Schema::create('import_staging_records',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('organization_id')->index();$t->uuid('execution_id')->index();$t->unsignedBigInteger('source_row_number');$t->string('dedupe_key',64)->index();$t->jsonb('payload');$t->timestamps();$t->unique(['execution_id','source_row_number']);$t->foreign('execution_id')->references('id')->on('import_executions')->cascadeOnDelete();});
  Schema::table('imports',function(Blueprint $t){$t->uuid('destination_schema_id')->nullable()->index();$t->jsonb('source_options_json')->nullable();$t->jsonb('import_policy_json')->nullable();$t->timestampTz('deleted_at')->nullable()->index();$t->foreign('destination_schema_id')->references('id')->on('destination_schemas')->nullOnDelete();});
  Schema::table('import_executions',function(Blueprint $t){$t->jsonb('metrics_json')->nullable();$t->unsignedInteger('chunk_size')->default(1000);});
  Schema::table('import_chunks',function(Blueprint $t){$t->unsignedInteger('duration_ms')->nullable();$t->unsignedBigInteger('peak_memory_bytes')->nullable();$t->text('last_error')->nullable();});
 }
 public function down():void{Schema::table('import_chunks',fn(Blueprint $t)=>$t->dropColumn(['duration_ms','peak_memory_bytes','last_error']));Schema::table('import_executions',fn(Blueprint $t)=>$t->dropColumn(['metrics_json','chunk_size']));Schema::table('imports',function(Blueprint $t){$t->dropForeign(['destination_schema_id']);$t->dropColumn(['destination_schema_id','source_options_json','import_policy_json','deleted_at']);});Schema::dropIfExists('import_staging_records');Schema::dropIfExists('import_mappings');Schema::dropIfExists('destination_fields');Schema::dropIfExists('destination_schemas');}
};

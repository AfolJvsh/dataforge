<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::table('import_chunks',fn(Blueprint $t)=>$t->unsignedInteger('db_write_ms')->default(0));}public function down():void{Schema::table('import_chunks',fn(Blueprint $t)=>$t->dropColumn('db_write_ms'));}};

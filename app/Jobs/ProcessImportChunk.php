<?php
namespace App\Jobs;

use App\Domain\Imports\{ChunkStatus,DedupeKey,ImportStatus,RowValidator,SourceReaderFactory,TransformPipeline};
use App\Models\{ImportChunk,ImportExecution};
use App\Services\{ImportFinalizer,OperationalMetrics};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{DB,Log,Storage};
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProcessImportChunk implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=4;
    public array $backoff=[5,30,120];
    public int $timeout=300;
    public function __construct(public string $chunkId){}

    public function handle(SourceReaderFactory $factory,TransformPipeline $transforms,RowValidator $validator,DedupeKey $dedupe,ImportFinalizer $finalizer,OperationalMetrics $metrics):void
    {
        $claimed=DB::transaction(function(){
            $chunk=ImportChunk::lockForUpdate()->findOrFail($this->chunkId);
            if($chunk->status===ChunkStatus::Completed||$chunk->status===ChunkStatus::Cancelled)return null;
            $execution=ImportExecution::lockForUpdate()->findOrFail($chunk->execution_id);
            if($execution->cancel_requested_at){$chunk->update(['status'=>ChunkStatus::Cancelled]);return null;}
            if($execution->status===ImportStatus::Queued)$execution->update(['status'=>ImportStatus::Processing,'started_at'=>now()]);
            $chunk->update(['status'=>ChunkStatus::Processing,'attempts'=>$chunk->attempts+1,'last_error'=>null]);
            return [$chunk->fresh(),$execution->fresh()];
        },3);
        if(!$claimed){$finalizer->finalize(ImportChunk::findOrFail($this->chunkId)->execution_id);return;}
        [$chunk,$execution]=$claimed;$execution->load('import');$started=microtime(true);$startMem=memory_get_usage(true);$snapshot=$execution->mapping_snapshot_json;$batchSize=max(50,min(1000,(int)($snapshot['db_batch_size']??250)));
        try{$stream=Storage::disk(config('filesystems.default'))->readStream($execution->import->storage_key);if(!is_resource($stream))throw new RuntimeException('Unable to read import object');}catch(\Throwable $e){$metrics->storageFailure($execution->import->organization_id);throw $e;}
        $processed=0;$invalid=0;$staged=0;$dbWriteMs=0;$errors=[];$records=[];
        try{
            foreach($factory->make($execution->import->source_type)->rowsForChunk($stream,$chunk->range_metadata_json,$execution->import->source_options_json??[]) as $rowNumber=>$source){
                if($processed>0&&$processed%100===0&&ImportExecution::whereKey($execution->id)->value('cancel_requested_at')){$this->cancel($chunk,$execution);return;}
                $processed++;
                try{$mapped=[];foreach($snapshot['mappings']??[] as $destination=>$config){$mapped[$destination]=$transforms->apply($source[$config['source']]??null,$config['transforms']??[],$source);} $rowErrors=$validator->validate($mapped,$snapshot['validation']??[]);}
                catch(Throwable $e){$rowErrors=[['field'=>'*','code'=>'transform_error','message'=>$e->getMessage()]];$mapped=[];}
                if($rowErrors!==[]){$invalid++;foreach($rowErrors as $error)$errors[]=$this->errorRow($execution->id,$rowNumber,$error,$source,$snapshot);}
                else{$key=$dedupe->make($mapped,$snapshot['dedupe_fields']??array_keys($mapped));$records[]=['id'=>(string)Str::uuid(),'organization_id'=>$execution->import->organization_id,'execution_id'=>$execution->id,'source_row_number'=>$rowNumber,'dedupe_key'=>$key,'payload'=>json_encode($mapped,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()];}
                if(count($records)+count($errors)>=$batchSize){[$written,$writeMs]=$this->flush($records,$errors);$staged+=$written;$dbWriteMs+=$writeMs;$records=[];$errors=[];}
            }
            [$written,$writeMs]=$this->flush($records,$errors);$staged+=$written;$dbWriteMs+=$writeMs;
        }finally{fclose($stream);}

        DB::transaction(function()use($chunk,$execution,$processed,$invalid,$started,$startMem,$dbWriteMs){$locked=ImportChunk::lockForUpdate()->findOrFail($chunk->id);if($locked->status===ChunkStatus::Completed)return;if(ImportExecution::whereKey($execution->id)->value('cancel_requested_at')){$locked->update(['status'=>ChunkStatus::Cancelled]);return;}$locked->update(['status'=>ChunkStatus::Completed,'processed_rows'=>$processed,'duration_ms'=>(int)((microtime(true)-$started)*1000),'peak_memory_bytes'=>max($startMem,memory_get_peak_usage(true)),'db_write_ms'=>$dbWriteMs]);$e=ImportExecution::lockForUpdate()->findOrFail($execution->id);$e->increment('processed_rows',$processed);$e->increment('invalid_rows',$invalid);},3);
        Log::info('dataforge.chunk.completed',['organization_id'=>$execution->import->organization_id,'import_id'=>$execution->import_id,'execution_id'=>$execution->id,'chunk_id'=>$chunk->id,'processed_rows'=>$processed,'invalid_rows'=>$invalid,'staged_rows'=>$staged]);
        $finalizer->finalize($execution->id);
    }

    public function failed(?Throwable $e):void
    {
        $chunk=ImportChunk::find($this->chunkId);if(!$chunk)return;$chunk->update(['status'=>ChunkStatus::Failed,'last_error'=>mb_substr($e?->getMessage()??'worker failed',0,4000)]);ImportExecution::whereKey($chunk->execution_id)->update(['status'=>ImportStatus::Failed,'completed_at'=>now()]);
    }

    private function flush(array $records,array $errors):array{$started=microtime(true);$written=DB::transaction(function()use($records,$errors){if($errors)DB::table('import_row_errors')->insertOrIgnore($errors);if(!$records)return 0;return DB::table('import_staging_records')->insertOrIgnore($records);},3);return[$written,(int)((microtime(true)-$started)*1000)];}
    private function errorRow(string $executionId,int $rowNumber,array $error,array $source,array $snapshot):array{$selected=[];foreach($snapshot['error_source_fields']??array_slice(array_keys($source),0,5) as $field)$selected[$field]=$source[$field]??null;$message=mb_substr((string)$error['message'],0,2000);$fingerprint=hash('sha256',implode("\x1f",[$rowNumber,(string)($error['field']??''),(string)$error['code'],$message]));return ['id'=>(string)Str::uuid(),'execution_id'=>$executionId,'source_row_number'=>$rowNumber,'error_code'=>$error['code'],'field'=>$error['field'],'message'=>$message,'error_fingerprint'=>$fingerprint,'raw_row_json'=>json_encode($selected,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()];}
    private function cancel(ImportChunk $chunk,ImportExecution $execution):void{DB::transaction(function()use($chunk,$execution){ImportChunk::whereKey($chunk->id)->where('status',ChunkStatus::Processing->value)->update(['status'=>ChunkStatus::Cancelled]);ImportExecution::whereKey($execution->id)->update(['status'=>ImportStatus::Cancelled,'completed_at'=>now()]);},3);}
}

<?php
namespace Tests\Feature;

use App\Domain\Imports\ImportStatus;
use App\Jobs\AnalyzeImport;
use App\Models\DataImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

final class UploadFinalizationTest extends TestCase
{
    use RefreshDatabase, CreatesTenant;

    public function test_uploaded_object_is_inspected_hashed_persisted_and_queued_for_analysis(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        [$user, $organization] = $this->tenant('upload');
        $this->actingAsTenant($user);

        $key = "organizations/{$organization->id}/imports/customers.csv";
        $contents = "email,name\nalice@example.test,Alice\n";
        Storage::disk('local')->put($key, $contents);

        $response = $this->postJson('/api/uploads/finalize', [
            'organization_id' => $organization->id,
            'name' => 'Customers',
            'storage_key' => $key,
            'original_filename' => 'customers.csv',
            'source_type' => 'csv',
            'retention_days' => 30,
        ])->assertAccepted();

        $import = DataImport::findOrFail($response->json('id'));
        self::assertSame($organization->id, $import->organization_id);
        self::assertSame(hash('sha256', $contents), $import->checksum);
        self::assertSame(ImportStatus::Analyzing, $import->status);
        self::assertSame(strlen($contents), $import->file_size);
        self::assertNotNull($import->retention_until);
        Queue::assertPushed(AnalyzeImport::class, fn (AnalyzeImport $job) => $job->importId === $import->id);
    }

    public function test_finalize_rejects_storage_keys_outside_the_callers_organization_prefix(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        [$user, $organization] = $this->tenant('owner');
        [, $other] = $this->tenant('other');
        $this->actingAsTenant($user);

        $key = "organizations/{$other->id}/imports/customers.csv";
        Storage::disk('local')->put($key, "email\na@example.test\n");

        $this->postJson('/api/uploads/finalize', [
            'organization_id' => $organization->id,
            'name' => 'Customers',
            'storage_key' => $key,
            'original_filename' => 'customers.csv',
            'source_type' => 'csv',
        ])->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Imports\ImportStatus;
use App\Models\DataImport;
use App\Models\DestinationField;
use App\Models\DestinationSchema;
use App\Models\ImportExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

final class ImportLifecycleTest extends TestCase
{
    use RefreshDatabase, CreatesTenant;

    public function test_tenant_cannot_read_another_organizations_import(): void
    {
        [$user] = $this->tenant('a');
        [, $other] = $this->tenant('b');
        $this->actingAsTenant($user);

        $import = $this->importFor($other->id);
        $this->getJson('/api/imports/'.$import->id)->assertForbidden();
    }

    public function test_mapping_is_versioned_to_a_tenant_schema_and_preview_uses_snapshot(): void
    {
        [$user, $organization] = $this->tenant('map');
        $this->actingAsTenant($user);
        $import = $this->importFor($organization->id);

        $schema = DestinationSchema::create(['organization_id' => $organization->id, 'name' => 'Customer', 'version' => 1]);
        $email = DestinationField::create(['schema_id' => $schema->id, 'key' => 'email', 'type' => 'email', 'nullable' => false, 'position' => 0]);
        $name = DestinationField::create(['schema_id' => $schema->id, 'key' => 'name', 'type' => 'string', 'nullable' => false, 'position' => 1]);

        $this->putJson('/api/imports/'.$import->id.'/mapping', [
            'destination_schema_id' => $schema->id,
            'mappings' => [
                ['destination_field_id' => $email->id, 'source_column' => 'Email', 'transforms' => [['type' => 'trim'], ['type' => 'lower']]],
                ['destination_field_id' => $name->id, 'source_column' => 'Name', 'transforms' => [['type' => 'trim']]],
            ],
            'validation' => ['email' => [['type' => 'required'], ['type' => 'email']]],
            'dedupe_fields' => ['email'],
            'duplicate_strategy' => 'keep_first',
            'db_batch_size' => 250,
        ])->assertOk()->assertJsonPath('saved', true);

        $this->postJson('/api/imports/'.$import->id.'/preview')
            ->assertOk()
            ->assertJsonPath('rows.0.output.email', 'alice@example.test')
            ->assertJsonPath('snapshot.duplicate_strategy', 'keep_first');
    }

    public function test_cancel_marks_execution_without_destroying_progress(): void
    {
        [$user, $organization] = $this->tenant('cancel');
        $this->actingAsTenant($user);
        $import = $this->importFor($organization->id);
        $execution = ImportExecution::create([
            'import_id' => $import->id,
            'mapping_snapshot_json' => ['mappings' => []],
            'status' => ImportStatus::Processing,
            'total_rows' => 100,
            'processed_rows' => 40,
        ]);

        $this->postJson("/api/imports/{$import->id}/executions/{$execution->id}/cancel")
            ->assertOk()->assertJsonPath('status', 'cancellation_requested');

        $execution->refresh();
        self::assertNotNull($execution->cancel_requested_at);
        self::assertSame(40, $execution->processed_rows);
    }

    private function importFor(string $organizationId): DataImport
    {
        return DataImport::create([
            'organization_id' => $organizationId,
            'name' => 'Customers',
            'source_type' => 'csv',
            'storage_key' => 'imports/customers.csv',
            'original_filename' => 'customers.csv',
            'file_size' => 128,
            'checksum' => str_repeat('a', 64),
            'status' => ImportStatus::Ready,
            'source_schema_json' => [
                'headers' => ['Email', 'Name'],
                'sample' => [['Email' => ' ALICE@EXAMPLE.TEST ', 'Name' => ' Alice ']],
                'inferred' => ['Email' => 'string', 'Name' => 'string'],
            ],
        ]);
    }
}

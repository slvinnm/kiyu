<?php

use App\Enums\StationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;

uses(RefreshDatabase::class, SetupKiyuHospital::class);

beforeEach(function () {
    $this->setupHospital();
});

test('department and station crud works correctly', function () {
    // Create Department
    $deptRes = $this->postJson('/api/departments', [
        'code' => 'POLI_MATA',
        'name' => 'Poli Mata',
    ]);

    $deptRes->assertStatus(201)
        ->assertJsonPath('data.code', 'POLI_MATA');

    $deptId = $deptRes->json('data.id');

    // Create Station
    $stationRes = $this->postJson('/api/stations', [
        'department_id' => $deptId,
        'name' => 'Ruang Periksa Mata',
        'code' => 'EYE-01',
        'type' => StationType::Doctor->value,
        'queue_prefix' => 'M',
    ]);

    $stationRes->assertStatus(201)
        ->assertJsonPath('data.code', 'EYE-01');

    // List Stations for department
    $this->getJson("/api/departments/{$deptId}/stations")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('workflow step creation and management', function () {
    // Add a step to general workflow
    $stepRes = $this->postJson("/api/workflows/{$this->generalWorkflow->id}/steps", [
        'station_id' => $this->doctorStation2->id,
        'name' => 'Second Opinion Dokter',
        'sequence' => 10,
    ]);

    $stepRes->assertStatus(201)
        ->assertJsonPath('data.sequence', 10);

    $stepId = $stepRes->json('data.id');

    // Update step
    $this->putJson("/api/workflow-steps/{$stepId}", [
        'name' => 'Second Opinion Updated',
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Second Opinion Updated');

    // Delete step
    $this->deleteJson("/api/workflow-steps/{$stepId}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('workflow_steps', ['id' => $stepId]);
});

test('patient directory search and retrieval', function () {
    // Search by name
    $searchRes = $this->getJson('/api/patients?search=Budi');
    $searchRes->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.name', 'Budi Santoso');

    // Show patient with visits
    $this->getJson("/api/patients/{$this->samplePatient->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.mrn', $this->samplePatient->mrn);
});

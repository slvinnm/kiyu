<?php

use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;

uses(RefreshDatabase::class, SetupKiyuHospital::class);

beforeEach(function () {
    $this->setupHospital();
});

test('clinical call-next claims ticket for clinical station', function () {
    // 1. Create walk-in and complete registration to create nurse ticket
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $this->postJson('/api/reception/call-next', [
        'department_id' => $this->generalDepartment->id,
    ]);

    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $intake['acquisition']['id'],
        'patient_id' => $this->samplePatient->id,
    ]);

    // 2. Nurse calls next at nurse station
    $callRes = $this->postJson('/api/clinical/call-next', [
        'station_id' => $this->nurseStation->id,
        'assigned_to' => 'nurse-bob',
    ]);

    $callRes->assertStatus(200)
        ->assertJsonPath('data.status', QueueTicketStatus::Called->value)
        ->assertJsonPath('data.station_id', $this->nurseStation->id)
        ->assertJsonPath('data.assigned_to', 'nurse-bob');

    // Verify QueueEvent CALLED
    $this->assertDatabaseHas('queue_events', [
        'queue_ticket_id' => $callRes->json('data.id'),
        'event_type' => 'CALLED',
        'to_status' => 'CALLED',
    ]);
});

test('starting clinical service transitions ticket to IN_PROGRESS and transitions Visit from WAITING to IN_PROGRESS', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $visitId = $intake['visit']['id'];

    $this->postJson('/api/reception/call-next', ['department_id' => $this->generalDepartment->id]);
    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $intake['acquisition']['id'],
        'patient_id' => $this->samplePatient->id,
    ]);

    // Nurse calls next
    $nurseTicket = $this->postJson('/api/clinical/call-next', [
        'station_id' => $this->nurseStation->id,
    ])->json('data');

    // Confirm visit is still WAITING before clinical start
    $this->assertDatabaseHas('visits', [
        'id' => $visitId,
        'status' => VisitStatus::Waiting->value,
    ]);

    // Nurse starts service
    $startRes = $this->postJson("/api/tickets/{$nurseTicket['id']}/start");

    $startRes->assertStatus(200)
        ->assertJsonPath('data.status', QueueTicketStatus::InProgress->value);

    // CRITICAL INVARIANT: Visit status must now be IN_PROGRESS!
    $this->assertDatabaseHas('visits', [
        'id' => $visitId,
        'status' => VisitStatus::InProgress->value,
    ]);

    $this->assertDatabaseHas('queue_events', [
        'queue_ticket_id' => $nurseTicket['id'],
        'event_type' => 'STARTED',
        'to_status' => 'IN_PROGRESS',
    ]);
});

test('holding a ticket sets status ON_HOLD and excludes it from call-next, then resume sets back to CALLED', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $this->postJson('/api/reception/call-next', ['department_id' => $this->generalDepartment->id]);
    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $intake['acquisition']['id'],
        'patient_id' => $this->samplePatient->id,
    ]);

    $nurseTicket = $this->postJson('/api/clinical/call-next', [
        'station_id' => $this->nurseStation->id,
    ])->json('data');

    // Hold the ticket
    $holdRes = $this->postJson("/api/tickets/{$nurseTicket['id']}/hold", [
        'notes' => 'Patient waiting for lab results',
    ]);

    $holdRes->assertStatus(200)
        ->assertJsonPath('data.status', QueueTicketStatus::OnHold->value);

    // Call-next should NOT find the ON_HOLD ticket
    $secondCall = $this->postJson('/api/clinical/call-next', [
        'station_id' => $this->nurseStation->id,
    ]);

    $secondCall->assertStatus(200)
        ->assertJsonPath('data', null);

    // Resume the ticket
    $resumeRes = $this->postJson("/api/tickets/{$nurseTicket['id']}/resume");

    $resumeRes->assertStatus(200)
        ->assertJsonPath('data.status', QueueTicketStatus::Called->value);
});

test('clinical completion advances workflow to next step and finally completes visit', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $visitId = $intake['visit']['id'];

    $this->postJson('/api/reception/call-next', ['department_id' => $this->generalDepartment->id]);
    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $intake['acquisition']['id'],
        'patient_id' => $this->samplePatient->id,
    ]);

    // 1. Nurse step (step 2)
    $nurseTicket = $this->postJson('/api/clinical/call-next', ['station_id' => $this->nurseStation->id])->json('data');
    $this->postJson("/api/tickets/{$nurseTicket['id']}/start")->assertStatus(200);
    $completeNurse = $this->postJson("/api/tickets/{$nurseTicket['id']}/complete")->assertStatus(200);

    // Next step is Doctor (step 3)
    $docTicketId = $completeNurse->json('data.next_ticket.id');
    expect($completeNurse->json('data.next_ticket.station_id'))->toBe($this->doctorStation1->id);

    // 2. Doctor step (step 3)
    $this->postJson('/api/clinical/call-next', ['station_id' => $this->doctorStation1->id])->assertStatus(200);
    $this->postJson("/api/tickets/{$docTicketId}/start")->assertStatus(200);
    $completeDoc = $this->postJson("/api/tickets/{$docTicketId}/complete")->assertStatus(200);

    // Next step is Pharmacy (step 4)
    $pharmTicketId = $completeDoc->json('data.next_ticket.id');
    expect($completeDoc->json('data.next_ticket.station_id'))->toBe($this->pharmacyStation->id);

    // 3. Pharmacy step (step 4 - final step)
    $this->postJson('/api/clinical/call-next', ['station_id' => $this->pharmacyStation->id])->assertStatus(200);
    $this->postJson("/api/tickets/{$pharmTicketId}/start")->assertStatus(200);
    $completePharm = $this->postJson("/api/tickets/{$pharmTicketId}/complete")->assertStatus(200);

    // No next step -> Visit COMPLETED
    expect($completePharm->json('data.next_ticket'))->toBeNull();
    expect($completePharm->json('data.visit.status'))->toBe(VisitStatus::Completed->value);

    $this->assertDatabaseHas('visits', [
        'id' => $visitId,
        'status' => VisitStatus::Completed->value,
    ]);
});

test('ticket transfer moves patient to another compatible station in the same department and step', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $this->postJson('/api/reception/call-next', ['department_id' => $this->generalDepartment->id]);
    $reg = $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $intake['acquisition']['id'],
        'patient_id' => $this->samplePatient->id,
    ])->json('data');

    // Complete Nurse step to reach Doctor step
    $nurseTicket = $this->postJson('/api/clinical/call-next', ['station_id' => $this->nurseStation->id])->json('data');
    $this->postJson("/api/tickets/{$nurseTicket['id']}/start");
    $docTicket = $this->postJson("/api/tickets/{$nurseTicket['id']}/complete")->json('data.next_ticket');

    // Call doc ticket at Doctor Station 1
    $this->postJson('/api/clinical/call-next', ['station_id' => $this->doctorStation1->id]);

    // Transfer from Doctor Station 1 to Doctor Station 2
    $transferRes = $this->postJson("/api/tickets/{$docTicket['id']}/transfer", [
        'target_station_id' => $this->doctorStation2->id,
        'notes' => 'Transferring to Doctor Station 2 for specialist consultation',
    ]);

    $transferRes->assertStatus(200)
        ->assertJsonPath('data.old_ticket.status', QueueTicketStatus::Transferred->value)
        ->assertJsonPath('data.old_ticket.transferred_to_station_id', $this->doctorStation2->id)
        ->assertJsonPath('data.new_ticket.status', QueueTicketStatus::Created->value)
        ->assertJsonPath('data.new_ticket.station_id', $this->doctorStation2->id)
        ->assertJsonPath('data.new_ticket.transferred_from_ticket_id', $docTicket['id']);

    // Attempting to transfer to an incompatible station (e.g. Pharmacy) must be rejected
    $incompatibleRes = $this->postJson("/api/tickets/{$transferRes->json('data.new_ticket.id')}/transfer", [
        'target_station_id' => $this->pharmacyStation->id,
    ]);

    // Needs to be CALLED or IN_PROGRESS before transferring
    $incompatibleRes->assertStatus(422);
});

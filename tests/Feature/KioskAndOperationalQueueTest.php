<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;
use Tests\TestCase;

class KioskAndOperationalQueueTest extends TestCase
{
    use RefreshDatabase;
    use SetupKiyuHospital;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHospital();
    }

    public function test_can_list_active_departments_for_kiosk(): void
    {
        $response = $this->getJson('/api/departments/active');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'is_active']]]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_lookup_online_booking_preview_at_kiosk(): void
    {
        $onlineBooking = $this->postJson('/api/intake/online', [
            'patient_id' => $this->samplePatient->id,
            'department_id' => $this->generalDepartment->id,
            'priority' => Priority::Normal->value,
        ])->json('data');

        $activeKey = $onlineBooking['visit']['online_active_key'];

        $response = $this->getJson("/api/intake/online-lookup/{$activeKey}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_eligible_checkin', true)
            ->assertJsonPath('data.status', VisitStatus::AwaitingCheckin->value)
            ->assertJsonPath('data.department_name', 'Poli Umum');

        // Verify patient name is masked for privacy
        $this->assertStringContainsString('*', $response->json('data.patient_preview.masked_name'));
    }

    public function test_can_recall_reception_ticket(): void
    {
        $intake = $this->postJson('/api/intake/kiosk', [
            'department_id' => $this->generalDepartment->id,
        ])->json('data');

        $callNext = $this->postJson('/api/reception/call-next', [
            'department_id' => $this->generalDepartment->id,
            'assigned_to' => 'receptionist_1',
        ])->json('data');

        $ticketId = $callNext['id'];

        $response = $this->postJson("/api/reception/{$ticketId}/recall", [
            'assigned_to' => 'receptionist_1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', QueueTicketStatus::Called->value);

        $this->assertDatabaseHas('queue_events', [
            'queue_ticket_id' => $ticketId,
            'event_type' => 'RECALLED',
        ]);
    }

    public function test_can_skip_and_no_show_reception_ticket(): void
    {
        $intake = $this->postJson('/api/intake/kiosk', [
            'department_id' => $this->generalDepartment->id,
        ])->json('data');

        $callNext = $this->postJson('/api/reception/call-next', [
            'department_id' => $this->generalDepartment->id,
            'assigned_to' => 'receptionist_1',
        ])->json('data');

        $ticketId = $callNext['id'];

        $response = $this->postJson("/api/reception/{$ticketId}/skip", [
            'reason' => 'Patient went to the restroom',
            'user_id' => 'receptionist_1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', QueueTicketStatus::Skipped->value);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticketId,
            'status' => QueueTicketStatus::Skipped->value,
            'notes' => 'Patient went to the restroom',
        ]);
    }

    public function test_can_recall_clinical_ticket(): void
    {
        // Setup a full walkin until clinical station
        $intake = $this->postJson('/api/intake/kiosk', [
            'department_id' => $this->generalDepartment->id,
        ])->json('data');

        $callNext = $this->postJson('/api/reception/call-next', [
            'department_id' => $this->generalDepartment->id,
        ])->json('data');

        $reg = $this->postJson('/api/reception/complete-registration', [
            'acquisition_id' => $intake['acquisition']['id'],
            'patient_id' => $this->samplePatient->id,
        ])->json('data');

        $clinicalTicket = $reg['next_ticket'];

        $called = $this->postJson('/api/clinical/call-next', [
            'station_id' => $clinicalTicket['station_id'],
            'assigned_to' => 'nurse_1',
        ])->json('data');

        $response = $this->postJson("/api/tickets/{$called['id']}/recall", [
            'user_id' => 'nurse_1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', QueueTicketStatus::Called->value);

        $this->assertDatabaseHas('queue_events', [
            'queue_ticket_id' => $called['id'],
            'event_type' => 'RECALLED',
        ]);
    }

    public function test_duplicate_nik_returns_business_conflict_exception(): void
    {
        $intake = $this->postJson('/api/intake/kiosk', [
            'department_id' => $this->generalDepartment->id,
        ])->json('data');

        // Complete registration with duplicate NIK
        $response = $this->postJson('/api/reception/complete-registration', [
            'acquisition_id' => $intake['acquisition']['id'],
            'patient_data' => [
                'name' => 'Budi Duplikat',
                'nik' => $this->samplePatient->nik, // Duplicate NIK!
            ],
        ]);

        $response->assertStatus(409)
            ->assertSee('NIK');
    }
}

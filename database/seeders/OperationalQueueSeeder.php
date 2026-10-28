<?php

namespace Database\Seeders;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Models\QueueCounter;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\Station;
use App\Models\Visit;
use App\Models\VisitCounter;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OperationalQueueSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $general = Department::where('code', 'GEN')->first();
        if (! $general) {
            return;
        }

        $genWorkflow = Workflow::where('department_id', $general->id)->first();
        $regStation = Station::where('code', 'GEN-REG')->first();
        $nurseStation = Station::where('code', 'GEN-NUR')->first();
        $docStation = Station::where('code', 'GEN-DOC1')->first();
        $phaStation = Station::where('code', 'GEN-PHA')->first();

        $regStep = WorkflowStep::where('workflow_id', $genWorkflow->id)->where('station_id', $regStation->id)->first();
        $nurseStep = WorkflowStep::where('workflow_id', $genWorkflow->id)->where('station_id', $nurseStation->id)->first();
        $docStep = WorkflowStep::where('workflow_id', $genWorkflow->id)->where('station_id', $docStation->id)->first();
        $phaStep = WorkflowStep::where('workflow_id', $genWorkflow->id)->where('station_id', $phaStation->id)->first();

        $patients = Patient::limit(10)->get();
        if ($patients->isEmpty()) {
            return;
        }

        // Initialize counters for today
        VisitCounter::updateOrCreate(
            ['counter_date' => $today],
            ['last_sequence' => 15]
        );

        QueueCounter::updateOrCreate(
            ['station_id' => $regStation->id, 'counter_date' => $today],
            ['last_sequence' => 10]
        );

        QueueCounter::updateOrCreate(
            ['station_id' => $nurseStation->id, 'counter_date' => $today],
            ['last_sequence' => 5]
        );

        QueueCounter::updateOrCreate(
            ['station_id' => $docStation->id, 'counter_date' => $today],
            ['last_sequence' => 4]
        );

        QueueCounter::updateOrCreate(
            ['station_id' => $phaStation->id, 'counter_date' => $today],
            ['last_sequence' => 2]
        );

        // =========================================================================
        // SCENARIO 1: Online Booking (Ready for Kiosk Self Check-in Testing)
        // =========================================================================
        // Patient 1: Budi Santoso (Online Booking awaiting check-in with predictable key)
        $onlineVisit1 = Visit::firstOrCreate(
            ['visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0001'],
            [
                'patient_id' => $patients[0]->id,
                'department_id' => $general->id,
                'workflow_id' => $genWorkflow->id,
                'priority' => Priority::Normal,
                'intake_channel' => IntakeChannel::Online,
                'status' => VisitStatus::AwaitingCheckin,
                'online_active_key' => 'ONLINE-123456',
            ]
        );

        QueueAcquisition::create([
            'visit_id' => $onlineVisit1->id,
            'department_id' => $general->id,
            'channel' => IntakeChannel::Online,
            'status' => QueueAcquisitionStatus::Acquired,
            'idempotency_key' => 'IDEMP-ONLINE-001',
        ]);

        // Patient 2: Siti Aminah (Online Booking awaiting check-in, priority)
        $onlineVisit2 = Visit::firstOrCreate(
            ['visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0002'],
            [
                'patient_id' => $patients[1]->id,
                'department_id' => $general->id,
                'workflow_id' => $genWorkflow->id,
                'priority' => Priority::Priority,
                'intake_channel' => IntakeChannel::Online,
                'status' => VisitStatus::AwaitingCheckin,
                'online_active_key' => 'ONLINE-654321',
            ]
        );

        QueueAcquisition::create([
            'visit_id' => $onlineVisit2->id,
            'department_id' => $general->id,
            'channel' => IntakeChannel::Online,
            'status' => QueueAcquisitionStatus::Acquired,
            'idempotency_key' => 'IDEMP-ONLINE-002',
        ]);

        // =========================================================================
        // SCENARIO 2: Reception Queue (Calling, Waiting, Priority)
        // =========================================================================
        // Ticket R001: CALLED at reception desk
        $recVisit1 = Visit::firstOrCreate(
            ['visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0003'],
            [
                'patient_id' => null,
                'department_id' => $general->id,
                'workflow_id' => $genWorkflow->id,
                'priority' => Priority::Normal,
                'intake_channel' => IntakeChannel::Kiosk,
                'status' => VisitStatus::Waiting,
                'checked_in_at' => now()->subMinutes(15),
            ]
        );

        $acq1 = QueueAcquisition::create([
            'visit_id' => $recVisit1->id,
            'department_id' => $general->id,
            'channel' => IntakeChannel::Kiosk,
            'status' => QueueAcquisitionStatus::Acquired,
        ]);

        $ticketR1 = QueueTicket::create([
            'visit_id' => $recVisit1->id,
            'workflow_step_id' => $regStep->id,
            'station_id' => $regStation->id,
            'queue_number' => 'R001',
            'priority' => Priority::Normal,
            'internal_sequence' => 1,
            'status' => QueueTicketStatus::Called,
            'called_at' => now()->subMinutes(2),
            'assigned_to' => 'reception@kiyu.id',
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketR1->id,
            'event_type' => QueueEventType::Called,
            'from_status' => QueueTicketStatus::Created->value,
            'to_status' => QueueTicketStatus::Called->value,
            'user_id' => 'reception@kiyu.id',
        ]);

        // Ticket R002: WAITING (Priority)
        $recVisit2 = Visit::firstOrCreate(
            ['visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0004'],
            [
                'patient_id' => null,
                'department_id' => $general->id,
                'workflow_id' => $genWorkflow->id,
                'priority' => Priority::Priority,
                'intake_channel' => IntakeChannel::Kiosk,
                'status' => VisitStatus::Waiting,
                'checked_in_at' => now()->subMinutes(10),
            ]
        );

        QueueAcquisition::create([
            'visit_id' => $recVisit2->id,
            'department_id' => $general->id,
            'channel' => IntakeChannel::Kiosk,
            'status' => QueueAcquisitionStatus::Acquired,
        ]);

        $ticketR2 = QueueTicket::create([
            'visit_id' => $recVisit2->id,
            'workflow_step_id' => $regStep->id,
            'station_id' => $regStation->id,
            'queue_number' => 'R002',
            'priority' => Priority::Priority,
            'internal_sequence' => 2,
            'status' => QueueTicketStatus::Created,
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketR2->id,
            'event_type' => QueueEventType::Created,
            'to_status' => QueueTicketStatus::Created->value,
        ]);

        // Ticket R003: WAITING (Normal)
        $recVisit3 = Visit::firstOrCreate(
            ['visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0005'],
            [
                'patient_id' => null,
                'department_id' => $general->id,
                'workflow_id' => $genWorkflow->id,
                'priority' => Priority::Normal,
                'intake_channel' => IntakeChannel::Manual,
                'status' => VisitStatus::Waiting,
                'checked_in_at' => now()->subMinutes(5),
            ]
        );

        QueueAcquisition::create([
            'visit_id' => $recVisit3->id,
            'department_id' => $general->id,
            'channel' => IntakeChannel::Manual,
            'status' => QueueAcquisitionStatus::Acquired,
        ]);

        $ticketR3 = QueueTicket::create([
            'visit_id' => $recVisit3->id,
            'workflow_step_id' => $regStep->id,
            'station_id' => $regStation->id,
            'queue_number' => 'R003',
            'priority' => Priority::Normal,
            'internal_sequence' => 3,
            'status' => QueueTicketStatus::Created,
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketR3->id,
            'event_type' => QueueEventType::Created,
            'to_status' => QueueTicketStatus::Created->value,
        ]);

        // =========================================================================
        // SCENARIO 3: Nurse Station Queue (Patients registered, waiting for vitals)
        // =========================================================================
        $nurseVisit1 = Visit::firstOrCreate([
            'patient_id' => $patients[2]->id,
            'department_id' => $general->id,
            'workflow_id' => $genWorkflow->id,
            'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0006',
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Kiosk,
            'status' => VisitStatus::Waiting,
            'registered_at' => now()->subMinutes(20),
            'registered_by' => 'reception@kiyu.id',
        ]);

        $ticketN1 = QueueTicket::firstOrCreate(
            ['queue_number' => 'N001', 'station_id' => $nurseStation->id],
            [
                'visit_id' => $nurseVisit1->id,
                'workflow_step_id' => $nurseStep->id,
                'station_id' => $nurseStation->id,
                'priority' => Priority::Normal,
                'internal_sequence' => 1,
                'status' => QueueTicketStatus::Called,
                'called_at' => now()->subMinutes(1),
                'assigned_to' => 'nurse@kiyu.id',
            ]
        );

        QueueEvent::create([
            'queue_ticket_id' => $ticketN1->id,
            'event_type' => QueueEventType::Called,
            'from_status' => QueueTicketStatus::Created->value,
            'to_status' => QueueTicketStatus::Called->value,
            'user_id' => 'nurse@kiyu.id',
        ]);

        $nurseVisit2 = Visit::firstOrCreate([
            'patient_id' => $patients[3]->id,
            'department_id' => $general->id,
            'workflow_id' => $genWorkflow->id,
            'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0007',
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Kiosk,
            'status' => VisitStatus::Waiting,
            'registered_at' => now()->subMinutes(15),
            'registered_by' => 'reception@kiyu.id',
        ]);

        $ticketN2 = QueueTicket::create([
            'visit_id' => $nurseVisit2->id,
            'workflow_step_id' => $nurseStep->id,
            'station_id' => $nurseStation->id,
            'queue_number' => 'N002',
            'priority' => Priority::Normal,
            'internal_sequence' => 2,
            'status' => QueueTicketStatus::Created,
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketN2->id,
            'event_type' => QueueEventType::Created,
            'to_status' => QueueTicketStatus::Created->value,
        ]);

        // =========================================================================
        // SCENARIO 4: Doctor Station (In-Progress Consultation)
        // =========================================================================
        // Patient actively being consulted by doctor
        $docVisit1 = Visit::firstOrCreate([
            'patient_id' => $patients[4]->id,
            'department_id' => $general->id,
            'workflow_id' => $genWorkflow->id,
            'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0008',
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Kiosk,
            'status' => VisitStatus::InProgress, // CRITICAL: Visit is InProgress
            'registered_at' => now()->subMinutes(40),
            'registered_by' => 'reception@kiyu.id',
        ]);

        $ticketD1 = QueueTicket::create([
            'visit_id' => $docVisit1->id,
            'workflow_step_id' => $docStep->id,
            'station_id' => $docStation->id,
            'queue_number' => 'D1001',
            'priority' => Priority::Normal,
            'internal_sequence' => 1,
            'status' => QueueTicketStatus::InProgress,
            'called_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(8),
            'assigned_to' => 'doctor1@kiyu.id',
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketD1->id,
            'event_type' => QueueEventType::Started,
            'from_status' => QueueTicketStatus::Called->value,
            'to_status' => QueueTicketStatus::InProgress->value,
            'user_id' => 'doctor1@kiyu.id',
        ]);

        // Patient waiting for doctor
        $docVisit2 = Visit::firstOrCreate([
            'patient_id' => $patients[5]->id,
            'department_id' => $general->id,
            'workflow_id' => $genWorkflow->id,
            'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0009',
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Kiosk,
            'status' => VisitStatus::Waiting,
            'registered_at' => now()->subMinutes(25),
        ]);

        $ticketD2 = QueueTicket::create([
            'visit_id' => $docVisit2->id,
            'workflow_step_id' => $docStep->id,
            'station_id' => $docStation->id,
            'queue_number' => 'D1002',
            'priority' => Priority::Normal,
            'internal_sequence' => 2,
            'status' => QueueTicketStatus::Created,
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketD2->id,
            'event_type' => QueueEventType::Created,
            'to_status' => QueueTicketStatus::Created->value,
        ]);

        // =========================================================================
        // SCENARIO 5: Pharmacy Station (Prescription Pickup Waiting)
        // =========================================================================
        $phaVisit = Visit::firstOrCreate([
            'patient_id' => $patients[6]->id,
            'department_id' => $general->id,
            'workflow_id' => $genWorkflow->id,
            'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0010',
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Kiosk,
            'status' => VisitStatus::InProgress,
            'registered_at' => now()->subMinutes(50),
        ]);

        $ticketP1 = QueueTicket::create([
            'visit_id' => $phaVisit->id,
            'workflow_step_id' => $phaStep->id,
            'station_id' => $phaStation->id,
            'queue_number' => 'P001',
            'priority' => Priority::Normal,
            'internal_sequence' => 1,
            'status' => QueueTicketStatus::Created,
        ]);

        QueueEvent::create([
            'queue_ticket_id' => $ticketP1->id,
            'event_type' => QueueEventType::Created,
            'to_status' => QueueTicketStatus::Created->value,
        ]);

        // =========================================================================
        // SCENARIO 6: Completed Visit History
        // =========================================================================
        $completedVisit = Visit::firstOrCreate([
            'patient_id' => $patients[7]->id,
            'department_id' => $general->id,
            'workflow_id' => $genWorkflow->id,
            'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-0011',
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Kiosk,
            'status' => VisitStatus::Completed,
            'checked_in_at' => now()->subHours(2),
            'registered_at' => now()->subHours(2)->addMinutes(10),
            'completed_at' => now()->subHours(1),
        ]);
    }
}

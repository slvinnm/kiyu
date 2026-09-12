<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\Department;
use App\Models\Visit;
use App\Models\WorkflowVersion;
use App\Models\WorkflowStep;
use App\Models\VisitWorkflow;
use App\Models\VisitWorkflowStep;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    public function createFromIntake(Visit $visit, int $initialStepSequence): ?QueueTicket
    {
        return DB::transaction(function () use ($visit, $initialStepSequence) {
            // 1. Establish visit workflow context
            $workflowVersion = $visit->workflowVersion;
            $workflowSteps = $workflowVersion->steps();

            // 2. Get the initial step
            $initialStep = $workflowSteps->firstWhere('sequence', $initialStepSequence);
            if (! $initialStep) {
                return null;
            }

            // 3. Create the visit workflow step
            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $visit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            $visitWorkflowStep = VisitWorkflowStep::create([
                'visit_workflow_id' => $visitWorkflow->id,
                'workflow_step_id' => $initialStep->id,
                'status' => VisitWorkflowStepStatus::PENDING->value,
            ]);

            // 4. If the step requires a queue, create a queue ticket
            if ($initialStep->requires_queue) {
                $station = $initialStep->station;
                if ($station) {
                    $number = (new QueueNumberGenerator())->generate($station);
                    $ticket = QueueTicket::create([
                        'visit_id' => $visit->id,
                        'visit_workflow_step_id' => $visitWorkflowStep->id,
                        'station_id' => $station->id,
                        'queue_number' => $number,
                        'priority' => Priority::NORMAL->value,
                        'internal_sequence' => 0,
                        'status' => QueueStatus::CREATED->value,
                    ]);

                    // Log creation event
                    $ticket->events()->create([
                        'event_type' => \App\Enums\QueueEventType::CREATED,
                        'from_status' => null,
                        'to_status' => QueueStatus::CREATED->value,
                    ]);

                    return $ticket;
                }
            }

            return null;
        });
    }

    public function completeCurrentStep(QueueTicket $ticket): ?array
    {
        return DB::transaction(function () use ($ticket) {
            $visitWorkflowStep = $ticket->visitWorkflowStep;
            $visit = $ticket->visit;

            // 1. Mark visit workflow step as completed
            $visitWorkflowStep->update([
                'status' => \App\Enums\VisitWorkflowStepStatus::COMPLETED->value,
                'completed_at' => now(),
            ]);

            // 2. Mark queue ticket as completed
            $ticket->update([
                'status' => \App\Enums\QueueStatus::COMPLETED->value,
                'completed_at' => now(),
            ]);

            // 3. Log completion event
            $ticket->events()->create([
                'event_type' => \App\Enums\QueueEventType::COMPLETED,
                'from_status' => \App\Enums\QueueStatus::IN_PROGRESS->value,
                'to_status' => \App\Enums\QueueStatus::COMPLETED->value,
            ]);

            // 4. Determine next applicable step
            $workflowVersion = $visit->workflowVersion;
            $steps = $workflowVersion->steps();
            $currentSequence = $visitWorkflowStep->workflowStep->sequence;

            $nextStep = null;
            foreach ($steps as $step) {
                if ($step->sequence > $currentSequence) {
                    $nextStep = $step;
                    break;
                }
            }

            // Also check for optional steps that might come earlier
            if (! $nextStep) {
                // Check for optional steps that were skipped past
                $nextStep = $steps->firstWhere(fn($s) => $s->is_optional && $s->sequence > $currentSequence);
            }

            // 5. Check for completion
            if (! $nextStep) {
                // No more steps, mark visit as complete
                $visit->update([
                    'status' => \App\Enums\VisitStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);

                return ['next_step' => null, 'visit_completed' => true, 'ticket' => $ticket->fresh()];
            }

            // 6. Create next visit workflow step
            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $visit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            $nextVisitWorkflowStep = VisitWorkflowStep::create([
                'visit_workflow_id' => $visitWorkflow->id,
                'workflow_step_id' => $nextStep->id,
                'status' => VisitWorkflowStepStatus::PENDING->value,
            ]);

            // 7. Create next queue ticket if step requires queue
            if ($nextStep->requires_queue) {
                $station = $nextStep->station;
                if ($station) {
                    $number = (new QueueNumberGenerator())->generate($station);
                    $nextTicket = QueueTicket::create([
                        'visit_id' => $visit->id,
                        'visit_workflow_step_id' => $nextVisitWorkflowStep->id,
                        'station_id' => $station->id,
                        'queue_number' => $number,
                        'priority' => Priority::NORMAL->value,
                        'internal_sequence' => 0,
                        'status' => \App\Enums\QueueStatus::CREATED->value,
                    ]);

                    // Log creation event
                    $nextTicket->events()->create([
                        'event_type' => \App\Enums\QueueEventType::CREATED,
                        'from_status' => null,
                        'to_status' => \App\Enums\QueueStatus::CREATED->value,
                    ]);

                    return ['next_step' => $nextStep, 'visit_completed' => false, 'ticket' => $nextTicket->fresh()];
                }
            }

            return ['next_step' => $nextStep, 'visit_completed' => false, 'ticket' => $nextVisitWorkflowStep->fresh()];
        });
    }

    public function createOrUpdateVisitWorkflowStep(VisitWorkflow $workflow, WorkflowStep $step): VisitWorkflowStep
    {
        return VisitWorkflowStep::updateOrCreate([
            'visit_workflow_id' => $workflow->id,
            'workflow_step_id' => $step->id,
        ], [
            'status' => VisitWorkflowStepStatus::PENDING->value,
        ]);
    }
}
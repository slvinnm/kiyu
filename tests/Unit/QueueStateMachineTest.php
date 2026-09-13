<?php

use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use App\Services\QueueStateMachine;

it('accepts only the defined queue transitions', function (): void {
    $machine = new QueueStateMachine;
    $created = new QueueTicket(['status' => QueueStatus::CREATED]);
    $completed = new QueueTicket(['status' => QueueStatus::COMPLETED]);

    expect($machine->canTransition($created, QueueStatus::CALLED))->toBeTrue();
    expect($machine->canTransition($created, QueueStatus::COMPLETED))->toBeFalse();
    expect($machine->canTransition($created, QueueStatus::CREATED))->toBeFalse();
    expect($machine->canTransition($completed, QueueStatus::CANCELLED))->toBeFalse();
});

it('identifies tickets eligible for each queue action', function (): void {
    $machine = new QueueStateMachine;
    $created = new QueueTicket(['status' => QueueStatus::CREATED]);
    $called = new QueueTicket(['status' => QueueStatus::CALLED]);
    $inProgress = new QueueTicket(['status' => QueueStatus::IN_PROGRESS]);
    $onHold = new QueueTicket(['status' => QueueStatus::ON_HOLD]);

    expect($machine->isEligibleForCall($created))->toBeTrue()
        ->and($machine->isEligibleForNextSelection($created))->toBeTrue()
        ->and($machine->isEligibleToStart($called))->toBeTrue()
        ->and($machine->isEligibleForCompletion($inProgress))->toBeTrue()
        ->and($machine->isEligibleForResume($onHold))->toBeTrue()
        ->and($machine->isEligibleForSkip($created))->toBeTrue()
        ->and($machine->isEligibleForSkip($called))->toBeTrue()
        ->and($machine->isEligibleForSkip($inProgress))->toBeTrue()
        ->and($machine->isEligibleForCall($onHold))->toBeFalse();
});

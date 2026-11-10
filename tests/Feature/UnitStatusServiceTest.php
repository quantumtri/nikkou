<?php

use App\Enums\UnitStatus;
use App\Exceptions\InvalidUnitTransition;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitStatusService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(fn () => $this->service = app(UnitStatusService::class));

it('starts new units as available', function () {
    expect(Unit::factory()->create()->refresh()->status)->toBe(UnitStatus::Available);
});

it('allows the transitions from the spec', function (UnitStatus $from, UnitStatus $to) {
    $unit = Unit::factory()->status($from)->create();

    expect($this->service->transition($unit, $to)->status)->toBe($to);
})->with([
    'available to on loan' => [UnitStatus::Available, UnitStatus::OnLoan],
    'on loan to available' => [UnitStatus::OnLoan, UnitStatus::Available],
    'on loan to needs service' => [UnitStatus::OnLoan, UnitStatus::NeedsService],
    'needs service to in service' => [UnitStatus::NeedsService, UnitStatus::InService],
    'in service to available' => [UnitStatus::InService, UnitStatus::Available],
]);

it('rejects transitions outside the spec', function (UnitStatus $from, UnitStatus $to) {
    $unit = Unit::factory()->status($from)->create();

    expect(fn () => $this->service->transition($unit, $to))->toThrow(InvalidUnitTransition::class);
    expect($unit->refresh()->status)->toBe($from);
})->with([
    'on loan to on loan' => [UnitStatus::OnLoan, UnitStatus::OnLoan],
    'needs service to on loan' => [UnitStatus::NeedsService, UnitStatus::OnLoan],
    'in service to on loan' => [UnitStatus::InService, UnitStatus::OnLoan],
    'sold to available' => [UnitStatus::Sold, UnitStatus::Available],
    'sold to on loan' => [UnitStatus::Sold, UnitStatus::OnLoan],
]);

it('only treats available units as loanable', function (UnitStatus $status, bool $loanable) {
    expect($this->service->isLoanable(Unit::factory()->status($status)->make()))->toBe($loanable);
})->with([
    [UnitStatus::Available, true],
    [UnitStatus::OnLoan, false],
    [UnitStatus::NeedsService, false],
    [UnitStatus::InService, false],
    [UnitStatus::Sold, false],
]);

it('writes an audit log entry for every status change', function () {
    $actor = User::factory()->sales()->create();
    $unit = Unit::factory()->create();

    $this->service->transition($unit, UnitStatus::OnLoan, $actor, 'Dipinjam untuk home trial');

    $log = ActivityLog::where('subject_type', 'unit')->where('subject_id', $unit->id)->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->action)->toBe('unit.status_changed')
        ->and($log->from)->toBe('available')
        ->and($log->to)->toBe('on_loan')
        ->and($log->notes)->toBe('Dipinjam untuk home trial');
});

it('lets an admin override with a reason and logs it', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->status(UnitStatus::NeedsService)->create();

    $this->service->forceTransition($unit, UnitStatus::OnLoan, $admin, 'Unit sudah diperiksa di lokasi');

    expect($unit->refresh()->status)->toBe(UnitStatus::OnLoan);
    expect(ActivityLog::where('action', 'unit.status_overridden')->sole()->notes)->toBe('Unit sudah diperiksa di lokasi');
});

it('refuses an override from non-admins', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $unit = Unit::factory()->status(UnitStatus::OnLoan)->create();

    expect(fn () => $this->service->forceTransition($unit, UnitStatus::Available, $user, 'alasan'))->toThrow(HttpException::class);
    expect($unit->refresh()->status)->toBe(UnitStatus::OnLoan);
})->with(['sales', 'warehouse']);

it('refuses an override without a reason', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->status(UnitStatus::OnLoan)->create();

    expect(fn () => $this->service->forceTransition($unit, UnitStatus::Available, $admin, '  '))->toThrow(InvalidArgumentException::class);
});

it('does not allow status to be mass assigned', function () {
    $unit = Unit::factory()->create();

    expect(fn () => $unit->update(['status' => 'sold']))->toThrow(MassAssignmentException::class);
    expect($unit->refresh()->status)->toBe(UnitStatus::Available);
});

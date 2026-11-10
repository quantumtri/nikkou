<?php

use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->log = ActivityLog::record(Unit::factory()->create(), 'unit.created', User::factory()->admin()->create());
});

it('cannot be updated through the model', function () {
    expect(fn () => $this->log->update(['notes' => 'diubah']))->toThrow(LogicException::class);
});

it('cannot be deleted through the model', function () {
    expect(fn () => $this->log->delete())->toThrow(LogicException::class);
});

it('cannot be updated or deleted with raw queries on PostgreSQL', function (string $sql) {
    expect(fn () => DB::transaction(fn () => DB::statement($sql)))->toThrow(QueryException::class);
    expect(ActivityLog::count())->toBe(1);
})->with([
    'update' => "update activity_logs set notes = 'x'",
    'delete' => 'delete from activity_logs',
])->skip(fn () => DB::getDriverName() !== 'pgsql', 'Trigger only exists on PostgreSQL.');

it('is closed to every role by policy', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect($user->can('update', $this->log))->toBeFalse()
        ->and($user->can('delete', $this->log))->toBeFalse()
        ->and($user->can('create', ActivityLog::class))->toBeFalse();
})->with(['admin', 'sales', 'warehouse']);

it('is readable by admins only', function () {
    expect(User::factory()->admin()->create()->can('viewAny', ActivityLog::class))->toBeTrue()
        ->and(User::factory()->sales()->create()->can('viewAny', ActivityLog::class))->toBeFalse()
        ->and(User::factory()->warehouse()->create()->can('viewAny', ActivityLog::class))->toBeFalse();
});

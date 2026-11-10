<?php

use App\Models\Lead;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->dewi = User::factory()->sales()->create();
    $this->rizky = User::factory()->sales()->create();
    $this->warehouse = User::factory()->warehouse()->create();
});

it('scopes loans to the owning sales on the server', function () {
    $mine = Loan::factory()->for($this->dewi, 'sales')->create();
    $other = Loan::factory()->for($this->rizky, 'sales')->create();

    expect(Loan::visibleTo($this->dewi)->pluck('id')->all())->toBe([$mine->id])
        ->and(Loan::visibleTo($this->admin)->count())->toBe(2)
        ->and(Loan::visibleTo($this->warehouse)->count())->toBe(0);

    expect($this->dewi->can('view', $mine))->toBeTrue()
        ->and($this->dewi->can('view', $other))->toBeFalse()
        ->and($this->warehouse->can('view', $mine))->toBeFalse();
});

it('scopes leads to the owning sales on the server', function () {
    $mine = Lead::factory()->for($this->dewi, 'sales')->create();
    $other = Lead::factory()->for($this->rizky, 'sales')->create();

    expect(Lead::visibleTo($this->dewi)->pluck('id')->all())->toBe([$mine->id])
        ->and(Lead::visibleTo($this->admin)->count())->toBe(2)
        ->and(Lead::visibleTo($this->warehouse)->count())->toBe(0);

    expect($this->dewi->can('update', $other))->toBeFalse()
        ->and($this->admin->can('update', $other))->toBeTrue();
});

it('lets admin and sales create loans but not warehouse', function () {
    expect($this->admin->can('create', Loan::class))->toBeTrue()
        ->and($this->dewi->can('create', Loan::class))->toBeTrue()
        ->and($this->warehouse->can('create', Loan::class))->toBeFalse();
});

it('reserves the loan override for admins', function () {
    expect($this->admin->can('override', Loan::class))->toBeTrue()
        ->and($this->dewi->can('override', Loan::class))->toBeFalse()
        ->and($this->warehouse->can('override', Loan::class))->toBeFalse();
});

it('lets admin, warehouse and the owning sales process a return', function () {
    $loan = Loan::factory()->for($this->dewi, 'sales')->create();

    expect($this->admin->can('processReturn', $loan))->toBeTrue()
        ->and($this->warehouse->can('processReturn', $loan))->toBeTrue()
        ->and($this->dewi->can('processReturn', $loan))->toBeTrue()
        ->and($this->rizky->can('processReturn', $loan))->toBeFalse();

    $returned = Loan::factory()->returned()->for($this->dewi, 'sales')->create();
    expect($this->admin->can('processReturn', $returned))->toBeFalse();
});

it('limits inventory input to admin and warehouse', function () {
    $unit = Unit::factory()->create();

    expect($this->admin->can('create', Unit::class))->toBeTrue()
        ->and($this->warehouse->can('create', Unit::class))->toBeTrue()
        ->and($this->dewi->can('create', Unit::class))->toBeFalse()
        ->and($this->dewi->can('viewAny', Unit::class))->toBeFalse()
        ->and($this->dewi->can('view', $unit))->toBeTrue()
        ->and($this->admin->can('delete', $unit))->toBeFalse();
});

it('limits account management to admins', function () {
    expect($this->admin->can('viewAny', User::class))->toBeTrue()
        ->and($this->dewi->can('viewAny', User::class))->toBeFalse()
        ->and($this->warehouse->can('create', User::class))->toBeFalse();
});

it('flags a loan as overdue only while active and past due', function () {
    expect(Loan::factory()->overdue()->make()->isOverdue())->toBeTrue()
        ->and(Loan::factory()->make()->isOverdue())->toBeFalse()
        ->and(Loan::factory()->overdue()->returned()->make()->isOverdue())->toBeFalse();
});

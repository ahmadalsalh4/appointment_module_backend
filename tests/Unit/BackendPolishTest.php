<?php

use App\Support\Concerns\HandlesUniqueViolation;
use App\Support\Exceptions\AppointmentCategoryMismatchException;
use App\Support\Exceptions\AppointmentConflictException;
use App\Support\Exceptions\AppointmentException;
use App\Support\Exceptions\AppointmentForbiddenException;
use App\Support\Exceptions\AppointmentOutOfHoursException;
use App\Support\Exceptions\AppointmentWrongStateException;
use App\Support\WorkingHoursChecker;
use Carbon\Carbon;

it('exposes a stable status code for every appointment exception', function () {
    $cases = [
        [new AppointmentConflictException, 409],
        [new AppointmentWrongStateException, 422],
        [new AppointmentForbiddenException, 403],
        [new AppointmentOutOfHoursException, 422],
        [new AppointmentCategoryMismatchException, 422],
    ];

    foreach ($cases as [$exception, $code]) {
        expect($exception)->toBeInstanceOf(AppointmentException::class);
        expect($exception->statusCode)->toBe($code);
        expect($exception->getMessage())->toBeString()->not->toBeEmpty();
    }
});

it('WorkingHoursChecker accepts a slot fully inside a block', function () {
    $tz = 'Europe/Istanbul';
    $start = Carbon::parse('2026-08-10 10:00:00', $tz);
    $end = Carbon::parse('2026-08-10 10:30:00', $tz);

    expect(WorkingHoursChecker::isWithin($start, $end, [
        ['start' => '09:00', 'end' => '12:00'],
    ]))->toBeTrue();
});

it('WorkingHoursChecker rejects a slot spanning midnight into the next day', function () {
    $tz = 'Europe/Istanbul';
    $start = Carbon::parse('2026-08-10 23:30:00', $tz);
    $end = Carbon::parse('2026-08-11 00:30:00', $tz);

    expect(WorkingHoursChecker::isWithin($start, $end, [
        ['start' => '09:00', 'end' => '17:00'],
    ]))->toBeFalse();
});

it('WorkingHoursChecker rejects a slot that starts before a block', function () {
    $tz = 'Europe/Istanbul';
    $start = Carbon::parse('2026-08-10 08:30:00', $tz);
    $end = Carbon::parse('2026-08-10 09:30:00', $tz);

    expect(WorkingHoursChecker::isWithin($start, $end, [
        ['start' => '09:00', 'end' => '17:00'],
    ]))->toBeFalse();
});

// Indirect coverage for the trait: we can't easily mock a QueryException
// without a DB, but the public surface area is small and exercised by
// the live integration paths. Skipping response-builder coverage here.
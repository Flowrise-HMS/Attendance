<?php

use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Enums\VerifyType;
use Modules\Attendance\Models\AttendanceRecord;

it('has the correct AttendanceStatus values', function (): void {
    expect(AttendanceStatus::Present->value)->toBe('present')
        ->and(AttendanceStatus::Late->value)->toBe('late')
        ->and(AttendanceStatus::Absent->value)->toBe('absent')
        ->and(AttendanceStatus::OnLeave->value)->toBe('on_leave')
        ->and(AttendanceStatus::Weekend->value)->toBe('weekend')
        ->and(AttendanceStatus::Holiday->value)->toBe('holiday')
        ->and(AttendanceStatus::NoShift->value)->toBe('no_shift')
        ->and(AttendanceStatus::NoPunch->value)->toBe('no_punch');
});

it('has labels for every AttendanceStatus', function (): void {
    expect(AttendanceStatus::Present->getLabel())->toBe('Present')
        ->and(AttendanceStatus::Late->getLabel())->toBe('Late')
        ->and(AttendanceStatus::Absent->getLabel())->toBe('Absent')
        ->and(AttendanceStatus::NoShift->getLabel())->toBe('No shift')
        ->and(AttendanceStatus::NoPunch->getLabel())->toBe('No punch');
});

it('maps AttendanceStatus to Filament colors', function (): void {
    expect(AttendanceStatus::Present->getColor())->toBe('success')
        ->and(AttendanceStatus::Late->getColor())->toBe('warning')
        ->and(AttendanceStatus::Absent->getColor())->toBe('danger')
        ->and(AttendanceStatus::Weekend->getColor())->toBe('gray');
});

it('has the correct PunchType values', function (): void {
    expect(PunchType::In->value)->toBe('in')
        ->and(PunchType::Out->value)->toBe('out')
        ->and(PunchType::BreakIn->value)->toBe('break_in')
        ->and(PunchType::BreakOut->value)->toBe('break_out')
        ->and(PunchType::OvertimeIn->value)->toBe('overtime_in')
        ->and(PunchType::OvertimeOut->value)->toBe('overtime_out')
        ->and(PunchType::Unspecified->value)->toBe('unspecified');
});

it('maps ZK status codes to PunchType', function (): void {
    expect(PunchType::fromStatus(0))->toBe(PunchType::In)
        ->and(PunchType::fromStatus(1))->toBe(PunchType::Out)
        ->and(PunchType::fromStatus(4))->toBe(PunchType::BreakIn)
        ->and(PunchType::fromStatus(5))->toBe(PunchType::BreakOut)
        ->and(PunchType::fromStatus(14))->toBe(PunchType::OvertimeIn)
        ->and(PunchType::fromStatus(15))->toBe(PunchType::OvertimeOut)
        ->and(PunchType::fromStatus(99))->toBe(PunchType::Unspecified);
});

it('has the correct PunchSource values and labels', function (): void {
    expect(PunchSource::Push->value)->toBe('push')
        ->and(PunchSource::Pull->value)->toBe('pull')
        ->and(PunchSource::Import->value)->toBe('import')
        ->and(PunchSource::Manual->value)->toBe('manual')
        ->and(PunchSource::Push->getLabel())->toBe('Push')
        ->and(PunchSource::Pull->getLabel())->toBe('Pull');
});

it('maps verify codes to VerifyType', function (): void {
    expect(VerifyType::fromCode(0))->toBe(VerifyType::Password)
        ->and(VerifyType::fromCode(1))->toBe(VerifyType::Fingerprint)
        ->and(VerifyType::fromCode(2))->toBe(VerifyType::Card)
        ->and(VerifyType::fromCode(3))->toBe(VerifyType::Face)
        ->and(VerifyType::fromCode(99))->toBe(VerifyType::Other)
        ->and(VerifyType::fromCode(null))->toBeNull();
});

it('produces a deterministic dedup hash for the same machine+badge+time', function (): void {
    $first = AttendanceRecord::makeDedupHash('mach-1', '1001', '2026-08-10 08:00:00');
    $second = AttendanceRecord::makeDedupHash('mach-1', '1001', '2026-08-10 08:00:00');

    expect($first)->toBe($second)
        ->and(strlen($first))->toBe(64)
        ->and($first)->not->toBe(AttendanceRecord::makeDedupHash('mach-2', '1001', '2026-08-10 08:00:00'))
        ->and($first)->not->toBe(AttendanceRecord::makeDedupHash('mach-1', '1002', '2026-08-10 08:00:00'))
        ->and($first)->not->toBe(AttendanceRecord::makeDedupHash('mach-1', '1001', '2026-08-10 08:05:00'));
});

it('uses the literal unassigned machine key for null machine', function (): void {
    $unassigned = AttendanceRecord::makeDedupHash('unassigned', '1001', '2026-08-10 08:00:00');

    expect($unassigned)->toBe(AttendanceRecord::makeDedupHash('unassigned', '1001', '2026-08-10 08:00:00'))
        ->and($unassigned)->not->toBe(AttendanceRecord::makeDedupHash('some-machine', '1001', '2026-08-10 08:00:00'));
});

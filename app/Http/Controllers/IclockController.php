<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Classes\Services\AttendanceIngestionService;
use Modules\Attendance\Models\AttendanceMachine;

class IclockController
{
    protected const MAX_BODY_BYTES = 10_485_760; // 10 MB

    public function __construct(private AttendanceIngestionService $ingestion) {}

    public function cdata(Request $request): Response
    {
        $machine = $this->resolveMachine($request);
        $this->touchMachine($machine);

        if ($request->isMethod('POST') && $request->query('table') === 'ATTLOG' && $machine) {
            if ($request->header('Content-Length') > static::MAX_BODY_BYTES || strlen($request->getContent()) > static::MAX_BODY_BYTES) {
                Log::warning('Attendance push rejected: body too large', ['serial' => $machine->serial_number]);

                return $this->ok();
            }

            $this->ingestion->ingestAttlog($machine, (string) $request->getContent());
        }

        return $this->ok();
    }

    public function getrequest(Request $request): Response
    {
        $this->touchMachine($this->resolveMachine($request));

        return $this->ok();
    }

    protected function resolveMachine(Request $request): ?AttendanceMachine
    {
        $serial = trim((string) $request->query('SN', ''));
        if ($serial === '') {
            return null;
        }

        return AttendanceMachine::query()
            ->where('serial_number', $serial)
            ->where('push_enabled', true)
            ->where('is_active', true)
            ->first();
    }

    protected function touchMachine(?AttendanceMachine $machine): void
    {
        if ($machine) {
            $machine->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    protected function ok(): Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}

<?php

namespace OGame\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OGame\Models\FleetMission;
use OGame\Services\FleetMissionProcessingService;

class ProcessFleetMission implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Maximum number of times to retry a failed job.
     */
    public int $tries = 3;

    /**
     * How long (seconds) to consider the job "unique" in the queue.
     * Prevents duplicate jobs for the same mission from piling up when both
     * the scheduler and a per-request dispatch fire close together.
     */
    public int $uniqueFor = 300;

    public function __construct(private int $missionId)
    {
    }

    /**
     * Unique key: one job per mission in the queue at a time.
     */
    public function uniqueId(): string
    {
        return 'fleet_mission_' . $this->missionId;
    }

    public function handle(FleetMissionProcessingService $processor): void
    {
        DB::transaction(function () use ($processor) {
            $mission = FleetMission::where('id', $this->missionId)
                ->lockForUpdate()
                ->first();

            if ($mission === null || $mission->processed) {
                return;
            }

            $processor->updateMission($mission);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessFleetMission: failed for mission {$this->missionId} after {$this->tries} attempts: " . $exception->getMessage());
    }
}

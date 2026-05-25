<?php

namespace OGame\Console\Commands\Scheduler;

use Illuminate\Console\Command;
use OGame\Jobs\ProcessFleetMission;
use OGame\Services\FleetMissionProcessingService;

class DispatchArrivedFleetMissions extends Command
{
    protected $signature = 'ogamex:scheduler:dispatch-fleet-missions';

    protected $description = 'Dispatch queue jobs for all arrived, unprocessed fleet missions.';

    public function handle(FleetMissionProcessingService $processor): void
    {
        $missions = $processor->getGlobalArrivedMissions();

        foreach ($missions as $mission) {
            ProcessFleetMission::dispatch($mission->id);
        }

        if ($missions->count() > 0) {
            $this->info("Dispatched {$missions->count()} fleet mission job(s).");
        }
    }
}

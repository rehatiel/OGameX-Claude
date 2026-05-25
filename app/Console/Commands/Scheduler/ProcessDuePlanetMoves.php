<?php

namespace OGame\Console\Commands\Scheduler;

use Illuminate\Console\Command;
use OGame\Factories\PlanetServiceFactory;
use OGame\Services\BuildingQueueService;
use OGame\Services\DarkMatterService;
use OGame\Services\FleetMissionService;
use OGame\Services\PlanetMoveService;
use OGame\Services\ResearchQueueService;
use OGame\Services\SettingsService;
use OGame\Services\UnitQueueService;

class ProcessDuePlanetMoves extends Command
{
    protected $signature = 'ogamex:scheduler:process-planet-moves';

    protected $description = 'Process planet relocations that have completed their 24-hour countdown.';

    public function handle(
        PlanetMoveService $planetMoveService,
        PlanetServiceFactory $planetServiceFactory,
        DarkMatterService $darkMatterService,
        SettingsService $settingsService,
        BuildingQueueService $buildingQueueService,
        ResearchQueueService $researchQueueService,
        UnitQueueService $unitQueueService,
        FleetMissionService $fleetMissionService,
    ): void {
        $planetMoveService->processDueMoves(
            $planetServiceFactory,
            $darkMatterService,
            $settingsService,
            $buildingQueueService,
            $researchQueueService,
            $unitQueueService,
            $fleetMissionService,
        );
    }
}

<?php

namespace OGame\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use OGame\Enums\FleetMissionType;
use OGame\Factories\GameMissionFactory;
use OGame\Factories\PlanetServiceFactory;
use OGame\GameMessages\AcsDefendArrivalHost;
use OGame\GameMessages\AcsDefendArrivalSender;
use OGame\Models\FleetMission;

/**
 * Player-agnostic fleet mission processing. Used by the queue worker and scheduler.
 * Does not depend on PlayerService so it can be resolved without a player context.
 */
class FleetMissionProcessingService
{
    private FleetMission $model;

    public function __construct(
        private MessageService $messageService,
        private GameMissionFactory $gameMissionFactory,
    ) {
        $this->model = new FleetMission();
    }

    /**
     * Get all arrived, unprocessed missions globally (across all players/planets).
     * Used by the scheduler to dispatch queue jobs.
     *
     * @return Collection<int, FleetMission>
     */
    public function getGlobalArrivedMissions(): Collection
    {
        $currentTime = Date::now()->timestamp;

        return $this->model
            ->where('processed', 0)
            ->where(function ($query) use ($currentTime) {
                $query->where('time_arrival', '<=', $currentTime)
                    ->orWhere(function ($query) use ($currentTime) {
                        // ACS Defend: include missions that have physically arrived but are still holding
                        $query->where('mission_type', FleetMissionType::AcsDefend)
                            ->whereNull('parent_id')
                            ->whereNotNull('time_physical_arrival')
                            ->where('time_physical_arrival', '<=', $currentTime);
                    });
            })
            ->get();
    }

    /**
     * Get arrived, unprocessed missions for the given planet IDs.
     * Used for per-player processing.
     *
     * @param int[] $planetIds
     * @return Collection<int, FleetMission>
     */
    public function getArrivedMissionsByPlanetIds(array $planetIds): Collection
    {
        $currentTime = Date::now()->timestamp;

        $missions = $this->model
            ->where(function ($query) use ($planetIds) {
                $query->whereIn('planet_id_from', $planetIds)
                    ->orWhereIn('planet_id_to', $planetIds);
            })
            ->where('processed', 0)
            ->where(function ($query) use ($currentTime) {
                $query->where('time_arrival', '<=', $currentTime)
                    ->orWhere(function ($query) use ($currentTime) {
                        // ACS Defend: include missions that have physically arrived but are still holding
                        $query->where('mission_type', FleetMissionType::AcsDefend)
                            ->whereNull('parent_id')
                            ->whereNotNull('time_physical_arrival')
                            ->where('time_physical_arrival', '<=', $currentTime);
                    });
            })
            ->get();

        // Filter out missions whose full hold time hasn't elapsed yet
        return $missions->filter(function ($mission) use ($currentTime) {
            // ACS Defend outbound: process immediately when physically arrived (hold time handled separately)
            if ($mission->isAcsDefendOutbound()) {
                return true;
            }

            if ($mission->time_holding !== null) {
                return ($mission->time_arrival + $mission->time_holding) <= $currentTime;
            }

            return true;
        });
    }

    /**
     * Process a single fleet mission. Idempotent: re-checks readiness inside.
     * Callers are responsible for acquiring a DB lock before calling this.
     *
     * @param FleetMission $mission
     * @return void
     */
    public function updateMission(FleetMission $mission): void
    {
        // Reload from DB to ensure freshest data (guard against stale caller-side model)
        $mission = FleetMission::find($mission->id);
        if ($mission === null || $mission->processed) {
            return;
        }

        $currentTime = Date::now()->timestamp;

        // ACS Defend: send physical-arrival messages once, at the moment the fleet arrives (before hold expires)
        if ($mission->isAcsDefendOutbound()
            && $mission->time_physical_arrival !== null
            && $mission->time_physical_arrival <= $currentTime
            && !$mission->hasArrivalMessagesSent()
        ) {
            $mission->processed_hold = 1;
            $mission->save();
            $this->sendAcsDefendArrivalMessages($mission);
        }

        // Determine full processing time (arrival + any hold period)
        $holdTime = ($mission->time_holding !== null && !$mission->isAcsDefendOutbound()) ? $mission->time_holding : 0;
        if (($mission->time_arrival + $holdTime) > $currentTime) {
            return;
        }

        $missionObject = $this->gameMissionFactory->getMissionById($mission->mission_type, [
            'fleetMissionService' => app(FleetMissionService::class),
            'messageService' => $this->messageService,
        ]);
        $missionObject->process($mission);
    }

    /**
     * Send ACS Defend physical-arrival messages to sender and host.
     * Public so FleetMissionService::cancelMission() can call it without duplicating logic.
     *
     * @param FleetMission $mission
     * @return void
     */
    public function sendAcsDefendArrivalMessages(FleetMission $mission): void
    {
        $planetServiceFactory = app(PlanetServiceFactory::class);

        $originPlanet = $planetServiceFactory->make($mission->planet_id_from, true);
        $targetPlanet = $planetServiceFactory->make($mission->planet_id_to, true);

        $this->messageService->sendSystemMessageToPlayer($originPlanet->getPlayer(), AcsDefendArrivalSender::class, [
            'to' => '[planet]' . $mission->planet_id_to . '[/planet]',
        ]);

        $this->messageService->sendSystemMessageToPlayer($targetPlanet->getPlayer(), AcsDefendArrivalHost::class, [
            'to' => '[planet]' . $mission->planet_id_to . '[/planet]',
        ]);
    }
}

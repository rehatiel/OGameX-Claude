<?php

namespace OGame\Services;

use Illuminate\Support\Collection;
use OGame\Enums\FleetMissionType;
use OGame\Factories\GameMissionFactory;
use OGame\Factories\PlanetServiceFactory;
use OGame\Models\Enums\PlanetType;
use OGame\Models\Planet\Coordinate;
use OGame\ViewModels\FleetEventRowViewModel;

class FleetMovementAssembler
{
    public function __construct(
        private readonly PlanetServiceFactory $planetServiceFactory,
        private readonly FleetMissionService $fleetMissionService,
    ) {
    }

    /**
     * Build sorted FleetEventRowViewModel array from a collection of fleet missions.
     *
     * NOTE: calls $planetServiceFactory->make() once per mission row — N+1 to address in a follow-up.
     *
     * @param Collection $missions
     * @return FleetEventRowViewModel[]
     */
    public function buildEventRows(Collection $missions): array
    {
        $fleet_events = [];

        foreach ($missions as $row) {
            $eventRowViewModel = new FleetEventRowViewModel();
            $eventRowViewModel->id = $row->id;
            $eventRowViewModel->mission_type = $row->mission_type->value;
            $eventRowViewModel->mission_label = $this->fleetMissionService->missionTypeToLabel($row->mission_type);
            $eventRowViewModel->mission_time_arrival = $row->time_arrival;
            $eventRowViewModel->time_departure = $row->time_departure;
            $eventRowViewModel->is_return_trip = !empty($row->parent_id);

            if ($eventRowViewModel->is_return_trip) {
                $eventRowViewModel->origin_planet_name = '';
                $eventRowViewModel->origin_planet_coords = new Coordinate($row->galaxy_to, $row->system_to, $row->position_to);
                $eventRowViewModel->origin_planet_type = PlanetType::from($row->type_to);
                if ($row->planet_id_to !== null) {
                    $planetToService = $this->planetServiceFactory->make($row->planet_id_to);
                    if ($planetToService !== null) {
                        $eventRowViewModel->origin_planet_name = $planetToService->getPlanetName();
                        $eventRowViewModel->origin_planet_coords = $planetToService->getPlanetCoordinates();
                        $eventRowViewModel->origin_planet_image_type = $planetToService->getPlanetImageType();
                        $eventRowViewModel->origin_planet_biome_type = $planetToService->getPlanetBiomeType();
                    }
                }

                $eventRowViewModel->destination_planet_name = '';
                $eventRowViewModel->destination_planet_coords = new Coordinate($row->galaxy_from, $row->system_from, $row->position_from);
                $eventRowViewModel->destination_planet_type = PlanetType::from($row->type_from);
                if ($row->planet_id_from !== null) {
                    $planetFromService = $this->planetServiceFactory->make($row->planet_id_from);
                    if ($planetFromService !== null) {
                        $eventRowViewModel->destination_planet_name = $planetFromService->getPlanetName();
                        $eventRowViewModel->destination_planet_coords = $planetFromService->getPlanetCoordinates();
                        $eventRowViewModel->destination_planet_image_type = $planetFromService->getPlanetImageType();
                        $eventRowViewModel->destination_planet_biome_type = $planetFromService->getPlanetBiomeType();
                    }
                }
            } else {
                $eventRowViewModel->origin_planet_name = '';
                $eventRowViewModel->origin_planet_coords = new Coordinate($row->galaxy_from, $row->system_from, $row->position_from);
                $eventRowViewModel->origin_planet_type = PlanetType::from($row->type_from);
                if ($row->planet_id_from !== null) {
                    $planetFromService = $this->planetServiceFactory->make($row->planet_id_from);
                    if ($planetFromService !== null) {
                        $eventRowViewModel->origin_planet_name = $planetFromService->getPlanetName();
                        $eventRowViewModel->origin_planet_coords = $planetFromService->getPlanetCoordinates();
                        $eventRowViewModel->origin_planet_image_type = $planetFromService->getPlanetImageType();
                        $eventRowViewModel->origin_planet_biome_type = $planetFromService->getPlanetBiomeType();
                    }
                }

                $eventRowViewModel->destination_planet_name = '';
                $eventRowViewModel->destination_planet_coords = new Coordinate($row->galaxy_to, $row->system_to, $row->position_to);
                $eventRowViewModel->destination_planet_type = PlanetType::from($row->type_to);

                if ($row->planet_id_to !== null) {
                    $planetToService = $this->planetServiceFactory->make($row->planet_id_to);
                    if ($planetToService !== null) {
                        $eventRowViewModel->destination_planet_name = $planetToService->getPlanetName();
                        $eventRowViewModel->destination_planet_coords = $planetToService->getPlanetCoordinates();
                        $eventRowViewModel->destination_planet_image_type = $planetToService->getPlanetImageType();
                        $eventRowViewModel->destination_planet_biome_type = $planetToService->getPlanetBiomeType();
                    }
                }
            }

            $eventRowViewModel->fleet_unit_count = $this->fleetMissionService->getFleetUnitCount($row);
            $eventRowViewModel->fleet_units = $this->fleetMissionService->getFleetUnits($row);
            $eventRowViewModel->resources = $this->fleetMissionService->getResources($row);

            $eventRowViewModel->active_recall_time = time() + (time() - $row->time_departure);

            $mission = GameMissionFactory::getMissionById($row->mission_type, []);
            $eventRowViewModel->friendly_status = $mission::getFriendlyStatus()->value;
            $isRelocationTransfer = ($row->mission_type === FleetMissionType::Deployment && $row->planet_id_from === $row->planet_id_to);
            $eventRowViewModel->is_recallable = ($row->mission_type !== FleetMissionType::MissileAttack && !$isRelocationTransfer);

            $eventRowViewModel->union_id = $row->union_id;

            // ACS Attack outbound trips display as regular Attack in the movement page
            if ($row->mission_type === FleetMissionType::AcsAttack && $row->union_id !== null && !$eventRowViewModel->is_return_trip) {
                $eventRowViewModel->mission_type = 1;
                $eventRowViewModel->mission_label = $this->fleetMissionService->missionTypeToLabel(1);
                $eventRowViewModel->alliance_name = 'KV' . $row->id;
            }

            $eventRowViewModel->can_create_federation = (
                in_array($row->mission_type, [FleetMissionType::Attack, FleetMissionType::AcsAttack]) &&
                !$eventRowViewModel->is_return_trip
            );

            if ($this->fleetMissionService->missionHasReturnMission($eventRowViewModel->mission_type) && !$eventRowViewModel->is_return_trip) {
                $eventRowViewModel->has_return_trip = true;
                $eventRowViewModel->return_time_arrival = $row->time_arrival + ($row->time_arrival - $row->time_departure) + ($row->time_holding ?? 0);
            }

            $currentTime = time();
            $eventRowViewModel->is_at_destination = $eventRowViewModel->has_return_trip && $eventRowViewModel->mission_time_arrival <= $currentTime;

            if ($eventRowViewModel->is_at_destination && $eventRowViewModel->return_time_arrival) {
                $travelTime = $eventRowViewModel->mission_time_arrival - $eventRowViewModel->time_departure;
                $expeditionEndTime = $eventRowViewModel->return_time_arrival - $travelTime;
                $eventRowViewModel->timer_time = $expeditionEndTime;
            } else {
                $eventRowViewModel->timer_time = $eventRowViewModel->mission_time_arrival;
            }

            $eventRowViewModel->remaining_time = max(0, $eventRowViewModel->timer_time - $currentTime);
            $eventRowViewModel->duration = max(1, $eventRowViewModel->mission_time_arrival - $eventRowViewModel->time_departure);

            if ($eventRowViewModel->has_return_trip && $eventRowViewModel->return_time_arrival) {
                $eventRowViewModel->return_remaining_time = max(0, $eventRowViewModel->return_time_arrival - $currentTime);
            }

            $fleet_events[] = $eventRowViewModel;
        }

        usort($fleet_events, fn ($a, $b) => $a->mission_time_arrival - $b->mission_time_arrival);

        return $fleet_events;
    }
}

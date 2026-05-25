<?php

namespace OGame\Services;

use Exception;
use OGame\Enums\FleetMissionType;
use OGame\Factories\PlanetServiceFactory;
use OGame\GameMessages\FleetUnionInvite as FleetUnionInviteMessage;
use OGame\Models\FleetMission;
use OGame\Models\FleetUnion;
use OGame\Models\FleetUnionInvite;
use OGame\Models\Planet\Coordinate;
use OGame\Models\User;

/**
 * Class FleetUnionService.
 *
 * Handles fleet union creation and management for ACS Attack missions.
 *
 * @package OGame\Services
 */
class FleetUnionService
{
    /**
     * Maximum delay percentage (30% of remaining time).
     */
    private const MAX_DELAY_PERCENTAGE = 0.30;

    /**
     * FleetUnionService constructor.
     */
    public function __construct(
        private readonly BuddyService $buddyService,
        private readonly AllianceService $allianceService,
        private readonly PlanetServiceFactory $planetServiceFactory,
        private readonly MessageService $messageService,
    ) {
    }

    /**
     * Create a new fleet union from an existing attack mission.
     *
     * @param FleetMission $mission The initial attack mission to convert to a union
     * @param string|null $name Optional name for the union
     * @return FleetUnion
     * @throws Exception
     */
    public function createUnion(FleetMission $mission, string|null $name = null): FleetUnion
    {
        // Validate mission type (must be attack - type 1)
        if ($mission->mission_type !== FleetMissionType::Attack) {
            throw new Exception(__('t_acs.error_invalid_mission_type'));
        }

        // Validate mission is still in flight
        if ($mission->processed || $mission->canceled) {
            throw new Exception(__('t_acs.error_mission_not_active'));
        }

        // Validate mission is not already in a union
        if ($mission->isInUnion()) {
            throw new Exception(__('t_acs.error_already_in_union'));
        }

        // Create the union
        $union = FleetUnion::create([
            'user_id' => $mission->user_id,
            'name' => $name,
            'galaxy_to' => $mission->galaxy_to,
            'system_to' => $mission->system_to,
            'position_to' => $mission->position_to,
            'planet_type_to' => $mission->type_to,
            'time_arrival' => $mission->time_arrival,
            'max_fleets' => 16,
            'max_players' => 5,
        ]);

        // Link the mission to the union and convert to ACS Attack
        $mission->union_id = $union->id;
        $mission->union_slot = 1; // Initiator always gets slot 1
        $mission->mission_type = FleetMissionType::AcsAttack;
        $mission->save();

        return $union;
    }

    /**
     * Join an existing union with a fleet mission.
     *
     * @param FleetUnion $union The union to join
     * @param FleetMission $mission The fleet mission joining the union
     * @return void
     * @throws Exception
     */
    public function joinUnion(FleetUnion $union, FleetMission $mission): void
    {
        // Validate union hasn't reached max fleets
        if ($union->hasReachedMaxFleets()) {
            throw new Exception(__('t_acs.error_max_fleets_reached'));
        }

        // Validate union hasn't reached max players (if this is a new player)
        $isNewPlayer = !$union->activeFleetMissions()
            ->where('user_id', $mission->user_id)
            ->exists();

        if ($isNewPlayer && $union->hasReachedMaxPlayers()) {
            throw new Exception(__('t_acs.error_max_players_reached'));
        }

        // Validate player is ally or buddy of union creator
        $creatorUserId = $union->user_id;
        $joiningUserId = $mission->user_id;

        if (!$this->isAllyOrBuddy($creatorUserId, $joiningUserId)) {
            throw new Exception(__('t_acs.error_not_buddy_or_ally'));
        }

        // Validate fleet targets the same location as the union
        if ($mission->galaxy_to !== $union->galaxy_to
            || $mission->system_to !== $union->system_to
            || $mission->position_to !== $union->position_to
            || $mission->type_to !== $union->planet_type_to) {
            throw new Exception(__('t_ingame.fleet.err_union_target_mismatch'));
        }

        // Validate fleet can arrive within delay limit
        $maxArrival = $union->time_arrival + $this->getMaxDelayTime($union);
        if ($mission->time_arrival > $maxArrival) {
            throw new Exception(__('t_acs.error_exceeds_delay_limit'));
        }

        // Get next available slot
        $nextSlot = $union->activeFleetMissions()->max('union_slot') + 1;

        // Link mission to union
        $mission->union_id = $union->id;
        $mission->union_slot = $nextSlot;
        $mission->mission_type = FleetMissionType::AcsAttack;

        // Adjust arrival time to match union (if fleet arrives earlier)
        if ($mission->time_arrival < $union->time_arrival) {
            $mission->time_arrival = $union->time_arrival;
        } else {
            // Fleet arrives later - update union arrival time (within delay limit)
            $union->time_arrival = $mission->time_arrival;
            $union->save();

            // Also sync all existing union members to the new (later) arrival time.
            // The mission itself has not been saved yet (union_id not in DB), so
            // activeFleetMissions() only returns already-joined missions.
            $union->activeFleetMissions()->update(['time_arrival' => $mission->time_arrival]);
        }

        $mission->save();
    }

    /**
     * Get the maximum delay time allowed for joining fleets.
     * This is 30% of the remaining flight time.
     *
     * @param FleetUnion $union
     * @return int Delay time in seconds
     */
    public function getMaxDelayTime(FleetUnion $union): int
    {
        $remainingTime = $union->getRemainingTime();
        return (int) floor($remainingTime * self::MAX_DELAY_PERCENTAGE);
    }

    /**
     * Handle a fleet being recalled from a union.
     *
     * @param FleetMission $mission The mission being recalled
     * @return void
     */
    public function handleFleetRecall(FleetMission $mission): void
    {
        if (!$mission->isInUnion()) {
            return;
        }

        /** @var FleetUnion $union */
        $union = $mission->union;

        // Remove from union and revert to regular attack
        $mission->union_id = null;
        $mission->union_slot = null;
        $mission->mission_type = FleetMissionType::Attack;
        $mission->save();

        // Check if union is now empty
        if ($union->activeFleetMissions()->count() === 0) {
            // Delete the empty union
            $union->delete();
            return;
        }

        // Compact remaining slots: renumber by current slot order starting from 1
        $remainingMissions = $union->activeFleetMissions()
            ->orderBy('union_slot')
            ->get();

        $slot = 1;
        foreach ($remainingMissions as $remainingMission) {
            if ($remainingMission->union_slot !== $slot) {
                $remainingMission->union_slot = $slot;
                $remainingMission->save();
            }
            $slot++;
        }

        // Update union ownership to the new slot 1 fleet's owner
        $newInitiator = $union->activeFleetMissions()->where('union_slot', 1)->first();
        if ($newInitiator !== null && $union->user_id !== $newInitiator->user_id) {
            $union->user_id = $newInitiator->user_id;
            $union->save();
        }
    }

    /**
     * Get all active fleet unions a player can join (invited or creator, not expired, has active missions).
     *
     * @return array<array{id: int, name: string, galaxy: int, system: int, position: int, planet_type: int, creator: string, time: int}>
     */
    public function getAvailableUnionsForPlayer(int $playerId): array
    {
        $unions = FleetUnion::with(['creator'])
            ->where('time_arrival', '>', now()->timestamp)
            ->whereHas('activeFleetMissions', function ($query) {
                $query->where('processed', 0)->where('canceled', 0);
            })
            ->where(function ($query) use ($playerId) {
                $query->where('user_id', $playerId)
                    ->orWhereHas('invites', function ($query) use ($playerId) {
                        $query->where('user_id', $playerId);
                    });
            })
            ->get();

        $result = [];
        foreach ($unions as $union) {
            $result[] = [
                'id' => $union->id,
                'name' => $union->name ?? ('KV' . $union->id),
                'galaxy' => $union->galaxy_to,
                'system' => $union->system_to,
                'position' => $union->position_to,
                'planet_type' => $union->planet_type_to,
                'creator' => $union->creator->username,
                'time' => $union->time_arrival,
            ];
        }

        return $result;
    }

    /**
     * Get active fleet unions targeting a specific coordinate that a player can join.
     *
     * @return array<array{id: int, name: string, creator: string, time_arrival: int, participant_count: int, max_fleets: int, can_join: bool}>
     */
    public function getAvailableUnionsForCoordinate(int $playerId, int $galaxy, int $system, int $position, int $planetType): array
    {
        $unions = FleetUnion::with(['creator', 'activeFleetMissions'])
            ->where('galaxy_to', $galaxy)
            ->where('system_to', $system)
            ->where('position_to', $position)
            ->where('planet_type_to', $planetType)
            ->where('time_arrival', '>', now()->timestamp)
            ->whereHas('activeFleetMissions', function ($query) {
                $query->where('processed', 0)->where('canceled', 0);
            })
            ->where(function ($query) use ($playerId) {
                $query->where('user_id', $playerId)
                    ->orWhereHas('invites', function ($query) use ($playerId) {
                        $query->where('user_id', $playerId);
                    });
            })
            ->get();

        $result = [];
        foreach ($unions as $union) {
            $result[] = [
                'id' => $union->id,
                'name' => $union->name,
                'creator' => $union->creator->username,
                'time_arrival' => $union->time_arrival,
                'participant_count' => $union->activeFleetMissions()->count(),
                'max_fleets' => $union->max_fleets,
                'can_join' => !$union->hasReachedMaxFleets(),
            ];
        }

        return $result;
    }

    /**
     * Send ACS union invite messages to the named users and record invite records.
     *
     * @param string $unionUsersString Semicolon-separated usernames
     * @param PlayerService $senderPlayer The player who created/edited the union
     * @param FleetMission $mission The mission associated with the union
     * @param int $unionId The fleet union ID
     * @param string $unionName The union display name (e.g. "KV123")
     */
    public function sendUnionInvites(string $unionUsersString, PlayerService $senderPlayer, FleetMission $mission, int $unionId, string $unionName): void
    {
        if (empty($unionUsersString)) {
            return;
        }

        $usernames = array_filter(explode(';', $unionUsersString));
        $senderName = $senderPlayer->getUsername(false);
        $targetCoords = $mission->galaxy_to . ':' . $mission->system_to . ':' . $mission->position_to;

        $targetPlayerName = '';
        $targetCoordinate = new Coordinate($mission->galaxy_to, $mission->system_to, $mission->position_to);
        $targetPlanetService = $this->planetServiceFactory->makeForCoordinate($targetCoordinate);
        if ($targetPlanetService !== null) {
            $targetPlayer = $targetPlanetService->getPlayer();
            if ($targetPlayer !== null) {
                $targetPlayerName = $targetPlayer->getUsername(false);
            }
        }

        $arrivalTime = date('d.m.Y H:i:s', $mission->time_arrival);

        foreach ($usernames as $username) {
            $username = trim($username);

            if ($username === $senderName) {
                continue;
            }

            /** @var User|null $invitedUser */
            $invitedUser = User::where('username', $username)->first();
            if ($invitedUser === null) {
                continue;
            }

            FleetUnionInvite::firstOrCreate([
                'fleet_union_id' => $unionId,
                'user_id' => $invitedUser->id,
            ]);

            $invitedPlayerService = app(PlayerService::class, ['player_id' => $invitedUser->id]);
            $this->messageService->sendSystemMessageToPlayer($invitedPlayerService, FleetUnionInviteMessage::class, [
                'sender_name' => $senderName,
                'union_name' => $unionName,
                'target_player' => $targetPlayerName,
                'target_coords' => $targetCoords,
                'arrival_time' => $arrivalTime,
            ]);
        }
    }

    /**
     * Check if a player can join a union (ally/buddy of creator).
     * Used for UI filtering to show available unions.
     *
     * @param FleetUnion $union
     * @param int $playerId
     * @return bool
     */
    public function canPlayerJoinUnion(FleetUnion $union, int $playerId): bool
    {
        return $this->isAllyOrBuddy($union->user_id, $playerId);
    }

    /**
     * Check if two players are allies or buddies.
     *
     * @param int $userId1
     * @param int $userId2
     * @return bool
     */
    private function isAllyOrBuddy(int $userId1, int $userId2): bool
    {
        // Same player is always allowed
        if ($userId1 === $userId2) {
            return true;
        }

        // Check if buddies
        if ($this->buddyService->areBuddies($userId1, $userId2)) {
            return true;
        }

        // Check if in same alliance
        if ($this->allianceService->arePlayersInSameAlliance($userId1, $userId2)) {
            return true;
        }

        return false;
    }
}

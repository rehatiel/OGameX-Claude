<?php

namespace OGame\Enums;

enum FleetMissionType: int
{
    case Attack = 1;
    case AcsAttack = 2;
    case Transport = 3;
    case Deployment = 4;
    case AcsDefend = 5;
    case Espionage = 6;
    case Colonise = 7;
    case Recycle = 8;
    case Destroy = 9;
    case MissileAttack = 10;
    case Expedition = 15;
}

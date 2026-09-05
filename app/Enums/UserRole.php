<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case ClubAdmin = 'club_admin';
    case Coach = 'coach';
    case Member = 'member';
    // Accounts die via een ouder/verzorger-koppeling zijn aangemaakt. Ze zijn
    // geen clublid; hun toegang loopt volledig via het gekoppelde kind.
    case Guardian = 'guardian';
    // Mag toegangscodes beheren en bij de ingang scannen. Los van coach of
    // commissie: de vrijwilliger aan de deur is vaak geen van beide.
    case Toegang = 'toegang';
    // Mag ruimtes van de club beheren en reserveren, in de portal en in de app.
    // Los van de andere rollen: de vrijwilliger die de kantine inplant is vaak
    // geen coach en geen bestuurslid.
    //
    // Met een streepje en niet met een liggend streepje zoals de rollen
    // hierboven: dit is de naam die is afgesproken, en drie plekken moeten hem
    // letterlijk gelijk hebben - deze enum, de migratie en de rollijst in
    // RoomController.
    case RoomPlanning = 'room-planning';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin => 'Superbeheerder',
            self::ClubAdmin => 'Clubbeheerder',
            self::Coach => 'Coach',
            self::Member => 'Lid',
            self::Guardian => 'Ouder/verzorger',
            self::Toegang => 'Toegangscontrole',
            self::RoomPlanning => 'Ruimteplanning',
        };
    }
}

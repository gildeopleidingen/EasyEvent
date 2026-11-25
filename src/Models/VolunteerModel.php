<?php

namespace App\Models;

use App\Conn;
use PDO;
use RuntimeException;

class VolunteerModel
{
    public function getOpenActivities(?int $gebruikerId = null): array
    {
        // Zonder expliciet id: gebruik de ingelogde gebruiker uit de sessie.
        if ($gebruikerId === null && isset($_SESSION['GebruikersID'])) {
            $gebruikerId = (int) $_SESSION['GebruikersID'];
        }

        $db = Conn::getPDO();

        if (!$db instanceof PDO) {
            throw new RuntimeException('Geen databaseverbinding.');
        }

        $sql = "
            SELECT
                e.id AS event_id,
                e.naam AS event_name,
                a.naam AS activity_name,
                et.datum AS event_date,
                aet.begin_tijd AS start_time,
                aet.eind_tijd AS end_time,

                CONCAT_WS(
                    ' ',
                    et.straatnaam,
                    et.huisnummer,
                    et.postcode,
                    et.plaatsnaam,
                    et.land
                ) AS location,

                COUNT(DISTINCT p.gebruiker_id)
                    AS registered_volunteers,

                COALESCE(MAX(p.gebruiker_id = :uid), 0)
                    AS is_registered

            FROM activiteit_event_tijd aet

            INNER JOIN activiteit a
                ON a.id = aet.activiteit_id

            INNER JOIN event_tijd et
                ON et.id = aet.event_tijd_id

            INNER JOIN `event` e
                ON e.id = et.event_id

            LEFT JOIN planning p
                ON p.activiteit_event_tijd_id = aet.id

            WHERE e.removed = 0

            GROUP BY
                aet.id,
                e.id,
                e.naam,
                a.naam,
                et.datum,
                aet.begin_tijd,
                aet.eind_tijd,
                et.straatnaam,
                et.huisnummer,
                et.postcode,
                et.plaatsnaam,
                et.land

            ORDER BY et.datum ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => (int) $gebruikerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

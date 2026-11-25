<?php

namespace App\Models;

use App\Conn;
use PDO;

class SingleEventModel extends DBModel
{
    public $message;
    public $gebruikerID;
    public $event;
    public $activities;
    public $organisations;
    public $active_roles;

    public function __construct()
    {
        // De databaseverbinding wordt per query opgehaald via Conn.
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function setGebruikerId(int $gebruikerID): void
    {
        $this->gebruikerID = $gebruikerID;
    }

    public function setEvent(EventModel $event): void
    {
        $this->event = $event;
    }

    public function setActivities(array $activities): void
    {
        $this->activities = $activities;
    }

    public function setOrganisations(array $organisations): void
    {
        $this->organisations = $organisations;
    }

    public function setRoles(array $active_roles): void
    {
        $this->active_roles = $active_roles;
    }

    public static function getOrganisations(): array
    {
        $db = Conn::getInstance()->getPDO();
        $stmt = $db->prepare('SELECT id, naam FROM vereniging ORDER BY naam');
        $stmt->execute();

        $organisations = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $organisations[] = new OrganisationModel(
                (int) $row['id'],
                $row['naam']
            );
        }

        return $organisations;
    }

    /** Haalt alle activiteiten van een evenement op voor bezoekers. */
    public static function getActivitiesByEventId(int $id): array
    {
        $db = Conn::getInstance()->getPDO();

        $sql = "SELECT
                    aet.id AS aet_id,
                    aet.event_tijd_id,
                    aet.begin_tijd,
                    aet.eind_tijd,
                    a.id AS activiteit_id,
                    a.naam,
                    et.plaatsnaam AS locatie
                FROM activiteit_event_tijd aet
                INNER JOIN event_tijd et
                    ON et.id = aet.event_tijd_id
                INNER JOIN activiteit a
                    ON a.id = aet.activiteit_id
                WHERE et.event_id = :eventid
                ORDER BY aet.begin_tijd ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute(['eventid' => $id]);

        $activities = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activity = new ActivityModel(
                (string) $row['naam'],
                (string) $row['begin_tijd'],
                (string) $row['eind_tijd']
            );

            $activity->setActiviteitEventTijdId((int) $row['aet_id']);
            $activity->setActiviteitId((int) $row['activiteit_id']);
            $activity->setEventTijdId((int) $row['event_tijd_id']);
            $activity->setLocatie((string) ($row['locatie'] ?? ''));
            $activity->setGebruikerID(null);
            $activity->setOrganisationID(null);
            $activity->setRolID(null);
            $activity->setPlannedID(null);

            $activities[] = $activity;
        }

        return $activities;
    }

    /** Haalt de activiteiten op waarop deze gebruiker al is ingepland. */
    public static function getPlanningByEventIdAndUserId(
        int $id,
        int $gebruiker_id
    ): array {
        $db = Conn::getInstance()->getPDO();

        $sql = "SELECT
                    aet.id AS aet_id,
                    aet.event_tijd_id,
                    aet.begin_tijd AS activiteit_begin_tijd,
                    aet.eind_tijd AS activiteit_eind_tijd,
                    a.id AS activiteit_id,
                    a.naam,
                    et.plaatsnaam AS locatie,
                    p.id AS planning_id,
                    p.gebruiker_id,
                    p.vereniging_id,
                    COALESCE(p.begin_tijd, aet.begin_tijd) AS planning_begin_tijd,
                    COALESCE(p.eind_tijd, aet.eind_tijd) AS planning_eind_tijd
                FROM planning p
                INNER JOIN activiteit_event_tijd aet
                    ON aet.id = p.activiteit_event_tijd_id
                INNER JOIN event_tijd et
                    ON et.id = aet.event_tijd_id
                INNER JOIN activiteit a
                    ON a.id = aet.activiteit_id
                WHERE et.event_id = :eventid
                  AND p.gebruiker_id = :userid
                ORDER BY aet.begin_tijd ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'eventid' => $id,
            'userid' => $gebruiker_id
        ]);

        $activities = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activity = new ActivityModel(
                (string) $row['naam'],
                (string) $row['planning_begin_tijd'],
                (string) $row['planning_eind_tijd']
            );

            $activity->setActiviteitEventTijdId((int) $row['aet_id']);
            $activity->setActiviteitId((int) $row['activiteit_id']);
            $activity->setEventTijdId((int) $row['event_tijd_id']);
            $activity->setLocatie((string) ($row['locatie'] ?? ''));
            $activity->setGebruikerID((int) $row['gebruiker_id']);
            $activity->setOrganisationID(
                $row['vereniging_id'] !== null
                    ? (int) $row['vereniging_id']
                    : null
            );
            $activity->setRolID(null);
            $activity->setPlannedID((int) $row['planning_id']);

            $activities[] = $activity;
        }

        return $activities;
    }

    /** Toont alle activiteiten en markeert welke al door de gebruiker zijn gekozen. */
    public static function getActivitiesByEventIdAndUserId(
        int $id,
        int $gebruiker_id
    ): array {
        $db = Conn::getInstance()->getPDO();

        $sql = "SELECT
                    aet.id AS aet_id,
                    aet.event_tijd_id,
                    aet.begin_tijd AS activiteit_begin_tijd,
                    aet.eind_tijd AS activiteit_eind_tijd,
                    a.id AS activiteit_id,
                    a.naam,
                    et.plaatsnaam AS locatie,
                    p.id AS planning_id,
                    p.gebruiker_id,
                    p.vereniging_id,
                    COALESCE(p.begin_tijd, aet.begin_tijd) AS planning_begin_tijd,
                    COALESCE(p.eind_tijd, aet.eind_tijd) AS planning_eind_tijd
                FROM activiteit_event_tijd aet
                INNER JOIN event_tijd et
                    ON et.id = aet.event_tijd_id
                INNER JOIN activiteit a
                    ON a.id = aet.activiteit_id
                LEFT JOIN planning p
                    ON p.activiteit_event_tijd_id = aet.id
                   AND p.gebruiker_id = :userid
                WHERE et.event_id = :eventid
                ORDER BY aet.begin_tijd ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'eventid' => $id,
            'userid' => $gebruiker_id
        ]);

        $activities = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activity = new ActivityModel(
                (string) $row['naam'],
                (string) $row['planning_begin_tijd'],
                (string) $row['planning_eind_tijd']
            );

            $activity->minTijd = (string) $row['activiteit_begin_tijd'];
            $activity->maxTijd = (string) $row['activiteit_eind_tijd'];

            $activity->setActiviteitEventTijdId((int) $row['aet_id']);
            $activity->setActiviteitId((int) $row['activiteit_id']);
            $activity->setEventTijdId((int) $row['event_tijd_id']);
            $activity->setLocatie((string) ($row['locatie'] ?? ''));

            $activity->setGebruikerID(
                $row['gebruiker_id'] !== null
                    ? (int) $row['gebruiker_id']
                    : null
            );

            $activity->setOrganisationID(
                $row['vereniging_id'] !== null
                    ? (int) $row['vereniging_id']
                    : null
            );

            $activity->setRolID(null);

            $activity->setPlannedID(
                $row['planning_id'] !== null
                    ? (int) $row['planning_id']
                    : null
            );

            $activities[] = $activity;
        }

        return $activities;
    }

    /** Haalt één evenement op met de bijbehorende eerste datum/tijd. */
    public static function getEventById(int $id): ?EventModel
    {
        $db = Conn::getInstance()->getPDO();

        $sql = "SELECT
                    e.id,
                    e.naam,
                    e.beschrijving,
                    e.hoofd_event,
                    e.organisator_id,
                    et.datum,
                    et.begin_tijd,
                    et.eind_tijd,
                    et.land,
                    et.plaatsnaam,
                    et.straatnaam,
                    et.huisnummer,
                    et.postcode
                FROM `event` e
                LEFT JOIN event_tijd et
                    ON et.event_id = e.id
                WHERE e.id = :eventid
                  AND e.removed = 0
                ORDER BY et.datum ASC, et.begin_tijd ASC
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute(['eventid' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $event = new EventModel();

        $event->setEventID((string) $row['id']);
        $event->setEventName((string) $row['naam']);
        $event->setEventInfo((string) ($row['beschrijving'] ?? ''));
        $event->setEventOrganizer((int) ($row['organisator_id'] ?? 0));
        $event->setEventSector(null);

        $event->setEventTimes(
            $row['datum'] !== null
                ? [[
                    'date' => $row['datum'],
                    'startTime' => $row['begin_tijd'],
                    'endTime' => $row['eind_tijd']
                ]]
                : []
        );

        $event->hoofdEventID = $row['hoofd_event'] !== null
            ? (int) $row['hoofd_event']
            : null;

        return $event;
    }
}

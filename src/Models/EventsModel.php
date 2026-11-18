<?php

namespace App\Models;

use App\Conn;
use PDO;

class EventsModel extends DBModel
{
    public $events;
    public $roles;

    public function __construct()
    {

    }

    public function setEvents(array $events){
        $this->events = $events;
    }

    public function getEvents(){
        return $this->events;
    }

    public static function getMainEvents() {
        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();

        $sql = "SELECT 
                    event.id, 
                    event.naam AS eventName, 
                    event.beschrijving AS eventInfo, 
                    GROUP_CONCAT(`event_tijd`.datum SEPARATOR ',') AS eventDate,
                    event.hoofd_event AS hoofdEventID
                FROM 
                    event 
                LEFT OUTER JOIN `event_tijd` ON event.id=`event_tijd`.event_id
                WHERE event.removed = 0
                GROUP BY event.id";

        $stmt = $db->prepare($sql);

        if (!$stmt->execute()) {
            die('Query failed: ' . implode(' ', $stmt->errorInfo()));
        }

        $events = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eventDates = [];
            if (isset($row['eventDate']))
            {
                $dates = explode(',', $row['eventDate']);
                foreach ($dates as $date) {
                    $eventDates[] = ['date' => $date, 'startTime' => null, 'endTime' => null];
                }
            }
            $event = new EventModel();
            $event->setEventID($row['id']);
            $event->setEventName($row['eventName']);
            $event->setEventInfo($row['eventInfo']);
            $events[] = $event;
        }

        return $events;
    }

    public static function generateEvents() {
        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();
    
        $sql = "SELECT e.id, 
                    e.naam           AS eventName, 
                    e.beschrijving   AS eventInfo, 
                    et.datum         AS eventDate,
                    et.begin_tijd    AS startTime,
                    et.eind_tijd     AS endTime,
                    e.hoofd_event    AS hoofdEventID,
                    e.organisator_id AS organisator,
                    et.land          AS Land,
                    et.plaatsnaam    AS Plaats,
                    et.straatnaam    AS Straatnaam,
                    et.huisnummer    AS Huisnummer,
                    et.postcode      AS Postcode,
                    sagg.sector_ids  AS SectorIDs
                FROM event e
                LEFT JOIN `event_tijd` et 
                    ON e.id = et.event_id
                LEFT JOIN (
                    SELECT  
                        event_id,
                        GROUP_CONCAT(DISTINCT sector_id ORDER BY sector_id SEPARATOR ', ') AS sector_ids
                    FROM sector_event
                    GROUP BY event_id
                ) sagg
                    ON sagg.event_id = e.id
                WHERE e.removed = 0;
                ";
        $stmt = $db->prepare($sql);

        if (!$stmt->execute()) {
            die('Query failed: ' . implode(' ', $stmt->errorInfo()));
        }
    
        $events = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $event = new EventModel();
            $event->setEventID((int)$row['id']);
            $event->setEventName($row['eventName']);
            $event->setEventInfo($row['eventInfo']);
            $event->setEventOrganizer((int)$row['organisator']);
            $event->setHoofdEventID((int)$row['hoofdEventID']);
            $event->setEventSector($row['SectorIDs']);
            $event->setEventTimes([['date' => $row['eventDate'], 'startTime' => $row['startTime'], 'endTime' => $row['endTime']]]);
            $events[] = $event;
        }
    
        return $events;
    }

    /**
     * Verwijdert een event definitief uit de database, inclusief alles wat eraan vastzit:
     * planning, inschrijvingen, activiteiten, dagen en sectorkoppelingen.
     * Alles gebeurt in één transactie: lukt één stap niet, dan blijft alles staan.
     */
    public static function delete($id){
        $db = Conn::getPDO();
        $params = [':id' => (int)$id];

        $db->beginTransaction();
        try {
            // Vrijwilligersplanning van de activiteiten van dit event
            $db->prepare("
                DELETE p FROM planning p
                JOIN activiteit_event_tijd aet ON aet.id = p.activiteit_event_tijd_id
                JOIN event_tijd et ON et.id = aet.event_tijd_id
                WHERE et.event_id = :id
            ")->execute($params);

            // Inschrijvingen op de dagen van dit event
            $db->prepare("
                DELETE i FROM inschrijving i
                JOIN event_tijd et ON et.id = i.event_tijd_id
                WHERE et.event_id = :id
            ")->execute($params);

            // Activiteiten van dit event onthouden voordat de koppelingen weg zijn
            $stmt = $db->prepare("
                SELECT DISTINCT aet.activiteit_id
                FROM activiteit_event_tijd aet
                JOIN event_tijd et ON et.id = aet.event_tijd_id
                WHERE et.event_id = :id
            ");
            $stmt->execute($params);
            $activiteitIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $db->prepare("
                DELETE aet FROM activiteit_event_tijd aet
                JOIN event_tijd et ON et.id = aet.event_tijd_id
                WHERE et.event_id = :id
            ")->execute($params);

            // Activiteiten zelf, alleen als geen ander event ze nog gebruikt
            $stmtActiviteit = $db->prepare("
                DELETE FROM activiteit
                WHERE id = :activiteit_id
                AND NOT EXISTS (SELECT 1 FROM activiteit_event_tijd WHERE activiteit_id = :activiteit_id2)
            ");
            foreach ($activiteitIds as $activiteitId) {
                $stmtActiviteit->execute([':activiteit_id' => $activiteitId, ':activiteit_id2' => $activiteitId]);
            }

            $db->prepare("DELETE FROM event_tijd WHERE event_id = :id")->execute($params);
            $db->prepare("DELETE FROM sector_event WHERE event_id = :id")->execute($params);
            // Sub-events niet meeverwijderen, alleen loskoppelen
            $db->prepare("UPDATE `event` SET hoofd_event = NULL WHERE hoofd_event = :id")->execute($params);

            $stmt = $db->prepare("DELETE FROM `event` WHERE id = :id");
            $stmt->execute($params);
            $verwijderd = $stmt->rowCount() > 0;

            $db->commit();
            return $verwijderd;
        } catch (\PDOException $e) {
            $db->rollBack();
            error_log('Event verwijderen mislukt: ' . $e->getMessage());
            return false;
        }
    }
}
<?php

namespace App\Models;

use App\Conn;
use PDO;

class EventModel
{
    public int $eventID;
    public string $eventName;
    public string $eventInfo;
    public int $eventOrganizer;
    public string $eventDescription = '';
    public string $eventLocation = '';
    public string $eventLand = '';
    public string $eventPlaats = '';
    public string $eventStraatnaam = '';
    public string $eventHuisnummer = '';
    public string $eventPostcode = '';
    public $eventSector = [];
    public $eventTimes = [];
    public $eventSectorInfo = [];
    public $images = [];
    public $hoofdEventID;
    public bool $isIngeschreven = false;
    public string $eventBanner = '';

    /**
     * Alle parameters zijn optioneel, zodat `new EventModel()` (lijsten)
     * en `new EventModel($organisator, $naam, ...)` (event aanmaken) beide werken.
     *
     * @param array|string|null $sector
     */
    public function __construct(
        ?int $eventOrganizer = null,
        ?string $eventName = null,
        ?string $eventInfo = null,
        ?string $land = null,
        ?string $plaats = null,
        ?string $straatnaam = null,
        ?string $huisnummer = null,
        ?string $postcode = null,
        $sector = [],
        array $eventTimes = []
    ) {
        if ($eventOrganizer !== null) {
            $this->eventOrganizer = $eventOrganizer;
        }
        if ($eventName !== null) {
            $this->eventName = $eventName;
        }
        if ($eventInfo !== null) {
            $this->eventInfo = $eventInfo;
        }

        $this->eventLand = (string) $land;
        $this->eventPlaats = (string) $plaats;
        $this->eventStraatnaam = (string) $straatnaam;
        $this->eventHuisnummer = (string) $huisnummer;
        $this->eventPostcode = (string) $postcode;

        $this->setEventSector($sector);
        $this->eventTimes = $eventTimes;
    }

    // Setters

    public function setEventName(string $eventName)
    {
        $this->eventName = $eventName;
    }

    public function setEventInfo(string $eventInfo)
    {
        $this->eventInfo = $eventInfo;
    }

    public function setEventDescription(string $eventDescription)
    {
        $this->eventDescription = $eventDescription;
    }

    public function setEventLocation(string $eventLocation)
    {
        $this->eventLocation = $eventLocation;
    }

    /**
     * Accepteert "1, 2, 3" (uit de database), een array met ids
     * (uit het formulier) of null/''.
     *
     * @param array|string|null $sector
     */
    public function setEventSector($sector)
    {
        if ($sector === null || $sector === '' || $sector === []) {
            $this->eventSector = [];
            return;
        }

        if (!is_array($sector)) {
            $sector = explode(',', (string) $sector);
        }

        $this->eventSector = array_values(
            array_filter(array_map('intval', $sector))
        );
    }

    public function setEventOrganizer(int $eventOrganizer)
    {
        $this->eventOrganizer = $eventOrganizer;
    }

    public function setEventTimes(array $eventTimes)
    {
        $this->eventTimes = $eventTimes;
    }

    public function setHoofdEventID(int $hoofdEventID)
    {
        $this->hoofdEventID = $hoofdEventID;
    }

    public function setEventID(string $eventID)
    {
        $this->eventID = (int) $eventID;
    }

    // Adders

    /** Voegt een tijdslot toe: ['date' => ..., 'BeginTime' => ..., 'EndTime' => ...] */
    public function addEventTime(array $timeSlot)
    {
        if (
            !empty($timeSlot['date'])
            && !empty($timeSlot['BeginTime'])
            && !empty($timeSlot['EndTime'])
        ) {
            $this->eventTimes[] = $timeSlot;
        }
    }

    // Getters

    public function getEventID()
    {
        return $this->eventID;
    }

    public function getEventName()
    {
        return $this->eventName;
    }

    public function getEventInfo()
    {
        return $this->eventInfo;
    }

    public function getEventSector()
    {
        return $this->eventSector;
    }

    public function getEventSectorAsString()
    {
        return implode(',', array_map('intval', $this->eventSector));
    }

    public function getEventOrganizer()
    {
        return $this->eventOrganizer;
    }

    public function getEventTimes()
    {
        return $this->eventTimes;
    }

    public function getEventTime()
    {
        return $this->eventTimes;
    }

    public function getEventBanner(): string
    {
        return $this->eventBanner;
    }

    public function setEventBanner(string $eventBanner): void
    {
        $this->eventBanner = $eventBanner;
    }

    public function getHoofdEventID()
    {
        return $this->hoofdEventID;
    }

    public function getIngeschreven()
    {
        return $this->isIngeschreven;
    }

    // Functions

    public static function hasRole(array $activities, RolModel $rol)
    {
        foreach ($activities as $activity) {
            if (
                $activity->hasRole()
                && $rol->getID() == $activity->getRolID()
            ) {
                return true;
            }
        }

        return false;
    }

    public static function hasOrganisation(
        array $activities,
        OrganisationModel $organisation
    ) {
        foreach ($activities as $activity) {
            if (
                $activity->hasOrganisation()
                && $organisation->getID() == $activity->getOrganisationID()
            ) {
                return true;
            }
        }

        return false;
    }

    /** Controleert het model zelf en geeft een lijst met foutmeldingen terug. */
    public function validateModel(): array
    {
        $errors = [];

        if (trim($this->eventName ?? '') === '') {
            $errors[] = 'Vul een titel in.';
        }

        if (trim($this->eventInfo ?? '') === '') {
            $errors[] = 'Vul een beschrijving in.';
        }

        if (empty($this->eventTimes)) {
            $errors[] = 'Voeg minstens één datum met begin- en eindtijd toe.';
        }

        foreach ($this->eventTimes as $slot) {
            if (
                empty($slot['date'])
                || empty($slot['BeginTime'])
                || empty($slot['EndTime'])
            ) {
                $errors[] = 'Vul bij elke dag een datum, begintijd en eindtijd in.';
                break;
            }
        }

        $postcode = trim($this->eventPostcode);

        if ($postcode !== '') {
            switch ($this->eventLand) {
                case 'Netherland':
                case 'Nederland':
                    if (!preg_match('/^\d{4}\s?[A-Z]{2}$/', $postcode)) {
                        $errors[] = 'De postcode moet bestaan uit 4 cijfers en 2 hoofdletters.';
                    }
                    break;

                case 'België':
                case 'Luxemburg':
                    if (!preg_match('/^\d{4}$/', $postcode)) {
                        $errors[] = 'De postcode moet bestaan uit 4 cijfers.';
                    }
                    break;

                case 'Duitsland':
                    if (!preg_match('/^\d{5}$/', $postcode)) {
                        $errors[] = 'De postcode moet bestaan uit 5 cijfers.';
                    }
                    break;
            }
        }

        return $errors;
    }

    /** Slaat het event, de tijdsloten en de sectoren op. Geeft true bij succes. */
    public static function sendEvent(EventModel $event): bool
    {
        $db = Conn::getInstance()->getPDO();

        try {
            $db->beginTransaction();

            $stmtEvent = $db->prepare(
                "INSERT INTO event (naam, beschrijving, organisator_id)
                 VALUES (:eventName, :eventInfo, :eventOrganisator)"
            );

            if (!$stmtEvent->execute([
                ':eventName' => $event->eventName,
                ':eventInfo' => $event->eventInfo,
                ':eventOrganisator' => $event->eventOrganizer,
            ])) {
                throw new \RuntimeException(
                    'Insertion into `event` failed: '
                    . implode(', ', $stmtEvent->errorInfo())
                );
            }

            $event->eventID = (int) $db->lastInsertId();

            $stmtEventTime = $db->prepare(
                "INSERT INTO `event_tijd`
                    (event_id, land, plaatsnaam, straatnaam, huisnummer,
                     postcode, datum, begin_tijd, eind_tijd)
                 VALUES
                    (:eventID, :Land, :Plaats, :Straatnaam, :Huisnummer,
                     :Postcode, :date, :BeginTijd, :EindTijd)"
            );

            foreach ($event->getEventTimes() as $slot) {
                if (!$stmtEventTime->execute([
                    ':eventID' => $event->eventID,
                    ':Land' => $event->eventLand,
                    ':Plaats' => $event->eventPlaats,
                    ':Straatnaam' => $event->eventStraatnaam,
                    ':Huisnummer' => $event->eventHuisnummer,
                    ':Postcode' => $event->eventPostcode,
                    ':date' => $slot['date'],
                    ':BeginTijd' => $slot['BeginTime'],
                    ':EindTijd' => $slot['EndTime'],
                ])) {
                    throw new \RuntimeException(
                        'Insertion into `event_tijd` failed: '
                        . implode(', ', $stmtEventTime->errorInfo())
                    );
                }
            }

            $stmtSector = $db->prepare(
                "INSERT INTO sector_event (event_id, sector_id)
                 VALUES (:EventID, :SectorID)"
            );

            foreach ($event->eventSector as $sectorId) {
                if (!$stmtSector->execute([
                    ':EventID' => $event->eventID,
                    ':SectorID' => $sectorId,
                ])) {
                    throw new \RuntimeException(
                        'Insertion into `sector_event` failed: '
                        . implode(', ', $stmtSector->errorInfo())
                    );
                }
            }

            $db->commit();

            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Event opslaan mislukt: ' . $e->getMessage());

            return false;
        }
    }
}

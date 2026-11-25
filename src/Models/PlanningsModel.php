<?php

namespace App\Models;

use App\Conn;
use PDO;

class PlanningsModel extends DBModel
{
    public int $gebruiker_id;
    public array $planningModels = [];
    public array $defaultActivities = [];
    public array $oldActivities = [];
    public array $activities = [];
    public ?int $verenigingId = null;
    public float $gewerkteUren;

    public bool $betaalt = false;
    public bool $isGoedGekeurd = false;

    public function __construct(
        int $gebruiker_id,
        array $plannedModels,
        ?int $verenigingId,
        array $defaultActivities,
        array $oldActivities
    ) {
        $this->gebruiker_id = $gebruiker_id;
        $this->defaultActivities = $defaultActivities;
        $this->oldActivities = $oldActivities;

        foreach ($plannedModels as $plannedModel) {
            if (isset($plannedModel['checked'])) {
                $planned = new PlanningModel();

                $planned->setActiviteitEventTijdId(
                    $plannedModel['checked']
                );

                $planned->setBeginTijd(self::normaliseerTijd($plannedModel['startTime']));
                $planned->setEindTijd(self::normaliseerTijd($plannedModel['endTime']));

                $this->planningModels[] = $planned;
            }
        }

        $this->verenigingId = $verenigingId;
    }

    /** Maakt van "10:30" een "10:30:00", zodat het vergelijkt met de database. */
    private static function normaliseerTijd(string $tijd): string
    {
        $tijd = trim($tijd);

        return strlen($tijd) === 5 ? $tijd . ':00' : $tijd;
    }

    public function setActivities(array $activities)
    {
        $this->activities = $activities;
    }

    public static function removeOldPlannedModels(array $oldActivities)
    {
        $idsToDelete = [];

        foreach ($oldActivities as $activity) {
            $idsToDelete[] = $activity->getPlannedID();
        }

        if (empty($idsToDelete)) {
            return true;
        }

        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();

        $placeholders = implode(
            ',',
            array_fill(0, count($idsToDelete), '?')
        );

        $sql = "DELETE FROM planning WHERE id IN ($placeholders)";

        $stmt = $db->prepare($sql);

        if ($stmt->execute($idsToDelete)) {
            return "Successfully removed planning!";
        }

        return "removed from `planning` table failed!";
    }

    public function validate()
    {
        foreach ($this->defaultActivities as $default) {
            foreach ($this->planningModels as $plannedModel) {
                if ($plannedModel->getID() == $default->getID()) {

                    if ($plannedModel->getBeginTijd() >= $plannedModel->getEindTijd()) {
                        return false;
                    }

                    if (
                        $plannedModel->getBeginTijd() < $default->getBeginTijd()
                        || $plannedModel->getBeginTijd() > $default->getEindTijd()
                    ) {
                        return false;
                    }

                    if (
                        $plannedModel->getEindTijd() > $default->getEindTijd()
                        || $plannedModel->getEindTijd() < $default->getBeginTijd()
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public static function sendPlanning(PlanningsModel $planning)
    {
        if (empty($planning->planningModels)) {
            return true;
        }

        $mysql = Conn::getInstance();
        $pdo = $mysql->getPDO();

        // De planning-tabel heeft geen kolom rol_id.
        $sql = "INSERT INTO planning
            (
                gebruiker_id,
                activiteit_event_tijd_id,
                begin_tijd,
                eind_tijd,
                vereniging_id
            )
            VALUES ";

        $placeholders = [];
        $params = [];

        foreach ($planning->planningModels as $index => $planningModel) {
            $placeholders[] = "(
                :gebruiker_id{$index},
                :activiteit_event_tijd_id{$index},
                :beginTijd{$index},
                :eindTijd{$index},
                :vereniging_ID{$index}
            )";

            $params[":gebruiker_id{$index}"] = $planning->gebruiker_id;

            $params[":activiteit_event_tijd_id{$index}"] =
                $planningModel->activiteit_event_tijd_id;

            $params[":beginTijd{$index}"] = $planningModel->beginTijd;
            $params[":eindTijd{$index}"] = $planningModel->eindTijd;

            $params[":vereniging_ID{$index}"] = $planning->verenigingId;
        }

        $sql .= implode(", ", $placeholders);

        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($params)) {
            return "Successfully added planning!";
        }

        return "Insertion into `planning` table failed!";
    }

    public static function getPlanning($activiteitID)
    {
        $mysql = Conn::getInstance();
        $pdo = $mysql->getPDO();

        $sql = "SELECT
                    et.datum AS eventDatum,
                    e.naam AS eventNaam,
                    et.begin_tijd AS eventBeginTijd,
                    et.eind_tijd AS eventEindTijd,
                    a.naam AS activiteitNaam,
                    aet.begin_tijd AS activiteitBeginTijd,
                    aet.eind_tijd AS activiteitEindTijd,
                    p.begin_tijd AS planningBeginTijd,
                    p.eind_tijd AS planningEindTijd,
                    g.voornaam,
                    g.achternaam,
                    g.telefoon,
                    v.naam AS verenigingNaam,
                    p.is_betaald,
                    p.goedgekeurd
                FROM planning p
                JOIN gebruiker g
                    ON g.id = p.gebruiker_id
                LEFT JOIN vereniging v
                    ON v.id = p.vereniging_id
                JOIN activiteit_event_tijd aet
                    ON aet.id = p.activiteit_event_tijd_id
                JOIN event_tijd et
                    ON et.id = aet.event_tijd_id
                JOIN activiteit a
                    ON a.id = aet.activiteit_id
                JOIN `event` e
                    ON e.id = et.event_id
                WHERE a.id = :activiteitid
                ORDER BY
                    et.datum,
                    et.begin_tijd,
                    aet.begin_tijd,
                    p.begin_tijd";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['activiteitid' => $activiteitID]);

        $planned = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $event = new EventModel();
            $event->setEventName($row['eventNaam']);

            $event->setEventTimes([
                [
                    'date' => $row['eventDatum'],
                    'BeginTijd' => $row['eventBeginTijd'],
                    'EindTijd' => $row['eventEindTijd']
                ]
            ]);

            $activity = new ActivityModel(
                $row['activiteitNaam'],
                $row['activiteitBeginTijd'],
                $row['activiteitEindTijd']
            );

            $activity->setNaam($row['activiteitNaam']);
            $activity->setBeginTijd($row['activiteitBeginTijd']);
            $activity->setEindTijd($row['activiteitEindTijd']);

            $user = new UserModel([]);
            $user->setVoornaam($row['voornaam']);
            $user->setAchternaam($row['achternaam']);
            $user->setTelefoon($row['telefoon']);
            $user->setOrganisations([$row['verenigingNaam']]);

            $plan = new PlanningModel();
            $plan->setActivity($activity);
            $plan->setUser($user);
            $plan->setEvent($event);
            $plan->setBeginTijd($row['planningBeginTijd']);
            $plan->setEindTijd($row['planningEindTijd']);
            $plan->setBetaalt($row['is_betaald']);
            $plan->setIsGoedGekeurd($row['goedgekeurd']);

            $planned[] = $plan;
        }

        return $planned;
    }
}

<?php

namespace App\Controllers;

use App\Conn;
use PDO;
use App\Controller;
use App\Models\EventsModel;
use App\Models\EventModel;
use App\Models\UsersModel;
use App\Models\EventEditModel;
use App\Models\SectorModel;
use App\Models\ActivityModel;
use App\Models\EventTijdModel;
use App\Models\RolModel;

class BeheerEventAanmakenController extends Controller {
    public function index() {
        $this->render("beheer/event-aanmaken");
    }

    public function step2() {
        $eventId = (int)($_GET['eventId'] ?? 0);
        $this->render("beheer/event-aanmaken-stap-2", [
            'eventId' => $eventId,
            'dagen' => EventTijdModel::getDagenByEventId($eventId)
        ]);
    }

    public function sendEvent(){
        $eventOrganizer = $_SESSION['GebruikersID'] ?? null;
        // Check if eventOrganizer is set and is an integer
        if (!isset($eventOrganizer) || !ctype_digit((string)$eventOrganizer)) {
            $this->render('beheer/home', ['error' => 'Organisator is niet geldig.']);
            return;
        }
        $eventOrganizer = (int)$eventOrganizer;
        $eventName = $_POST['eventNaam'] ?? null;
        $eventInfo = $_POST['info'] ?? null;
        $Land = $_POST['Land'] ?? null;
        $Plaats = $_POST['Plaats'] ?? null;
        $Straatnaam = $_POST['Straatnaam'] ?? null;
        $Huisnummer = $_POST['Huisnummer'] ?? null;
        $Postcode = $_POST['Postcode'] ?? null;
        $Sector = $_POST['sector'] ?? [];
        $hoofdEvent = $_POST['hoofdEvent'] ?? null;
        $eventID = $_POST['eventID'] ?? null;

        $dates = $_POST['datum'] ?? [];
        $startTimes = $_POST['begin-tijd'] ?? [];
        $endTimes = $_POST['eind-tijd'] ?? [];


        // $hasAtLeastOneTime = !empty($dates) && !empty($startTimes) && !empty($endTimes);

        // Controleer of alle velden ingevuld zijn
        if (empty($eventName) || empty($eventInfo) || empty($dates) || empty($startTimes)|| empty($endTimes)) {
            var_dump($eventName);
            var_dump($eventInfo);
            var_dump($startTimes);
            var_dump($startTimes);
            var_dump($_POST);
            var_dump($endTimes);
            $this->render('beheer/event-aanmaken', ['error' => 'Alle velden zijn verplicht.']);
            return;
        }

        for ($i = 0; $i < count($dates); $i++) { 
            $eventTimes[] = [
                'date' => $dates[$i], 
                'BeginTime' => $startTimes[$i], 
                'EndTime' => $endTimes[$i]
            ];
        }
        $eventModel = new EventModel( $eventOrganizer, $eventName, $eventInfo, $Land, $Plaats, $Straatnaam,$Huisnummer, $Postcode, $Sector, $eventTimes);

        $errors = $eventModel->validateModel();
        // Sla de gegevens tijdelijk op in de sessie
        $_SESSION['register_data'] = [
            'GebruikersID' => $_SESSION['GebruikersID'],
            'eventNaam' => $eventName,
            'info' => $eventInfo,
            'land' => $Land,
            'plaats' => $Plaats,
            'straatNaam' => $Straatnaam,
            'huisNummer' => $Huisnummer,
            'postcode' => $Postcode,
            'sector' => $Sector,
            'organisator' => $eventOrganizer,
            'hoofdEvent' => $hoofdEvent,
            'eventID' => $eventID,
            'eventTimes' => $eventTimes
        ];

        $result = $eventModel->sendEvent($eventModel);


        if ($result) {
            // Debugging statement to confirm redirection
            error_log("Redirecting to event aanmaken stap 2");
            $this->redirect('/beheer/event-aanmaken-stap-2?eventId='. $eventModel->getEventID() .'');
            return; // Ensure to return after rendering
        } else {
            $this->render('beheer/event-aanmaken', ['error' => 'Het event kon niet worden opgeslagen. Controleer de ingevulde gegevens.']);
        }

    }
    
    public function sendEventStep2()
    {
        $eventOrganizer = $_SESSION['GebruikersID'] ?? null;

        $isbevoegd = RolModel::isBevoegd($eventOrganizer);

       
        if (!isset($eventOrganizer) || !ctype_digit((string)$eventOrganizer) || !$isbevoegd) {
            $this->render('beheer/home', ['error' => 'Organisator is niet geldig.']);
            return;
        }

        $eventId = (int)($_GET['eventId'] ?? 0);
        // Alleen dagen die echt bij dit event horen zijn geldig.
        $dagen = EventTijdModel::getDagenByEventId($eventId);
        if (empty($dagen)) {
            $this->redirect('/beheer/event');
            return;
        }

        // Verwachte invoer: activiteiten[<event_tijd_id>][<n>][naam|begin_tijd|eind_tijd|aantal_personen]
        $invoer = isset($_POST['activiteiten']) && is_array($_POST['activiteiten']) ? $_POST['activiteiten'] : [];
        $fout = function (string $melding) use ($eventId, $dagen, $invoer) {
            // Opnieuw tonen met de ingevulde gegevens, zodat niets verloren gaat.
            $this->render('beheer/event-aanmaken-stap-2', [
                'eventId' => $eventId,
                'dagen' => $dagen,
                'oudeInvoer' => $invoer,
                'error' => $melding
            ]);
        };

        $activiteiten = [];
        $dagNummer = 0;
        foreach ($dagen as $eventTijdId => $dag) {
            $dagNummer++;
            foreach (($invoer[$eventTijdId] ?? []) as $activiteit) {
                if (!is_array($activiteit)) {
                    continue;
                }
                $naam = trim((string)($activiteit['naam'] ?? ''));
                $begin = (string)($activiteit['begin_tijd'] ?? '');
                $eind = (string)($activiteit['eind_tijd'] ?? '');
                $personen = filter_var($activiteit['aantal_personen'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

                if ($naam === '' || mb_strlen($naam) > 255) {
                    $fout("Dag $dagNummer: vul bij iedere activiteit een naam in (maximaal 255 tekens).");
                    return;
                }
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $begin) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $eind)) {
                    $fout("Dag $dagNummer: vul bij \"$naam\" een geldige begin- en eindtijd in.");
                    return;
                }
                if ($begin >= $eind) {
                    $fout("Dag $dagNummer: bij \"$naam\" moet de begintijd vóór de eindtijd liggen.");
                    return;
                }
                if ($personen === false) {
                    $fout("Dag $dagNummer: vul bij \"$naam\" een geldig aantal personen in.");
                    return;
                }

                $activiteiten[] = [
                    'event_tijd_id' => $eventTijdId,
                    'naam' => $naam,
                    'begin_tijd' => $begin,
                    'eind_tijd' => $eind,
                    'aantal_personen' => $personen
                ];
            }
        }

        if (empty($activiteiten)) {
            $fout('Voeg minstens één activiteit toe.');
            return;
        }

        if (!ActivityModel::sendActivitiesPerDag($activiteiten)) {
            $fout('De activiteiten konden niet worden opgeslagen. Probeer het opnieuw.');
            return;
        }

        // Na het aanmaken direct terug naar het evenementenoverzicht.
        $this->redirect('/events');
    }
    
    public function editEvent() {
        $eventID = $_GET['eventID'] ?? null;
        
        $EEModel = new EventEditModel;

        $EEModel->event = $EEModel->getEventByID($eventID);

        $this->render("beheer/event-aanmaken", (array)$EEModel);
    }

    public function updateEvent() {
        $eventID = $_GET['eventID'] ?? $_POST['eventID'] ?? null;
        if (!is_numeric($eventID)) {
            $this->redirect('/beheer/event');
            return;
        }

        $event = EventEditModel::getEventByID($eventID);
        if (!$event) {
            $this->redirect('/beheer/event');
            return;
        }

        $eventName = trim($_POST['eventNaam'] ?? '');
        $eventInfo = trim($_POST['info'] ?? '');
        if ($eventName === '' || $eventInfo === '') {
            $this->render('beheer/event-aanmaken', [
                'event' => $event,
                'error' => 'Titel en beschrijving zijn verplicht.'
            ]);
            return;
        }

        if (!EventEditModel::updateEvent($eventID, $eventName, $eventInfo)) {
            $this->render('beheer/event-aanmaken', [
                'event' => $event,
                'error' => 'Het evenement kon niet worden bijgewerkt.'
            ]);
            return;
        }

        $this->redirect('/beheer/event-aanmaken-stap-2?eventId=' . (int)$eventID);
    }
}

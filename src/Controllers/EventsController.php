<?php

namespace App\Controllers;

use App\Controller;
use App\Conn;
use App\Models\EventsModel;
use App\Models\RolModel;

class EventsController extends Controller
{
    public function adminIndex() {
        $eventModel = new EventsModel();
        $events = $eventModel->getMainEvents();
        $eventModel->setEvents($events);
        $this->render('beheer/event-overzicht', (array)$eventModel);
    }

    public function index()
    {
    
        $user = isset($_SESSION['gebruiker']) ? unserialize($_SESSION['gebruiker']) : null;

        $eventModel = new EventsModel();
        $eventModel->roles = $user ? $user->getRoles() : [];
        
        $events = $eventModel->generateEvents();
        $eventModel->setEvents($events);
        $this->render('events', (array)$eventModel);
    }

    public function delete() {
        if (!isset($_GET['eventID'])) {
            $this->redirect('/beheer/event');
            exit();
        }
        if (!isset($_SESSION['gebruiker']))
        {
            $this->redirect('/login');
            exit();
        }
        // Alleen een Admin of Organisator mag events verwijderen.
        if (!RolModel::isBevoegd($_SESSION['GebruikersID'] ?? 0)) {
            $this->redirect('/beheer/event');
            exit();
        }
        $id = intval($_GET['eventID']);
        EventsModel::delete($id);
        $this->redirect('/beheer/event');
    }
}

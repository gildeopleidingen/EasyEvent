<?php

namespace App\Controllers;

use App\Controller;
use App\Models\SingleEventModel;
use App\Models\PlanningsModel;

class EventInfoController extends Controller
{
    public function index()
    {
        if (!isset($_GET['eventID'])) {
            $this->redirect('/events');
            exit();
        }

        $id = (int) $_GET['eventID'];

        $eventModel = new SingleEventModel();
        $event = $eventModel->getEventById($id);

        if (!$event) {
            $this->redirect('/events');
            exit();
        }

        if (isset($_SESSION['gebruiker'])) {
            $user = unserialize($_SESSION['gebruiker']);

            $eventModel->setGebruikerId($user->getId());

            $activities = $eventModel->getActivitiesByEventIdAndUserId(
                $id,
                $user->getId()
            );
        } else {
            $activities = $eventModel->getActivitiesByEventId($id);
        }

        $eventModel->setEvent($event);
        $eventModel->setActivities($activities);

        $this->render('event-info', (array) $eventModel);
    }

    public function update()
    {
        if (
            !isset($_POST['eventID'])
            || !isset($_SESSION['gebruiker'])
        ) {
            $this->redirect('/events');
            exit();
        }

        $user = unserialize($_SESSION['gebruiker']);
        $id = (int) $_POST['eventID'];
        $postedActivities = $_POST['activities'] ?? [];

        $eventModel = new SingleEventModel();

        $activeActivities = $eventModel->getPlanningByEventIdAndUserId(
            $id,
            $user->getId()
        );

        $activities = $eventModel->getActivitiesByEventId($id);

        // Altijd als vrijwilliger: geen rol of vereniging meer nodig.
        $planning = new PlanningsModel(
            $user->getId(),
            $postedActivities,
            null,
            $activities,
            $activeActivities
        );

        if ($planning->validate()) {
            PlanningsModel::removeOldPlannedModels($activeActivities);
            PlanningsModel::sendPlanning($planning);

            $this->redirect(
                empty($planning->planningModels)
                    ? '/vrijwilligers'
                    : '/vrijwilligers?ingeschreven=1'
            );
            exit();
        }

        $this->renderWithError($id, 'Controleer je tijden: ze moeten binnen de activiteit vallen en de eindtijd moet na de begintijd liggen.');
    }

    private function renderWithError(int $id, string $error): void
    {
        $eventModel = new SingleEventModel();
        $event = $eventModel->getEventById($id);

        if (!$event) {
            $this->redirect('/events');
            exit();
        }

        $user = unserialize($_SESSION['gebruiker']);

        $eventModel->setGebruikerId($user->getId());
        $eventModel->setEvent($event);
        $eventModel->setActivities(
            $eventModel->getActivitiesByEventIdAndUserId($id, $user->getId())
        );

        $data = (array) $eventModel;
        $data['error'] = $error;

        $this->render('event-info', $data);
    }
}

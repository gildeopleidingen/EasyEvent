<?php

namespace App\Controllers;

use App\Controller;
use App\Models\VolunteerModel;

class VolunteerController extends Controller
{
    public function index(): void
    {
        try {
            $volunteerModel = new VolunteerModel();
            $user = isset($_SESSION['gebruiker'])
                ? unserialize($_SESSION['gebruiker'])
                : null;

            $activities = $volunteerModel->getOpenActivities(
                $user ? (int) $user->getId() : null
            );

            $this->render('vrijwilligers', [
                'activities' => $activities,
                'databaseUnavailable' => false
            ]);

        } catch (\Throwable $error) {

            error_log('Vrijwilligerspagina: ' . $error->getMessage());

            $this->render('vrijwilligers', [
                'activities' => [],
                'databaseUnavailable' => true
            ]);
        }
    }
}

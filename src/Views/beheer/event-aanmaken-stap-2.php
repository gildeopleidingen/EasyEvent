<?php
use App\Models\EventsModel;

$dagen = $dagen ?? [];
$oudeInvoer = $oudeInvoer ?? [];

// Datum als "15 november 2026" (valt terug op 15-11-2026 zonder intl-extensie).
$formatDatum = function (string $datum): string {
    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter('nl_NL', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'd MMMM yyyy');
        $tekst = $formatter->format(new DateTime($datum));
        if ($tekst !== false) {
            return $tekst;
        }
    }
    return date('d-m-Y', strtotime($datum));
};

// Eén rij met activiteitvelden. $waarden bevat eerder ingevulde waarden (na een fout).
$activiteitRij = function ($eventTijdId, $index, array $waarden = []) {
    $naam = "activiteiten[$eventTijdId][$index]";
    $id = "act-$eventTijdId-$index";
    $w = fn($sleutel) => htmlspecialchars((string)($waarden[$sleutel] ?? ''), ENT_QUOTES);
    return <<<HTML
    <div class="activity-item js-activiteit-rij">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="$id-naam" class="form-label">Naam <span class="verplicht">*</span></label>
                <input type="text" class="form-control" id="$id-naam" name="{$naam}[naam]" placeholder="Activiteit naam" maxlength="255" value="{$w('naam')}" required>
                <div class="invalid-feedback">Voer een activiteit naam in.</div>
            </div>
            <div class="col-6 col-md-2">
                <label for="$id-begin" class="form-label">Begintijd <span class="verplicht">*</span></label>
                <input type="time" class="form-control js-begin" id="$id-begin" name="{$naam}[begin_tijd]" value="{$w('begin_tijd')}" required>
                <div class="invalid-feedback">Voer een begintijd in.</div>
            </div>
            <div class="col-6 col-md-2">
                <label for="$id-eind" class="form-label">Eindtijd <span class="verplicht">*</span></label>
                <input type="time" class="form-control js-eind" id="$id-eind" name="{$naam}[eind_tijd]" value="{$w('eind_tijd')}" required>
                <div class="invalid-feedback">Eindtijd moet na begintijd zijn.</div>
            </div>
            <div class="col-9 col-md-3">
                <label for="$id-personen" class="form-label">Aantal personen <span class="verplicht">*</span></label>
                <input type="number" min="0" class="form-control" id="$id-personen" name="{$naam}[aantal_personen]" placeholder="Aantal personen" value="{$w('aantal_personen')}" required>
                <div class="invalid-feedback">Voer het aantal personen in.</div>
            </div>
            <div class="col-3 col-md-1 d-flex justify-content-end">
                <button type="button" class="btn btn-danger js-verwijder-activiteit" aria-label="Activiteit verwijderen">
                    <i class="bi bi-trash text-white"></i>
                </button>
            </div>
        </div>
    </div>
    HTML;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- favicons -->
    <link rel="shortcut icon" href="/images/icons/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/images/icons/favicon-16x16.png" type="image/x-icon" sizes="16x16">
    <link rel="shortcut icon" href="/images/icons/favicon-32x32.png" type="image/x-icon" sizes="32x32">

    <title>Beheer | Event Aanmaken</title>

    <!-- css -->
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/event-info.css">
    <link rel="stylesheet" href="/css/nav.css">
    <link rel="stylesheet" href="/css/event-aanmaken.css?v=<?= filemtime(__DIR__ . '/../../../css/event-aanmaken.css') ?>">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="container-fluid vh-100 d-flex flex-column">
        <?php require_once('./parts/nav.php'); ?>
        <div class="container my-4 pb-4">
            <h1 class="text-center mb-4">Event Aanmaken</h1>

            <div class="progress mb-4 fixed-top rounded-0">
                <div class="progress-bar rounded-0" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                    Stap 2 van de 2
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-3" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($dagen)): ?>
                <div class="alert alert-warning rounded-3" role="alert">
                    Dit event heeft nog geen dagen. Voeg eerst een datum toe bij stap 1.
                </div>
                <a href="/beheer/event" class="btn btn-secondary">Terug</a>
            <?php else: ?>

            <!-- Form 2: Activities (één knop per dag) -->
            <form id="formActivities" class="needs-validation" novalidate method="POST"
                  action="/beheer/event-aanmaken-stap-2?eventId=<?= (int)$eventId ?>">

                <ul class="nav nav-buttons dag-knoppen mb-4" role="tablist">
                    <?php $dagNummer = 0; foreach ($dagen as $eventTijdId => $dag): $dagNummer++; ?>
                        <li class="nav-item" role="presentation">
                            <button class="btn btn-primary <?= $dagNummer === 1 ? 'active' : '' ?>" id="dag-<?= $eventTijdId ?>-tab"
                                    type="button" role="tab"
                                    data-bs-toggle="tab" data-bs-target="#dag-<?= $eventTijdId ?>"
                                    aria-controls="dag-<?= $eventTijdId ?>" aria-selected="<?= $dagNummer === 1 ? 'true' : 'false' ?>">
                                Dag <?= $dagNummer ?><span class="d-none d-sm-inline"> – <?= htmlspecialchars($formatDatum($dag['datum'])) ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content">
                    <?php $dagNummer = 0; foreach ($dagen as $eventTijdId => $dag): $dagNummer++; ?>
                        <?php
                        // Na een fout de eerder ingevulde activiteiten terugzetten, anders één lege rij.
                        $rijen = isset($oudeInvoer[$eventTijdId]) && is_array($oudeInvoer[$eventTijdId])
                            ? array_values(array_filter($oudeInvoer[$eventTijdId], 'is_array'))
                            : [[]];
                        ?>
                        <div class="tab-pane fade <?= $dagNummer === 1 ? 'show active' : '' ?> js-dag" id="dag-<?= $eventTijdId ?>"
                             role="tabpanel" aria-labelledby="dag-<?= $eventTijdId ?>-tab" tabindex="0"
                             data-event-tijd-id="<?= (int)$eventTijdId ?>" data-volgende-index="<?= count($rijen) ?>">
                            <div class="js-activiteiten-lijst">
                                <?php foreach ($rijen as $index => $waarden): ?>
                                    <?= $activiteitRij((int)$eventTijdId, $index, $waarden) ?>
                                <?php endforeach; ?>
                            </div>

                            <p class="text-muted small js-geen-activiteiten" hidden>Nog geen activiteiten voor deze dag.</p>

                            <button type="button" class="btn btn-primary btn-plus mb-3 js-voeg-activiteit-toe"
                                    aria-label="Activiteit toevoegen aan dag <?= $dagNummer ?>" title="Activiteit toevoegen">
                                <i class="bi bi-plus-lg text-white"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <a href="/beheer/event" class="btn btn-secondary">Terug</a>
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                </div>
            </form>

            <!-- Sjabloon voor een nieuwe activiteit (__DAG__ en __INDEX__ worden door JS vervangen) -->
            <template id="activiteitSjabloon">
                <?= $activiteitRij('__DAG__', '__INDEX__') ?>
            </template>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" integrity="sha512-7eHRwcbYkK4d9g/6tD/mhkf++eoTHwpNM9woBxtPUBWm67zeAfFC+HrdoE2GanKeocly/VxeLvIqwvCdk7qScg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="/js/bootstrap.bundle.js"></script>
    <script src="/js/form-validatie.js"></script>
    <script src="/js/animaties.js"></script>
    <script src="/js/activiteiten-per-dag.js?v=<?= filemtime(__DIR__ . '/../../../js/activiteiten-per-dag.js') ?>"></script>
</body>
</html>

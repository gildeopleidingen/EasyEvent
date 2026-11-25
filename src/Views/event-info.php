<?php
$gebruikerID   = $gebruikerID   ?? null;
$activities    = $activities    ?? [];
$organisations = $organisations ?? [];
$active_roles  = $active_roles  ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- favicons -->
    <link rel="shortcut icon" href="/images/icons/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/images/icons/favicon-16x16.png" type="image/x-icon" sizes="16x16">
    <link rel="shortcut icon" href="/images/icons/favicon-32x32.png" type="image/x-icon" sizes="32x32">

    <title>EasyEvents | Eventinformatie</title>

    <!-- css -->
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/event-info.css">
    <link rel="stylesheet" href="/css/nav.css">
    <link rel="stylesheet" href="/css/event-info.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container-fluid vh-100 d-flex flex-column">
        <?php require_once("./parts/nav.php"); ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-danger"><?php echo $success; ?></div>
        <?php endif; ?>
        

        <div class="flex-grow-1 position-relative d-flex align-items-center justify-content-center rounded-4 mb-4 bg-dark" 
            style="background-image: url('../../images/<?= htmlspecialchars($event->getEventBanner()) ?>'); background-size: cover; background-position: center;">

            <div class="position-absolute top-0 start-0 w-100 h-100 rounded-4 bg-dark opacity-50"></div>
            
            <div class="position-relative text-white px-3">
                <h1 class="text-uppercase underline mb-4"><?= htmlspecialchars($event->getEventName()) ?></h1>
            </div>
            
            <div class="position-absolute text-white top-50 end-0 translate-middle-y text-dark rounded-3 shadow p-4 me-4" style="width: 300px; min-height: ; background-color: rgba(254, 141, 38, 0.5);">
                <h5 class="fw-bold">
                    <?php
                    $eventTime = $event->getEventTime()[0] ?? [];
                    echo htmlspecialchars($eventTime['date'] ?? 'Datum onbekend');
                    ?>
                </h5>
                <p class="mb-2">
                    <?= htmlspecialchars($eventTime['startTime'] ?? '') ?> - <?= htmlspecialchars($eventTime['endTime'] ?? '') ?>
                </p>
                <p class="small"><?= htmlspecialchars($event->getEventInfo()); ?></p>
                <a href="#" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#activiteiten">Bekijk de activiteiten</a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="activiteiten" tabindex="-1" aria-labelledby="activiteitenModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <?php if (isset($gebruikerID)): ?>
                    <form id="activityForm" action="/event-info?eventID=<?= $event->getEventID() ?>" Method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="activiteitenModalLabel">Selecteer Activiteiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <input type="hidden" name="eventID" value="<?= htmlspecialchars($event->getEventID()) ?>" />
                        <div class="modal-body">

                            <?php if (!empty($activities)): ?>
                                Kies de activiteit waarin je aan wil deelnemen.
                                <?php foreach ($activities as $activity): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="activity<?= htmlspecialchars($activity->getID()) ?>" name="activities[<?= htmlspecialchars($activity->getID()) ?>][checked]" <?php if ($activity->hasUser()) : ?> checked="checked"<?php endif; ?> value="<?= htmlspecialchars($activity->getID()) ?>">
                                    <label class="form-check-label" for="activity<?= htmlspecialchars($activity->getID()) ?>"><?= htmlspecialchars($activity->getName()) ?></label>
                                </div>
                                <?php
                                    $minTijd = substr($activity->minTijd ?? $activity->getBeginTijd(), 0, 5);
                                    $maxTijd = substr($activity->maxTijd ?? $activity->getEindTijd(), 0, 5);
                                ?>
                                <div class="d-flex align-items-center gap-2 mb-3 ms-4">
                                    <input type="time" class="form-control form-control-sm w-auto"
                                           name="activities[<?= htmlspecialchars($activity->getID()) ?>][startTime]"
                                           value="<?= htmlspecialchars(substr($activity->getBeginTijd(), 0, 5)) ?>"
                                           min="<?= htmlspecialchars($minTijd) ?>" max="<?= htmlspecialchars($maxTijd) ?>" required />
                                    <span>&ndash;</span>
                                    <input type="time" class="form-control form-control-sm w-auto"
                                           name="activities[<?= htmlspecialchars($activity->getID()) ?>][endTime]"
                                           value="<?= htmlspecialchars(substr($activity->getEindTijd(), 0, 5)) ?>"
                                           min="<?= htmlspecialchars($minTijd) ?>" max="<?= htmlspecialchars($maxTijd) ?>" required />
                                    <small class="text-muted">(<?= htmlspecialchars($minTijd) ?> &ndash; <?= htmlspecialchars($maxTijd) ?>)</small>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Geen activiteiten gevonden.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary" <?php if (empty($activities)): ?> disabled="disabled" <?php endif; ?>>Inschrijven</button>
                        </div>
                    </form>
                <?php endif; ?>
                <?php if (!isset($gebruikerID)): ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="activiteitenModalLabel">Activiteiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($activities)): ?>
                            <?php foreach ($activities as $activity): ?>
                                <p class="" id="<?= htmlspecialchars($activity->getID()) ?>"><?= htmlspecialchars($activity->getName()) ?></p>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <p>Geen activiteiten gevonden.</p>
                            <?php endif; ?>
                        </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" integrity="sha512-7eHRwcbYkK4d9g/6tD/mhkf++eoTHwpNM9woBxtPUBWm67zeAfFC+HrdoE2GanKeocly/VxeLvIqwvCdk7qScg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="/js/animaties.js"></script>
    <script src="/js/bootstrap.bundle.js"></script>
</body>
</html>


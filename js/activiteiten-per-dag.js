// Event aanmaken – stap 2: activiteiten per dag (één tab per dag).
// - activiteit toevoegen / verwijderen per dag
// - teller per dag-tab
// - eindtijd moet na begintijd liggen
// - bij een ongeldig formulier de tab openen waar de eerste fout staat
// Alle tabs zitten in hetzelfde formulier, dus gegevens blijven bewaard bij het wisselen.
(function () {
    const form = document.getElementById('formActivities');
    const sjabloon = document.getElementById('activiteitSjabloon');
    if (!form || !sjabloon) {
        return;
    }

    function controleerTijden(rij) {
        const begin = rij.querySelector('.js-begin');
        const eind = rij.querySelector('.js-eind');
        if (begin.value && eind.value && eind.value <= begin.value) {
            eind.setCustomValidity('Eindtijd moet na begintijd zijn.');
        } else {
            eind.setCustomValidity('');
        }
    }

    function updateDag(dag) {
        const aantal = dag.querySelectorAll('.js-activiteit-rij').length;
        dag.querySelector('.js-geen-activiteiten').hidden = aantal > 0;

        const teller = form.querySelector(`[data-bs-target="#${dag.id}"] .js-dag-teller`);
        if (teller) {
            teller.textContent = aantal;
        }
    }

    function koppelRij(rij) {
        rij.querySelectorAll('.js-begin, .js-eind').forEach((input) => {
            input.addEventListener('input', () => controleerTijden(rij));
        });
        controleerTijden(rij);
    }

    form.querySelectorAll('.js-dag').forEach((dag) => {
        dag.querySelectorAll('.js-activiteit-rij').forEach(koppelRij);
        updateDag(dag);

        dag.querySelector('.js-voeg-activiteit-toe').addEventListener('click', () => {
            const index = parseInt(dag.dataset.volgendeIndex, 10) || 0;
            dag.dataset.volgendeIndex = index + 1;

            const html = sjabloon.innerHTML
                .replaceAll('__DAG__', dag.dataset.eventTijdId)
                .replaceAll('__INDEX__', index);
            const tijdelijk = document.createElement('div');
            tijdelijk.innerHTML = html.trim();
            const rij = tijdelijk.firstElementChild;

            dag.querySelector('.js-activiteiten-lijst').appendChild(rij);
            koppelRij(rij);
            updateDag(dag);
            rij.querySelector('input').focus();
        });
    });

    // Verwijderen (event delegation, werkt ook voor nieuw toegevoegde rijen)
    form.addEventListener('click', (e) => {
        const knop = e.target.closest('.js-verwijder-activiteit');
        if (!knop) {
            return;
        }
        const dag = knop.closest('.js-dag');
        knop.closest('.js-activiteit-rij').remove();
        updateDag(dag);
    });

    // form-validatie.js blokkeert het versturen bij fouten; hier openen we de tab met de fout.
    form.addEventListener('submit', () => {
        const eersteFout = form.querySelector(':invalid');
        if (!eersteFout) {
            return;
        }
        const dag = eersteFout.closest('.js-dag');
        const tab = dag ? form.querySelector(`[data-bs-target="#${dag.id}"]`) : null;
        if (tab && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }
        eersteFout.focus();
    });
})();

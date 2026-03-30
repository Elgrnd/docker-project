document.querySelectorAll('.btn-filter-classe').forEach(btn => {
    btn.addEventListener('click', () => {
        const isActive = btn.classList.contains('active');

        document.querySelectorAll('.btn-filter-classe').forEach(b => {
            b.classList.remove('active', 'btn-danger');
            b.classList.add('btn-outline-primary');
            b.textContent = b.dataset.classe;
        });

        const rows = document.querySelectorAll('#usersTable tbody tr');

        if (isActive) {
            rows.forEach(row => row.style.display = '');
        } else {
            const classe = btn.dataset.classe;
            btn.classList.add('active', 'btn-danger');
            btn.classList.remove('btn-outline-primary');
            btn.textContent = 'Annuler';

            rows.forEach(row => {
                row.style.display = (isActive || row.dataset.classe === classe) ? '' : 'none';
            });
        }
    });
});

const selectAll = document.getElementById('selectAll');
const clearBtn = document.getElementById('clearSelection');
const countLabel = document.getElementById('selectionCount');

// Sélectionner / désélectionner tout
selectAll.addEventListener('change', () => {
    document.querySelectorAll('.user-checkbox').forEach(cb => {
        // Ne cocher que les lignes visibles (filtrées)
        const row = cb.closest('tr');
        if (row.style.display !== 'none') {
            cb.checked = selectAll.checked;
        }
    });
    updateSelectionUI();
});

// Checkbox individuelle
document.querySelectorAll('.user-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectionUI);
});

// Bouton tout désélectionner
clearBtn.addEventListener('click', () => {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    selectAll.checked = false;
    updateSelectionUI();
});

// Récupérer les IDs sélectionnés (utile pour la suite)
function getSelectedIds() {
    return [...document.querySelectorAll('.user-checkbox:checked')]
        .map(cb => cb.value);
}

const btnCreerGroupe = document.getElementById('btnCreerGroupe');
const modalNbMembres = document.getElementById('modalNbMembres');
const membresIdsInput = document.getElementById('membresIds');
const creerGroupeModal = new bootstrap.Modal(document.getElementById('creerGroupeModal'));

// Afficher/cacher le bouton "Créer un groupe"
function updateSelectionUI() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    const total = checked.length;

    countLabel.textContent = `${total} sélectionné(s)`;
    clearBtn.classList.toggle('d-none', total === 0);
    btnCreerGroupe.classList.toggle('d-none', total === 0); // ← nouveau
    selectAll.indeterminate = total > 0 && total < document.querySelectorAll('.user-checkbox').length;
    selectAll.checked = total > 0 && total === document.querySelectorAll('.user-checkbox').length;
}

// Ouvrir le modal avec les IDs sélectionnés
btnCreerGroupe.addEventListener('click', () => {
    const ids = getSelectedIds();
    membresIdsInput.value = ids.join(',');
    modalNbMembres.textContent = ids.length;
    creerGroupeModal.show();
});
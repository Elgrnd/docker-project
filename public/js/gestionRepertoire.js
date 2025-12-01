// Fonction pour rendre un répertoire éditable
function renameRepertoire(folderId) {
    const folderElement = document.querySelector(`[data-folder-id="${folderId}"]`);
    if (!folderElement) return;

    const folderNameElement = folderElement.querySelector('strong');
    const currentName = folderNameElement.textContent.trim();

    // Créer un input pour éditer le nom
    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    input.className = 'form-control form-control-sm d-inline-block';
    input.style.width = '200px';
    input.style.marginLeft = '5px';

    // Créer les boutons de validation/annulation
    const btnSave = document.createElement('button');
    btnSave.className = 'btn btn-success btn-sm ms-2';
    btnSave.innerHTML = '<i class="bi bi-check"></i>';
    btnSave.title = 'Valider';

    const btnCancel = document.createElement('button');
    btnCancel.className = 'btn btn-secondary btn-sm ms-1';
    btnCancel.innerHTML = '<i class="bi bi-x"></i>';
    btnCancel.title = 'Annuler';

    // Sauvegarder l'élément original
    const originalElement = folderNameElement.cloneNode(true);

    // Remplacer le nom par l'input
    folderNameElement.replaceWith(input);
    input.insertAdjacentElement('afterend', btnSave);
    btnSave.insertAdjacentElement('afterend', btnCancel);

    // Focus sur l'input et sélectionner le texte
    input.focus();
    input.select();

    // Fonction pour restaurer l'état original
    const restore = () => {
        input.replaceWith(originalElement);
        btnSave.remove();
        btnCancel.remove();
    };

    // Fonction pour sauvegarder le nouveau nom
    const save = async () => {
        const newName = input.value.trim();

        if (newName === '') {
            alert('Le nom du répertoire ne peut pas être vide');
            input.focus();
            return;
        }

        if (newName === currentName) {
            restore();
            return;
        }

        // Désactiver les boutons pendant la requête
        btnSave.disabled = true;
        btnCancel.disabled = true;
        input.disabled = true;

        try {
            let url = Routing.generate('rename_repertoire', {'id':folderId})
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name: newName
                })
            });

            const data = await response.json();

            if (data.success) {
                // Mettre à jour le nom dans le DOM
                originalElement.textContent = newName;
                restore();

                // Afficher un message de succès (optionnel)
                showNotification('Répertoire renommé avec succès', 'success');
            } else {
                // Afficher l'erreur
                alert(data.message || 'Erreur lors du renommage');
                input.disabled = false;
                btnSave.disabled = false;
                btnCancel.disabled = false;
                input.focus();
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors du renommage');
            input.disabled = false;
            btnSave.disabled = false;
            btnCancel.disabled = false;
        }
    };

    // Événements
    btnSave.addEventListener('click', save);
    btnCancel.addEventListener('click', restore);

    // Sauvegarder avec Entrée, annuler avec Échap
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            save();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            restore();
        }
    });
}

// Fonction pour afficher une notification (optionnelle)
function showNotification(message, type = 'info') {
    // Utiliser Bootstrap toast si disponible
    const toastContainer = document.querySelector('.toast-container');
    if (toastContainer) {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        setTimeout(() => toast.remove(), 5000);
    } else {
        // Fallback: simple alert
        console.log(message);
    }
}

// Styles CSS à ajouter (optionnel)
const style = document.createElement('style');
style.textContent = `
    .rename-icon {
        transition: color 0.2s ease;
        font-size: 0.9em;
    }
    
    .tree-folder strong {
        user-select: none;
    }
    
    .tree-folder input.form-control-sm {
        font-weight: bold;
        padding: 2px 6px;
    }
`;
document.head.appendChild(style);
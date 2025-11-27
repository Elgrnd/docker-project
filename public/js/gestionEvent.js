function afficherContenuYamlFile(contenu) {
    const overlay = document.getElementById("overlayBlur");
    const modal = document.getElementById("modalContent");
    const contentDisplay = document.getElementById("contentDisplay");

    contentDisplay.textContent = contenu; // affiche le contenu YAML
    overlay.style.display = "block";
    modal.style.display = "block";
}

function cacherBlur() {
    document.getElementById("overlayBlur").style.display = "none";
    document.getElementById("modalContent").style.display = "none";
}

async function responseDelete(url, csrfToken, event) {
    const response = await fetch(url, {
        method: 'DELETE',
        body: JSON.stringify({
            '_token': csrfToken
        })
    });
    if (response.status === 204) {
        const button = event.target;
        const fichier = button.closest(".file-item");
        fichier.remove();
        const fileList = document.querySelector('.file-list ul');
        if (fileList && fileList.children.length === 0) {
            // Remplacer la liste vide par le message
            fileList.remove();
            const fileListContainer = document.querySelector('.file-list');
            fileListContainer.innerHTML = '<p class="empty-msg">Aucun fichier dans le répertoire</p>';
        }
    }
}

async function deleteYamlFile(event) {
    const fichierId = event.currentTarget.dataset.fichierId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteYamlFile",  {"id": fichierId});

    await responseDelete(url, csrfToken, event);
}

async function deleteYamlFileGroupe(event) {
    const fichierId = event.currentTarget.dataset.fichierId;
    const groupeId = event.currentTarget.dataset.groupeId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteYamlFileGroupe",  {"id": groupeId, "yamlId": fichierId});

    await responseDelete(url, csrfToken, event);
}

// Toggle du formulaire
document.getElementById('toggleFormBtn').addEventListener('click', function(e) {
    e.preventDefault();
    const formContainer = document.getElementById('formContainer');

    if (formContainer.style.display === 'none') {
        formContainer.style.display = 'block';
        this.style.display = 'none';
    }
});

// Bouton annuler
document.getElementById('cancelBtn').addEventListener('click', function() {
    const formContainer = document.getElementById('formContainer');
    const toggleBtn = document.getElementById('toggleFormBtn');

    formContainer.style.display = 'none';
    toggleBtn.style.display = 'inline-block';

    document.querySelector('form').reset();
});

// Toggle des dossiers dans l'arborescence
document.querySelectorAll('.tree-folder').forEach(folder => {
    folder.addEventListener('click', function() {
        const folderId = this.getAttribute('data-folder-id');
        const children = document.querySelector(`[data-children-of="${folderId}"]`);
        const toggle = this.querySelector('.toggle-icon');

        if (children.style.display === 'none') {
            children.style.display = 'block';
            toggle.classList.remove('collapsed');
        } else {
            children.style.display = 'none';
            toggle.classList.add('collapsed');
        }
    });
});

const buttonDeleteU = document.getElementsByClassName("supprimerFichier");
Array.from(buttonDeleteU).forEach(function (button) {
    button.addEventListener("click", deleteYamlFile);
});

const buttonDeleteG = document.getElementsByClassName("supprimerFichierGroupe");
Array.from(buttonDeleteG).forEach(function (button) {
    button.addEventListener("click", deleteYamlFileGroupe);
});




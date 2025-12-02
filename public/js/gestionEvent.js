function afficherContenuYamlFile(contenu) {
    const overlay = document.getElementById("overlayBlur");
    const modal = document.getElementById("modalContent");
    const contentDisplay = document.getElementById("contentDisplay");

    contentDisplay.textContent = contenu; // affiche le contenu YAML
    overlay.style.display = "block";
    modal.style.display = "block";
}

function afficherContenuYamlFileGitlab(url) {
    const overlay = document.getElementById("overlayBlur");
    const modal = document.getElementById("modalContent");
    const contentDisplay = document.getElementById("contentDisplay");

    // Appel AJAX pour récupérer le YAML
    fetch(url)
        .then(response => response.text())
        .then(data => {
            contentDisplay.textContent = data; // Affiche contenu YAML
            overlay.style.display = "block";
            modal.style.display = "block";
        })
        .catch(err => {
            contentDisplay.textContent = "Erreur lors du chargement du fichier.";
            overlay.style.display = "block";
            modal.style.display = "block";
        });
}

function downloadYamlFileGitlab(url, filename) {
    fetch(url)
        .then(response => response.text())
        .then(content => {
            const blob = new Blob([content], { type: 'text/yaml' });
            const fileUrl = window.URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = fileUrl;
            a.download = filename;
            a.click();

            window.URL.revokeObjectURL(fileUrl);
        })
        .catch(() => alert("Erreur lors du téléchargement du fichier."));
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

async function deleteRepertoire(event) {
    const repertoireId = event.currentTarget.dataset.repertoireId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteRepertoire", { "id": repertoireId });

    const response = await fetch(url, {
        method: 'DELETE',
        body: JSON.stringify({
            '_token': csrfToken
        })
    });

    if (response.status === 204) {
        const button = event.target;
        const repertoireElement = button.closest(".tree-item");
        repertoireElement.remove();
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
if (document.getElementById('toggleFormBtn')) {
    document.getElementById('toggleFormBtn').addEventListener('click', function (e) {
        e.preventDefault();
        const formContainer = document.getElementById('formContainer');

        if (formContainer.style.display === 'none') {
            formContainer.style.display = 'block';
            this.style.display = 'none';
        }
    });
}

// Bouton annuler
if (document.getElementById('cancelBtn')) {
    document.getElementById('cancelBtn').addEventListener('click', function () {
        const formContainer = document.getElementById('formContainer');
        const toggleBtn = document.getElementById('toggleFormBtn');

        formContainer.style.display = 'none';
        toggleBtn.style.display = 'inline-block';

        document.querySelector('form').reset();
    });
}


// Toggle des dossiers dans l'arborescence
const foldersWithData = document.querySelectorAll('.tree-folder[data-folder-id]');
if (foldersWithData) {
    foldersWithData.forEach(folder => {
        folder.addEventListener('click', function () {
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
}

Array.from(document.getElementsByClassName("supprimerFichier")).forEach(function (button) {
    button.addEventListener("click", deleteYamlFile);
});

Array.from(document.getElementsByClassName("supprimerFichierGroupe")).forEach(function (button) {
    button.addEventListener("click", deleteYamlFileGroupe);
});

function downloadYamlFile(content, filename) {
    const blob = new Blob([content], { type: 'text/yaml' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    window.URL.revokeObjectURL(url); // libère la mémoire
}

// Toggle des dossiers dans l'arborescence
document.querySelectorAll('.repertoire-gitlab').forEach(folder => {
    folder.addEventListener('click', function () {
        const children = this.nextElementSibling; // <ul class="tree-children">
        const toggle = this.querySelector('.toggle-icon');

        if (!children) return;

        // Toggle affichage
        if (children.style.display === 'none') {
            children.style.display = 'block';
            toggle.classList.remove('collapsed');
        } else {
            children.style.display = 'none';
            toggle.classList.add('collapsed');
        }
    });
});



const buttonsRepertoires = document.getElementsByClassName("supprimerRepertoire")
Array.from(buttonsRepertoires).forEach(function (button) {
    button.addEventListener("click", deleteRepertoire);
});

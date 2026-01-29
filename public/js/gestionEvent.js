function afficherContenuTextFile(contenu) {
    const overlay = document.getElementById("overlayBlur");
    const modal = document.getElementById("modalContent");
    const contentDisplay = document.getElementById("contentDisplay");

    contentDisplay.textContent = contenu;
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

    await response(event, url, csrfToken)
}

async function deleteRepertoireGroupe(event) {
    const repertoireId = event.currentTarget.dataset.repertoireId;
    const groupeId = event.currentTarget.dataset.groupeId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteRepertoireGroupe", {
        "id": groupeId,
        "repertoireId": repertoireId
    });

    await response(event, url, csrfToken)
}

async function response(event, url, csrfToken) {
    const response = await fetch(url, {
        method: 'DELETE',
        body: JSON.stringify({
            '_token': csrfToken
        })
    });

    if (response.status === 204) {
        const button = event.target;
        const repertoireElement = button.closest(".tree-item");
        const parentTree = repertoireElement.parentElement.closest(".tree-item");
        repertoireElement.remove();

        if (parentTree) {
            const countDisplay = parentTree.querySelector(".child-count");
            if (countDisplay) {
                const newCount = parentTree.querySelectorAll(":scope > .tree-children > .tree-item").length;
                countDisplay.textContent = newCount + " sous-répertoire(s)";
            }
        }
    }
}

async function deleteFile(event) {
    const fichierId = event.currentTarget.dataset.fichierId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteFile",  {"id": fichierId});

    await responseDelete(url, csrfToken, event);
}

async function deleteFileGroupe(event) {
    const fichierId = event.currentTarget.dataset.fichierId;
    const groupeId = event.currentTarget.dataset.groupeId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteFileGroupe",  {"id": groupeId, "fileId": fichierId});

    await responseDelete(url, csrfToken, event);
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('toggleFormBtn');
    const formContainer = document.getElementById('formContainer');

    if (!btn || !formContainer) return;

    btn.addEventListener('click', (e) => {
        e.preventDefault();

        formContainer.classList.remove('d-none');
        btn.classList.add('d-none');
    });
});


const cancelBtn = document.getElementById('cancelBtn');
if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
        const formContainer = document.getElementById('formContainer');
        const toggleBtn = document.getElementById('toggleFormBtn');

        formContainer.classList.add('d-none');
        toggleBtn.classList.remove('d-none');

        const form = formContainer.querySelector('form');
        if (form) form.reset();
    });
}


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
    button.addEventListener("click", deleteFile);
});

Array.from(document.getElementsByClassName("supprimerFichierGroupe")).forEach(function (button) {
    button.addEventListener("click", deleteFileGroupe);
});

function downloadFileById(fileId) {
    const url = Routing.generate("file_download", { id: fileId });
    window.location.href = url;
}

const buttonsRepertoires = document.getElementsByClassName("supprimerRepertoire")
Array.from(buttonsRepertoires).forEach(function (button) {
    button.addEventListener("click", deleteRepertoire);
});

const buttonsRepertoiresGroupe = document.getElementsByClassName("supprimerRepertoireGroupe")
Array.from(buttonsRepertoiresGroupe).forEach(function (button) {
    button.addEventListener("click", deleteRepertoireGroupe);
});

document.addEventListener('DOMContentLoaded', () => {
    const gitlabFolders = document.querySelectorAll('.tree-folder[data-gitlab-folder]');
    if (!gitlabFolders) return;

    gitlabFolders.forEach(folder => {
        folder.addEventListener('click', function (e) {
            if (e.target.closest('a, button, form')) return;

            const children = this.nextElementSibling;
            const toggle = this.querySelector('.toggle-icon');

            if (!children || !children.classList.contains('tree-children')) return;

            if (children.style.display === 'none') {
                children.style.display = 'block';
                if (toggle) toggle.classList.remove('collapsed');
            } else {
                children.style.display = 'none';
                if (toggle) toggle.classList.add('collapsed');
            }
        });
    });
});
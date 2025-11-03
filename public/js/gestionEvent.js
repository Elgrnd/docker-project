function afficherContenuYamlFile(content) {
    document.getElementById("overlayBlur").style.display = "block";
    document.getElementById("modalContent").style.display = "block";
    document.getElementById("contentDisplay").textContent = content;
    document.getElementById("fileExplorer").classList.add("blur-content");
    document.body.style.overflow = "hidden";
}

function cacherBlur() {
    document.getElementById("overlayBlur").style.display = "none";
    document.getElementById("modalContent").style.display = "none";
    document.getElementById("fileExplorer").classList.remove("blur-content");
    document.body.style.overflow = "auto";
}

async function deleteYamlFile(event) {
    const fichierId = event.currentTarget.dataset.fichierId;
    const csrfToken = event.currentTarget.dataset.csrfToken;

    let url = Routing.generate("deleteYamlFile",  {"id": fichierId});

    const response = await fetch(url, {
        method: 'DELETE',
        body: JSON.stringify({
            '_token': csrfToken
        })});
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

const buttons = document.getElementsByClassName("supprimerFichier");
Array.from(buttons).forEach(function (button) {
    button.addEventListener("click", deleteYamlFile);
});




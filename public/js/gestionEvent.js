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

async function deleteYamlFile(url, element) {
    if (!confirm("Supprimer ce fichier ?")) return;

    alert("Suppression en cours...")

    const token = element.dataset.token;
    const formData = new FormData();
    formData.append('_token', token);

    const response = await fetch(url, { method: 'POST', body: formData });

    if (response.ok) {
        location.reload();
    } else {
        alert('Impossible de supprimer le fichier.');
    }
}




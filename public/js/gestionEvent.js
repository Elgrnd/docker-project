function afficherContenuYamlFile(content) {
    // Afficher l'overlay blur
    document.getElementById("overlayBlur").style.display = "block";

    // Afficher le modal
    document.getElementById("modalContent").style.display = "block";

    // Ajouter le contenu
    document.getElementById("contentDisplay").textContent = content;

    // Ajouter l'effet de flou au contenu principal
    document.getElementById("fileExplorer").classList.add("blur-content");

    // Empêcher le scroll du body
    document.body.style.overflow = "hidden";
}

function cacherBlur() {
    // Cacher l'overlay
    document.getElementById("overlayBlur").style.display = "none";

    // Cacher le modal
    document.getElementById("modalContent").style.display = "none";

    // Retirer l'effet de flou
    document.getElementById("fileExplorer").classList.remove("blur-content");

    // Réactiver le scroll
    document.body.style.overflow = "auto";
}


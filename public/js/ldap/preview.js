function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const header = section.previousElementSibling;
    const icon = header.querySelector('.toggle-icon');

    // Toggle display
    section.classList.toggle('show');

    // Rotation de l'icône
    icon.classList.toggle('open');
}
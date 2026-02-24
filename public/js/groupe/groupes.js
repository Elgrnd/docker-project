document.addEventListener('DOMContentLoaded', () => {
    const deleteForms = document.querySelectorAll('.delete-groupe-form');

    deleteForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const message = form.dataset.confirmMessage || 'Êtes-vous sûr ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});

// Fonction de recherche
document.getElementById('searchGroupe')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.groupe-row');

    rows.forEach(row => {
        const nom = row.getAttribute('data-nom');
        if (nom.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Gestion des dropdowns
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown-fichier');

    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu-fichier');

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();

            // Fermer tous les autres dropdowns
            document.querySelectorAll('.dropdown-menu-fichier').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });

            // Toggle le dropdown actuel
            menu.classList.toggle('show');
        });
    });

    // Fermer le dropdown si on clique ailleurs
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu-fichier').forEach(menu => {
            menu.classList.remove('show');
        });
    });
});
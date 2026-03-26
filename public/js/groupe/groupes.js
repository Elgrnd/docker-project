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

            document.querySelectorAll('.dropdown-fichier').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('open');
                    d.querySelector('.dropdown-menu-fichier')?.classList.remove('show');
                }
            });

            menu.classList.toggle('show');
            dropdown.classList.toggle('open');
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-fichier').forEach(dropdown => {
            dropdown.classList.remove('open');
            dropdown.querySelector('.dropdown-menu-fichier')?.classList.remove('show');
        });
    });
});
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
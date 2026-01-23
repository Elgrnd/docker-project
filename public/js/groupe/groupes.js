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
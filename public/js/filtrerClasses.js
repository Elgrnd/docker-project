document.querySelectorAll('.btn-filter-classe').forEach(btn => {
    btn.addEventListener('click', () => {
        const isActive = btn.classList.contains('active');

        document.querySelectorAll('.btn-filter-classe').forEach(b => {
            b.classList.remove('active', 'btn-danger');
            b.classList.add('btn-outline-primary');
            b.textContent = b.dataset.classe;
        });

        const rows = document.querySelectorAll('#usersTable tbody tr');

        if (isActive) {
            rows.forEach(row => row.style.display = '');
        } else {
            const classe = btn.dataset.classe;
            btn.classList.add('active', 'btn-danger');
            btn.classList.remove('btn-outline-primary');
            btn.textContent = 'Annuler';

            rows.forEach(row => {
                const classeCell = row.querySelector('td:nth-child(5)');
                if (!classeCell) return;
                row.style.display = classeCell.textContent.trim() === classe ? '' : 'none';
            });
        }
    });
});
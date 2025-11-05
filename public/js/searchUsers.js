document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('usersTable');
    const rows = table.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', () => {
        const filter = searchInput.value.toLowerCase().trim();

        for (let i = 1; i < rows.length; i++) {
            const nameCell = rows[i].getElementsByTagName('td')[0];
            const idCell = rows[i].getElementsByTagName('td')[1];

            if (nameCell && idCell) {
                const name = nameCell.textContent.toLowerCase();
                const id = idCell.textContent.toLowerCase();

                if (name.includes(filter) || id.includes(filter)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    });
});

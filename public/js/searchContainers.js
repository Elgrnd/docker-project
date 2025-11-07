document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('containersTable');
    const rows = table.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', () => {
        const filter = searchInput.value.toLowerCase().trim();

        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            if (cells.length < 3) continue;

            const userCell = cells[0];
            const nameCell = cells[1];
            const idCell = cells[2];

            const user = userCell.textContent.toLowerCase();
            const name = nameCell.textContent.toLowerCase();
            const id = idCell.textContent.toLowerCase();

            if (user.includes(filter) || name.includes(filter) || id.includes(filter)) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    });
});

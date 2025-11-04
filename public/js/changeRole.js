const selectRole = document.getElementById('role-select');

selectRole.addEventListener('selectionchange', function () {
    changerRole()
})

async function changerRole() {
    let URL = Routing.generate('changeRole', {"login": selectRole.dataset.utilisateurId})
    const response = await fetch(URL, {method:"GET"})
}
const roleSelectors = document.querySelectorAll('select[data-utilisateur-login]');
Array.from(roleSelectors).forEach(function(selector) {
    selector.addEventListener('change', changerRoleGroupe);
});

async function changerRoleGroupe(event) {
    let selectRole = event.target;

    let URL = Routing.generate('changeRoleGroupe', {
        id: selectRole.dataset.groupeId,
        login: selectRole.dataset.utilisateurLogin,
        role: selectRole.value
    });

    await fetch(URL, { method: "POST" });
}

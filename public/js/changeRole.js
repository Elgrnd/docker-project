const roleSelectors = document.querySelectorAll('.role-select');
Array.from(roleSelectors).forEach(function(selector) {
    selector.addEventListener('change', changerRole)
})


async function changerRole(event) {
    let selectRole = event.target;
    let URL = Routing.generate('changeRole', {"login": selectRole.dataset.utilisateurLogin, "role": selectRole.value})
    await fetch(URL, {method:"POST"})
}
document.addEventListener('DOMContentLoaded', () => {
    const vmSelect = document.getElementById('vmSelect');

    if (!vmSelect) return;

    vmSelect.addEventListener('change', () => {
        const vmId = vmSelect.value;

        window.location.href = Routing.generate('listContainers', {"id": vmId})
    });
});

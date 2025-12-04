document.addEventListener("DOMContentLoaded", () => {

    const box = document.getElementById("vm-status-box");

    function refreshStatus() {
        fetch("/sae-docker/public/vm/status")
            .then(res => res.json())
            .then(data => {
                const status = data.status;

                if (status === "creating") {
                    box.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-hourglass-split"></i>
                            Votre machine Proxmox est en cours de création...
                        </div>
                    `;
                } else if (status === "ready") {
                    box.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i>
                            Votre machine Proxmox est prête à être utilisée !
                        </div>
                    `;
                } else {
                    box.innerHTML = "";
                }
            });
    }

    refreshStatus();

    setInterval(refreshStatus, 3000);
});
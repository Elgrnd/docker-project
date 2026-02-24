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
                }
                else if (status === "ready") {
                    box.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i>
                            Votre machine Proxmox est prête à être utilisée !
                        </div>
    `;
                }
                else if (status === "none" || status === null) {
                    box.innerHTML = `
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-info-circle"></i>
                                Vous n’avez pas encore de machine virtuelle.
                            </div>
                            <button id="create-vm-btn" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Créer ma VM
                            </button>
                        </div>
    `;

                    const btn = document.getElementById("create-vm-btn");
                    if (btn) {
                        btn.addEventListener("click", createVm);
                    }
                }
                else {
                    box.innerHTML = "";
                }

            });
    }

    function createVm() {
        fetch("/sae-docker/public/vm/create", {
            method: "POST"
        }).then(() => {
            refreshStatus();
        });
    }

    refreshStatus();

    setInterval(refreshStatus, 3000);
});
async function refreshVmMonitoring() {
    try {
        const response = await fetch(window.MONITORING_VM_URL);
        if (!response.ok) return;

        const vms = await response.json();
        const tbody = document.querySelector('#vmMonitoringTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        vms.forEach(vm => {
            const uptimeMinutes = vm.uptime
                ? (vm.uptime < 3600
                    ? Math.round(vm.uptime / 60) + ' min'
                    : (vm.uptime / 3600).toFixed(1) + ' h')
                : '-';

            const diskMo = vm.disk
                ? (vm.disk / 1024 / 1024).toFixed(1)
                : '0.6';

            const maxDiskMo = vm.maxdisk
                ? (vm.maxdisk / 1024 / 1024).toFixed(0)
                : '-';

            tbody.innerHTML += `
                <tr>
                    <td>${vm.vmid}</td>
                    <td>${vm.name}</td>
                    <td>${vm.status}</td>
                    <td>${vm.cpu ? (vm.cpu * 100).toFixed(1) + ' %' : '-'}</td>
                    <td>${vm.mem && vm.maxmem
                ? (vm.mem / 1024 / 1024).toFixed(0) + ' / ' + (vm.maxmem / 1024 / 1024).toFixed(0)
                : '-'}</td>
                    <td>${diskMo} / ${maxDiskMo}</td>
                    <td>${uptimeMinutes}</td>
                </tr>
            `;
        });

    } catch (e) {
        console.error('Monitoring error', e);
    }
}

// Rafraîchissement toutes les 5 secondes
setInterval(refreshVmMonitoring, 2000);

// Premier chargement immédiat
refreshVmMonitoring();

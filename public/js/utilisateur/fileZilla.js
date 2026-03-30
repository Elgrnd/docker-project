function copySSH() {
    navigator.clipboard.writeText(document.getElementById('ssh-cmd').innerText);
    const btn = document.querySelector('.btn-copy');
    btn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
    setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i>', 2000);
}
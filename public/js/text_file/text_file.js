document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('textFileUpload');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const submitBtn = document.getElementById('submitBtn');

    // Gérer la sélection de fichier
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag & Drop
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFiles(files);
        }
    });

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.add('show');
            submitBtn.disabled = false;

            // Vérifier l'extension
            const validExtensions = ['yaml', 'yml'];
            const extension = file.name.split('.').pop().toLowerCase();

            if (!validExtensions.includes(extension)) {
                alert('Seuls les fichiers .yaml ou .yml sont acceptés');
                resetFileInput();
            }
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    function resetFileInput() {
        fileInput.value = '';
        fileInfo.classList.remove('show');
        submitBtn.disabled = true;
    }
});
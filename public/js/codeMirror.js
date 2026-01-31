document.addEventListener('DOMContentLoaded', function () {
    function getModeFromFilename(filename) {
        if (!filename) return 'text/plain';

        const ext = filename.split('.').pop().toLowerCase();
        const name = filename.toLowerCase();

        // Cas spéciaux basés sur le nom complet
        if (name === 'dockerfile' || name.startsWith('dockerfile.')) {
            return 'dockerfile';
        }
        if (name === 'makefile' || name.startsWith('makefile.')) {
            return 'cmake';
        }
        if (name.startsWith('.')) {
            return 'shell';  // .env, .gitignore, etc.
        }

        const modeMap = {
            'php': 'php',
            'yml': 'yaml',
            'yaml': 'yaml',
            'json': 'javascript',
            'env': 'shell',
            'toml': 'toml',
            'ini': 'properties',
            'cfg': 'properties',
            'conf': 'nginx',
            'properties': 'properties',
            'xml': 'xml',
            'dockerfile': 'dockerfile',
            'dockerignore': 'shell',
            'gitignore': 'shell',
            'md': 'markdown',
            'txt': 'text/plain',
            'tf': 'javascript',
            'tfvars': 'properties',
            'hcl': 'javascript',
            'sh': 'shell',
            'bash': 'shell',
            'zsh': 'shell',
            'ps1': 'powershell',
            'py': 'python',
            'js': 'javascript',
            'ts': 'javascript',
            'makefile': 'cmake'
        };

        return modeMap[ext] || 'text/plain';
    }

    const textarea = document.getElementById("editor");
    if (!textarea) {
        console.error('❌ Textarea #editor introuvable');
        return;
    }

    const filename = textarea.dataset.filename || '';
    const mode = getModeFromFilename(filename);

    console.log('📄 Filename:', filename);
    console.log('🎨 Mode:', mode);
    console.log('📝 Contenu chargé:', textarea.value.length, 'caractères');

    var editor = CodeMirror.fromTextArea(textarea, {
        lineNumbers: true,
        lineWrapping: true,
        mode: mode,
        theme: "default",
        indentUnit: 4,
        tabSize: 4,
        matchBrackets: true,
        autoCloseBrackets: true,
        indentWithTabs: false
    });

    editor.setSize(null, "70vh");

    console.log('✅ CodeMirror initialisé -', editor.lineCount(), 'lignes');

    // Exposer globalement pour debug/manipulation
    window.codeEditor = editor;

    // Synchronisation avant soumission (sécurité)
    const form = textarea.closest('form');
    if (form) {
        form.addEventListener('submit', function() {
            editor.save(); // Force la synchro textarea ← éditeur
            console.log('💾 Formulaire soumis avec', textarea.value.length, 'caractères');
        });
    }
});

window.onload = function () {
    var editor = CodeMirror.fromTextArea(document.getElementById("editor"), {
        lineNumbers: true,
        lineWrapping: true,
        mode: "yaml",
        theme: "default",
        indentUnit: 2,
        tabSize: 2
    });

    editor.setSize(null, "70vh");

    // Synchroniser le contenu avec l'input hidden avant soumission
    document.getElementById("yaml-form").addEventListener("submit", function(e) {
        document.getElementById("yaml-hidden").value = editor.getValue();
    });
};
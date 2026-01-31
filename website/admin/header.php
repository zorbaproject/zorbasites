<?php
echo '<!DOCTYPE html>';
echo '<head>';

echo '<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    
    <!-- script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script -->
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">';

echo "<!-- TinyMCE -->
    <script src='assets/tinymce/js/tinymce/tinymce.min.js' referrerpolicy='origin'></script>";
echo "<script type=\"text/javascript\">
      tinymce.init({
        selector: 'textarea#tiny',
        license_key: 'gpl',
            plugins: [
            'code', 'a11ychecker','advlist','advcode','advtable','autolink','checklist','export',
            'lists','link','image','charmap','anchor','searchreplace','visualblocks',
            'powerpaste','fullscreen','formatpainter','insertdatetime','media','table','help','wordcount',
            'codesample', 'style'
            ],
            toolbar: 'code | undo redo | a11ycheck casechange blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify |' +
            'bullist numlist checklist outdent indent | removeformat | codesample table help'
      })
    </script>";

echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/codemirror.min.css" /><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/codemirror.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/mode/javascript/javascript.min.js"></script>';

echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
     <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>';

echo '</head><body>';

echo '<a href= "./">Dashboard</a>';

?>

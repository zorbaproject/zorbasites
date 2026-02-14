<?php

require("user_config.php"); 
if(isLoggedIn()){ 
    $user=isLoggedIn(); 
    updateExpire($user['id']); 
    require_once 'utils.php';
    $dsn = "sqlite:$db";
    $pdo = new \PDO($dsn);
    
    /* Page code */
    include("header.php");
    if(isset($_POST['title'])) {
        $templatetitle = $_POST['title'];
        $templateslug = slugify($templatetitle);
        
        
        if(isset($_GET['id'])) {
            $templateid = $_GET['id'];
            $result = $pdo->prepare('SELECT templates.* FROM templates WHERE templates.id = ?');
            $result->execute(array($templateid));
            $template = $result->fetch();
            $currentslug = $template['slug'];
            
            $origslug = $templateslug;
            $s = 2;
            while (slug_exists($templateslug, 0, 'template', $templateid)) {
                $templateslug = $origslug.'-'.$s;
                $s++;
            }
            
            $updqry = $pdo->prepare('UPDATE templates SET title = ?, slug = ? WHERE id = ?');
            $updqry->execute(array($templatetitle, $templateslug, $templateid));
            
            
            if(isset($_POST['content'])) {
                $updqry = $pdo->prepare('UPDATE templates SET content = ? WHERE id = ?');
                $updqry->execute(array($_POST['content'], $templateid));
            }
            if(isset($_POST['from'])) {
                if ($_POST['from'] != '') {
                    $fromcontent = '';
                    $src_templates = list_src_pages();
                    $frompath = $src_templates[intval($_POST['from'])]['path'];
                    
                    if (str_ends_with($frompath, '.html')==false||$frompath=='/index.html') {
                        $frompageid = get_page_from_path($frompath);
                        if ($frompageid > 1) {
                            $result = $pdo->prepare('SELECT pages.* FROM pages WHERE pages.id = ?');
                            $result->execute(array($frompageid));
                            $fromdbpage = $result->fetch();
                            $fromcontent = $fromdbpage['content'];
                            $updqry = $pdo->prepare('UPDATE templates SET content = ? WHERE id = ?');
                            $updqry->execute(array($fromcontent, $templateid));
                        }
                    }
                    if (str_ends_with($frompath, '.html')&&file_exists($basedir.$frompath)) {
                        $fromcontent = file_get_contents($basedir.$frompath);
                    }
                    $updqry = $pdo->prepare('UPDATE templates SET content = ? WHERE id = ?');
                    $updqry->execute(array($fromcontent, $templateid));
                }
            }
        } else {
            $origslug = $templateslug;
            $s = 2;
            while (slug_exists($templateslug, 0, 'template')) {
                $templateslug = $origslug.'-'.$s;
                $s++;
            }
            $insqry = $pdo->prepare('INSERT INTO templates (title, slug) VALUES ( ?, ? ) ');
            $insqry->execute(array($templatetitle, $templateslug));
            $result = $pdo->prepare('SELECT id FROM templates WHERE slug = ?');
            $result->execute(array($templateslug));
            $row = $result->fetch();
            $templateid = $row['id'];
        }
        
        $header_redirect = 'edit_template.php?id='.$templateid;
        if (!$debug) echo "<a href='".$header_redirect."'>Page updated, go to its details</a> <meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
    }
    
    if(isset($_GET['id'])) {
        
        $templateid = $_GET['id'];
        
        
        $result = $pdo->prepare('SELECT templates.* FROM templates WHERE templates.id = ?');
        $result->execute(array($templateid));
        $template = $result->fetch();
        
        if(isset($_POST['deleted_on'])) {
            if ($_POST['deleted_on'] == 'now' && in_array($template['slug'], $protected_templates) == false ) {
                $updqry = $pdo->prepare('UPDATE templates SET deleted_on = CURRENT_TIMESTAMP WHERE id = ?');
                $updqry->execute(array($templateid));
                $header_redirect = 'index.php';
                if (!$debug) echo "<a href='".$header_redirect."'>Page deleted, go back to index </a> <meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
            }
        }
        
        echo '<h1>'.$template['title'].'</h1>';
        //print_r($template);
        echo '<span><form action="edit_template.php?id='.$templateid.'" method="POST" style="display:inline"><input type="hidden" name="deleted_on" id="pagedel" value="now"/><button type="submit" class="btn btn-danger me-2"/><i class="bi bi-trash-fill"></i>Delete template</button></form></span>';
        echo '<div class="mb-2"></div>';
        echo '<form action="edit_template.php?id='.$templateid.'" method="POST">';
        echo '<button type="submit" class="btn btn-success me-2" ><i class="bi bi-floppy-fill"></i>Save template</button>';
        echo '<a target="_blank" href="preview.php?template='.$templateid.'"><button type="button" class="btn btn-warning me-2"><i class="bi bi-easel2-fill"></i>Preview template</button></a></br>';
        echo '</br><label>Page title:</label><input type="text" name="title" id="pagetitle" value="'.$template['title'].'"/>';
        
        echo '<label>Copy content from existing page:</label><select name="from" id="pagefrom"/>';
        echo '<option value="">---</option>';
        $src_templates = list_src_pages();
        foreach($src_templates as $rowid => $row) {
            echo '<option value="'.$rowid.'">'.$row['path'].'</option>';
        }
        echo '</select></br>';
        
        echo 'Please insert {{ content }} where you want your page content to be placed. There are also other variables, like {{ page.title }}, etc...';
        
        //echo '<div class="border border-primary"><textarea id="editor" name="content" >'.$template['content'].'</textarea></div>';
        echo '<div class="border border-primary"><textarea id="editor" name="content" >'.htmlspecialchars($template['content']).'</textarea></div>';
        echo '<script>
        var mixedMode = {
        name: "htmlmixed",
        scriptTypes: [{matches: /\/x-handlebars-template|\/x-mustache/i,
        mode: null},
        {matches: /(text|application)\/(x-)?vb(a|script)/i,
        mode: "vbscript"}]
        };
        let editor = CodeMirror.fromTextArea(document.getElementById("editor"), {
        lineNumbers: true,
        //mode:  mixedMode,
        mode: "javascript",
        selectionPointer: true
        });
        </script>';
        
        echo '</form>';
    }
    
    include("footer.php");
    /* End page code */
    
} else{
    echo "<a href='user.php'>Log in here</a>";
}


?>


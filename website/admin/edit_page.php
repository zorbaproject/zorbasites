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
    if(isset($_POST['title'])&&isset($_POST['section'])) {
        $pagetitle = $_POST['title'];
        $pagesection = $_POST['section'];
        $pageslug = slugify($pagetitle);
        $pagepublic = 0;
        if(isset($_POST['public'])) {
            if ($_POST['public'] != 0) $pagepublic = 1;
        }
        $pageformat = 'html';
        if(isset($_POST['format'])) {
            if ($_POST['format'] == 'md') $pageformat = 'md';
        }
        
        if(isset($_GET['id'])) {
            $pageid = $_GET['id'];
            $result = $pdo->prepare('SELECT pages.* FROM pages WHERE pages.id = ?');
            $result->execute(array($pageid));
            $page = $result->fetch();
            $currentslug = $page['slug'];
            
            $origslug = $pageslug;
            $s = 2;
            while (slug_exists($pageslug, $pagesection, 'page', $pageid)) {
                $pageslug = $origslug.'-'.$s;
                $s++;
            }
            if (in_array($currentslug, $protected_pages)) {
                $updqry = $pdo->prepare('UPDATE pages SET title = ?, public = ?, format = ? WHERE id = ?');
                $updqry->execute(array($pagetitle, $pagepublic, $pageformat, $pageid));
            } else {
                $updqry = $pdo->prepare('UPDATE pages SET title = ?, slug = ?, section_id = ?, public = ?, format = ? WHERE id = ?');
                $updqry->execute(array($pagetitle, $pageslug, $pagesection, $pagepublic, $pageformat, $pageid));
            }
            
            if(isset($_POST['template'])) {
                if ($_POST['template'] != '') {
                    $updqry = $pdo->prepare('UPDATE pages SET template_id = ? WHERE id = ?');
                    $updqry->execute(array($_POST['template'], $pageid));
                } else {
                    $updqry = $pdo->prepare('UPDATE pages SET template_id = NULL WHERE id = ?');
                    $updqry->execute(array($pageid));
                }
            }
            if(isset($_POST['content'])) {
                $updqry = $pdo->prepare('UPDATE pages SET content = ? WHERE id = ?');
                $updqry->execute(array($_POST['content'], $pageid));
            }
            //Gestire from
            if(isset($_POST['from'])) {
                if ($_POST['from'] != '') {
                    $fromcontent = '';
                    $src_pages = list_src_pages();
                    $frompath = $src_pages[intval($_POST['from'])]['path'];
                    
                    if (str_ends_with($frompath, '.html')==false||$frompath=='/index.html') {
                        $frompageid = get_page_from_path($frompath);
                        if ($frompageid > 1) {
                            $result = $pdo->prepare('SELECT pages.* FROM pages WHERE pages.id = ?');
                            $result->execute(array($frompageid));
                            $fromdbpage = $result->fetch();
                            $fromcontent = $fromdbpage['content'];
                            $pageformat = $fromdbpage['format'];
                            $updqry = $pdo->prepare('UPDATE pages SET content = ?, format = ? WHERE id = ?');
                            $updqry->execute(array($fromcontent, $pageformat, $pageid));
                            $pagetemplate = $fromdbpage['template_id'];
                            if ($pagetemplate != '' && is_null($pagetemplate)==false) {
                                $updqry = $pdo->prepare('UPDATE pages SET template_id = ? WHERE id = ?');
                                $updqry->execute(array($pagetemplate, $pageid));
                            } else {
                                $updqry = $pdo->prepare('UPDATE pages SET template_id = NULL WHERE id = ?');
                                $updqry->execute(array($pageid));
                            }
                        }
                    }
                    if (str_ends_with($frompath, '.html')&&file_exists($basedir.$frompath)) {
                        $fromcontent = file_get_contents($basedir.$frompath);
                    }
                    $updqry = $pdo->prepare('UPDATE pages SET content = ? WHERE id = ?');
                    $updqry->execute(array($fromcontent, $pageid));
                }
            }
        } else {
            $origslug = $pageslug;
            $s = 2;
            while (slug_exists($pageslug, $pagesection)||in_array($pageslug, $protected_pages)) {
                $pageslug = $origslug.'-'.$s;
                $s++;
            }
            $insqry = $pdo->prepare('INSERT INTO pages (title, slug, section_id) VALUES ( ?, ?, ? ) ');
            $insqry->execute(array($pagetitle, $pageslug, $pagesection));
            $result = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND section_id = ?');
            $result->execute(array($pageslug, $pagesection));
            $row = $result->fetch();
            $pageid = $row['id'];
            if(isset($_POST['template'])) {
                if ($_POST['template'] != '') {
                    $updqry = $pdo->prepare('UPDATE pages SET template_id = ? WHERE id = ?');
                    $updqry->execute(array($_POST['template'], $pageid));
                } else {
                    $updqry = $pdo->prepare('UPDATE pages SET template_id = NULL WHERE id = ?');
                    $updqry->execute(array($pageid));
                }
            }
            //Gestire from
            if(isset($_POST['from'])) {
                if ($_POST['from'] != '') {
                    $fromcontent = '';
                    $src_pages = list_src_pages();
                    $frompath = $src_pages[intval($_POST['from'])]['path'];
                    
                    if (str_ends_with($frompath, '.html')==false||$frompath=='/index.html') {
                        $frompageid = get_page_from_path($frompath);
                        if ($frompageid > 1) {
                            $result = $pdo->prepare('SELECT pages.* FROM pages WHERE pages.id = ?');
                            $result->execute(array($frompageid));
                            $fromdbpage = $result->fetch();
                            $fromcontent = $fromdbpage['content'];
                            $pageformat = $fromdbpage['format'];
                            $updqry = $pdo->prepare('UPDATE pages SET content = ?, format = ?, WHERE id = ?');
                            $updqry->execute(array($fromcontent, $pageformat, $pageid));
                            $pagetemplate = $fromdbpage['template_id'];
                            if ($pagetemplate != '' && is_null($pagetemplate)==false) {
                                $updqry = $pdo->prepare('UPDATE pages SET template_id = ? WHERE id = ?');
                                $updqry->execute(array($pagetemplate, $pageid));
                            } else {
                                $updqry = $pdo->prepare('UPDATE pages SET template_id = NULL WHERE id = ?');
                                $updqry->execute(array($pageid));
                            }
                        }
                    }
                    if (str_ends_with($frompath, '.html')&&file_exists($basedir.$frompath)) {
                        $fromcontent = file_get_contents($basedir.$frompath);
                    }
                    $updqry = $pdo->prepare('UPDATE pages SET content = ? WHERE id = ?');
                    $updqry->execute(array($fromcontent, $pageid));
                }
            }
        }
        
        $header_redirect = 'edit_page.php?id='.$pageid;
        echo "<a href='".$header_redirect."'>Page updated, go to its details</a>";
        if (!$debug) echo "<meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
    }
    
    if(isset($_GET['id'])) {
        
        $pageid = $_GET['id'];
        
        $result = $pdo->prepare('SELECT * FROM sections');
        //$result = $pdo->prepare('SELECT * FROM sections WHERE deleted_on IS NULL');
        $result->execute();
        $sections = $result->fetchAll();
        $result = $pdo->prepare('SELECT id,slug,title FROM templates WHERE deleted_on IS NULL');
        $result->execute();
        $templates = $result->fetchAll();
        
        $result = $pdo->prepare('SELECT pages.*,sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
        $result->execute(array($pageid));
        $page = $result->fetch();
        
        if(isset($_POST['deleted_on'])) {
            if ($_POST['deleted_on'] == 'now' && in_array($page['slug'], $protected_pages) == false ) {
                $updqry = $pdo->prepare('UPDATE pages SET deleted_on = CURRENT_TIMESTAMP WHERE id = ?');
                $updqry->execute(array($pageid));
                $header_redirect = 'index.php';
                if (!$debug) echo "<a href='".$header_redirect."'>Page deleted, go back to index </a> <meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
            }
        }
        
        if(isset($_POST['render'])) {
            if ($_POST['render'] == 'now' && is_null($page['deleted_on']) && $page['public'] == 1 ) {
                $rendercontent = generate_page($pageid);
                $renderpath = $basedir.'/'.get_page_path($pageid);
                if (str_ends_with($renderpath, '/')) $renderpath .= 'index.html';
                $renderpath = preg_replace('/\/+/','/',$renderpath);
                $renderdir = preg_replace('/\/[^\/]*$/', '/', $renderpath);
                if (!is_dir($renderdir)) mkdir($renderdir, 0755, true);
                file_put_contents($renderpath, $rendercontent);
                //TODO: optionally, insert in the same folder a standard .htaccess file
            }
            if ($page['public'] != 1) echo 'PAGE IS NOT PUBLIC, cannot render.';
        }
        
        echo '<h1>'.$page['title'].'</h1>';
        //print_r($page);
        echo '<form action="edit_page.php?id='.$pageid.'" method="POST"><input type="hidden" name="deleted_on" id="pagedel" value="now"/><input type="submit" value="Delete page" /></form>';
        echo '<form action="edit_page.php?id='.$pageid.'" method="POST"><input type="hidden" name="render" id="pagerender" value="now"/><input type="submit" value="Render page" /></form>';
        echo '<form action="edit_page.php?id='.$pageid.'" method="POST">
        <label>Page title:</label><input type="text" name="title" id="pagetitle" value="'.$page['title'].'"/> <input type="submit" value="Save page" /> <a target="_blank" href="preview.php?page='.$pageid.'">Preview page</a> <a target="_blank" href="..'.get_page_path($pageid).'">View current version</a> </br>';
        echo '<p>Current slug: '.$page['slug'].'</p>';
        echo '<label>Section:</label><select name="section" id="pagesection"/>';
        foreach($sections as $row) {
            $secpath = get_sections_path($row['id']);
            $secpath_str = '';
            foreach ($secpath as $sec) {
                $secpath_str .= '/'.$sec;
            }
            $secpath_str = str_replace('//','/',$secpath_str);
            $issel = '';
            if ($row['id'] == $page['section_id']) $issel = 'selected';
            echo '<option value="'.$row['id'].'" '.$issel.'>'.$secpath_str.'</option>';
        }
        echo '</select></br>';
        echo '<label>Template:</label><select name="template" id="pagetemplate"/>';
        echo '<option value="">---</option>';
        foreach($templates as $row) {
            $issel = '';
            if ($row['id'] == $page['template_id']) $issel = 'selected';
            echo '<option value="'.$row['id'].'" '.$issel.'>'.$row['title'].'</option>';
        }
        echo '</select></br>';
        echo '<label>Copy content from existing page:</label><select name="from" id="pagefrom"/>';
        echo '<option value="">---</option>';
        $src_pages = list_src_pages();
        foreach($src_pages as $rowid => $row) {
            echo '<option value="'.$rowid.'">'.$row['path'].'</option>';
        }
        echo '</select></br>';
        $ismd = '';
        if ($page['format'] == 'md') $ismd = 'checked';
        echo '<div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="pageformat" value="md" name="format" '.$ismd.'>
        <label class="form-check-label" for="pageformat">Markdown</label>
        </div>';
        $ispublic = 'checked';
        if ($page['public'] == 0) $ispublic = '';
        echo '<div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="pagepublic" value="1" name="public" '.$ispublic.'>
        <label class="form-check-label" for="pagepublic">Public</label>
        </div>';
        if ($page['format'] == 'html') {
            if (is_null($page['template_id'])) {
                //echo '<div class="border border-primary"><textarea id="editor" name="content" >'.$page['content'].'</textarea></div>';
                echo '<div class="border border-primary"><textarea id="editor" name="content" >'.htmlspecialchars($page['content']).'</textarea></div>';
                echo '<script>
                //https://gist.github.com/daraul/7057c25495dc0284d1c4e77997d25938
                /*var htmlspecialcharsmap = {
                             \'&amp;\': \'&\',
                             \'&#038;\': "&",
                             \'&lt;\': \'<\',
                             \'&gt;\': \'>\',
                             \'&quot;\': \'"\',
                             \'&#039;\': "\'",
                             \'&#8217;\': "’",
                             \'&#8216;\': "‘",
                             \'&#8211;\': "–",
                             \'&#8212;\': "—",
                             \'&#8230;\': "…",
                             \'&#8221;\': \'”\'
            };
            for (const [key, value] of Object.entries(htmlspecialcharsmap)) {
                document.getElementById("editor").value = document.getElementById("editor").innerText.replace(key, value);
            }*/
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
            } else {
                //we use tinymce only for pages with templates, since standalone pages should be able to contain anything without tinymce messing it up
                echo '<div><textarea id="tiny" name="content" >'.$page['content'].'</textarea></div>';
            }
        } else {
            echo '<textarea id="mdetext" name="content" >'.$page['content'].'</textarea>
            <script>
            const easyMDE = new EasyMDE({element: document.getElementById(\'mdetext\')});
            </script>';
        }
        echo '</form>';
    }
    
    //https://www.tiny.cloud/solutions/wysiwyg-bootstrap-rich-text-editor/
    
    //https://github.com/Ionaru/easy-markdown-editor
    include("footer.php");
    /* End page code */
    
} else{
    echo "<a href='user.php'>Log in here</a>";
}


?>


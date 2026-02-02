<?php

require("user_config.php"); 
if(isLoggedIn()){ 
    $user=isLoggedIn(); 
    updateExpire($user['id']); 
    require_once 'utils.php';
    
    
    /* Page code */
    include("header.php");
    $result = $pdo->prepare('SELECT id,slug,title,public,parent FROM sections WHERE deleted_on IS NULL');
    $result->execute();
    $sections = $result->fetchAll();
    echo '<h1>Sections</h1>';
    echo '<table>';
    echo '<tr><th></th><th>Title</th><th>Slug</th><th>Published</th></tr>';
    foreach($sections as $row) {
        //print_r($row);
        echo '<tr>';
        echo '<td><a href="edit_section.php?id='.$row['id'].'"><i class="bi bi-pencil-fill"></i></a></td>';
        echo '<td>'.$row['title'].'</td>';
        echo '<td>'.$row['slug'].'</td>';
        $ispub = '<i class="bi bi-check-lg"></i>';
        if ($row['public'] == 0) $ispub = '<i class="bi bi-x-lg"></i>';
        echo '<td>'.$ispub.'</td>';
        echo '</tr>';
    }
    echo '</table>';
    /* Section creation form */
    echo '<form action="edit_section.php" method="POST">
    <label>Section title:</label><input type="text" name="title" id="sectitle"/>';
    echo '<label>Parent section:</label><select name="section" id="secsection"/>';
    foreach($sections as $row) {
        $secpath = get_sections_path($row['id']);
        $secpath_str = '';
        foreach ($secpath as $sec) {
            $secpath_str .= '/'.$sec;
        }
        $secpath_str = str_replace('//','/',$secpath_str);
        echo '<option value="'.$row['id'].'">'.$secpath_str.'</option>';
    }
    echo '</select>';
    echo '<input type="submit" value="Create new section" /></form>';
    
    $result = $pdo->prepare('SELECT id,slug,title FROM templates WHERE deleted_on IS NULL');
    $result->execute();
    $templates = $result->fetchAll();
    echo '<h1>Templates</h1>';
    echo '<p>Templates are special pages that can be used as a skeleton for other pages.</p>';
    echo '<table>';
    echo '<tr><th></th><th>Title</th><th>Slug</th></tr>';
    foreach($templates as $row) {
        //print_r($row);
        echo '<tr>';
        echo '<td><a href="edit_template.php?id='.$row['id'].'"><i class="bi bi-pencil-fill"></i></a></td>';
        echo '<td>'.$row['title'].'</td>';
        echo '<td>'.$row['slug'].'</td>';
        echo '</tr>';
    }
    echo '</table>';
    /* Template creation form */
    echo '<form action="edit_template.php" method="POST">
    <label>Template title:</label><input type="text" name="title" id="templatetitle"/>';
    echo '<input type="submit" value="Create new template" /></form>';
    
    $result = $pdo->prepare('SELECT pages.id, pages.slug, pages.title, pages.public, pages.format, pages.section_id, pages.deleted_on, sections.slug as section_slug, sections.title as section_title, sections.public as section_public, pages.template_id, templates.slug as template_slug, templates.title as template_title FROM pages LEFT JOIN sections on pages.section_id = sections.id LEFT JOIN templates ON pages.template_id = templates.id WHERE pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
    $result->execute();
    $pages = $result->fetchAll();
    echo '<h1>Pages</h1>';
    echo '<table>';
    echo '<tr><th></th><th>Title</th><th>Slug</th><th>Section</th><th>Template</th><th>Format</th><th>Published</th></tr>';
    foreach($pages as $row) {
        //print_r($row);
        echo '<tr>';
        echo '<td><a href="edit_page.php?id='.$row['id'].'"><i class="bi bi-pencil-fill"></i></a><a href="'.get_page_path($row['id']).'"><i class="bi bi-eye-fill"></i></a></td>';
        echo '<td>'.$row['title'].'</td>';
        echo '<td>'.$row['slug'].'</td>';
        echo '<td><a href="edit_section.php?id='.$row['section_id'].'">'.$row['section_title'].'</a></td>';
        echo '<td><a href="edit_template.php?id='.$row['template_id'].'">'.$row['template_title'].'</a></td>';
        echo '<td>'.$row['format'].'</td>';
        $ispub = '<i class="bi bi-check-lg"></i>';
        if ($row['public'] == 0) $ispub = '<i class="bi bi-x-lg"></i>';
        echo '<td>'.$ispub.'</td>';
        echo '</tr>';
    }
    echo '</table>';
    /* Page creation form */
    echo '<form action="edit_page.php" method="POST">
    <label>Page title:</label><input type="text" name="title" id="pagetitle"/>';
    echo '<label>Section:</label><select name="section" id="pagesection"/>';
    foreach($sections as $row) {
        $secpath = get_sections_path($row['id']);
        $secpath_str = '';
        foreach ($secpath as $sec) {
            $secpath_str .= '/'.$sec;
        }
        $secpath_str = str_replace('//','/',$secpath_str);
        echo '<option value="'.$row['id'].'">'.$secpath_str.'</option>';
    }
    echo '</select>';
    echo '<label>Template:</label><select name="template" id="pagetemplate"/>';
    echo '<option value="">---</option>';
    foreach($templates as $row) {
        echo '<option value="'.$row['id'].'">'.$row['title'].'</option>';
    }
    echo '</select>';
    echo '<label>Copy content from existing page:</label><select name="from" id="pagefrom"/>';
    echo '<option value="">---</option>';
    $src_pages = list_src_pages();
    foreach($src_pages as $rowid => $row) {
        echo '<option value="'.$rowid.'">'.$row['path'].'</option>';
    }
    echo '</select>';
    $ismd = '';
    echo '<!--div class="form-check form-switch"-->
    <input class="form-check-input" type="checkbox" role="switch" id="pageformat" value="markdown" name="format" '.$ismd.'>
    <label class="form-check-label" for="secpublic">Markdown</label>
    <!--/div-->';
    echo '<input type="submit" value="Create new page" /></form>';
    
    
    echo '<h1>Files</h1>';
    echo '<a href="upload.php">Manage files</a>';
    
    if(isset($_POST['render'])) {
        foreach($pages as $row) {
            $thispage = $row;
            $ispublic = true;
            if ($thispage['public'] != 1) $ispublic = false;
            $secid = $thispage['section_id'];
            $result = $pdo->prepare('SELECT * FROM sections WHERE id = ?');
            $result->execute(array($secid));
            $sec = $result->fetch();
            while ( $sec['slug'] != 'root' ) {
                $secid = $sec['parent'];
                $result = $pdo->prepare('SELECT * FROM sections WHERE id = ?');
                $result->execute(array($secid));
                $sec = $result->fetch();
                if ($sec['public'] != 1) {
                    $ispublic = false;
                    break;
                }
            }
            if ($_POST['render'] == 'now' && is_null($thispage['deleted_on']) && $ispublic ) {
                $rendercontent = generate_page($thispage['id'], '/theme/');
                $pagepath = get_page_path($thispage['id']);
                $renderpath = $basedir.'/'.$pagepath;
                if (str_ends_with($renderpath, '/')) $renderpath .= 'index.html';
                $renderpath = preg_replace('/\/+/','/',$renderpath);
                $renderdir = preg_replace('/\/[^\/]*$/', '/', $renderpath);
                if (!is_dir($renderdir)) mkdir($renderdir, 0755, true);
                file_put_contents($renderpath, $rendercontent);
                //TODO: optionally, insert in the same folder a standard .htaccess file
                echo 'Rendered page: '.$pagepath.'</br>';
            }
            if ($thispage['public'] != 1) echo 'PAGE '.$thispage['id'].' IS NOT PUBLIC, cannot render.</br>';
        }
        $header_redirect = 'index.php';
        echo "<a href='".$header_redirect."'>Website rendered </a> <meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
    }
    
    echo '<form action="index.php" method="POST"><input type="hidden" name="render" id="pagerender" value="now"/><input type="submit" value="Render website" /></form>';
    
    include("footer.php");
    /* End page code */
    
} else{ 
    echo "<a href='user.php'>Log in here</a>";
}


?>

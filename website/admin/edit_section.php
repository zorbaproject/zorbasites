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
        $sectitle = $_POST['title'];
        $secsection = $_POST['section'];
        $secslug = slugify($sectitle);
        $secpublic = 0;
        if(isset($_POST['public'])) {
            $secpublic = $_POST['public'];
            if ($secpublic != 0) {
                $secpublic = 1;
            }
        }
        
        if(isset($_GET['id'])) {
            $secid = $_GET['id'];
            $origslug = $secslug;
            $s = 2;
            while (slug_exists($secslug, $secsection, 'section', $secid)) {
                $secslug = $origslug.'-'.$s;
                $s++;
            }
            $updqry = $pdo->prepare('UPDATE sections SET title = ?, slug = ?, parent = ?, public = ? WHERE id = ?');
            $updqry->execute(array($sectitle, $secslug, $secsection, $secpublic, $secid));
        } else {
            $origslug = $secslug;
            $s = 2;
            while (slug_exists($secslug, $secsection)) {
                $secslug = $origslug.'-'.$s;
                $s++;
            }
            $insqry = $pdo->prepare('INSERT INTO sections (title, slug, parent) VALUES ( ?, ? , ? ) ');
            $insqry->execute(array($sectitle, $secslug, $secsection));
            $result = $pdo->prepare('SELECT id FROM sections WHERE slug = ?');
            $result->execute(array($secslug));
            $row = $result->fetch();
            $secid = $row['id'];
        }
        
        $header_redirect = 'edit_section.php?id='.$secid;
        echo "<a href='".$header_redirect."'>Section updated, go to its details</a> <meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
    }
    
    if(isset($_GET['id'])) {
        
        $secid = $_GET['id'];
        
        $result = $pdo->prepare('SELECT * FROM sections WHERE id = ?');
        $result->execute(array($secid));
        $section = $result->fetch();
        $result = $pdo->prepare('SELECT * FROM sections WHERE deleted_on IS NULL');
        $result->execute();
        $sections = $result->fetchAll();
        
        echo '<form action="edit_section.php?id='.$secid.'" method="POST">
        <label>Section title:</label><input type="text" name="title" id="sectitle" value="'.$section['title'].'"/></br>';
        echo '<label>Parent section:</label><select name="section" id="secsection"/>';
        foreach($sections as $row) {
            $secpath = get_sections_path($row['id']);
            $secpath_str = '';
            foreach ($secpath as $sec) {
                $secpath_str .= '/'.$sec;
            }
            $issel = '';
            if ($row['id'] == $section['parent']) $issel = 'selected';
            echo '<option value="'.$row['id'].'" '.$issel.'>'.$secpath_str.'</option>';
        }
        echo '</select></br>';
        $secpublic = $section['public'];
        $ispublic = 'checked';
        if ($secpublic == 0) $ispublic = '';
        echo '<div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="secpublic" name="public" '.$ispublic.'>
        <label class="form-check-label" for="secpublic">Public</label>
        </div>';
        echo '<input type="submit" value="Update section" /></form>';
        
        $result = $pdo->prepare('SELECT pages.id, pages.title, pages.slug, sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE sections.id = ?');
        $result->execute(array($secid));
        $pages = $result->fetchAll();
        echo 'Pages in this section:';
        echo '<ul>';
        foreach($pages as $row) {
            echo '<li><a href="edit_page.php?id='.$row['id'].'">'.$row['title'].'</a></li>';
        }
        echo '</ul>';
        
    }
    
    include("footer.php");
    /* End page code */
    
} else{ 
    echo "<a href='user.php'>Log in here</a>";
}


?>


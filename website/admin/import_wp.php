<?php

require("user_config.php");
if(isLoggedIn()){
    $user=isLoggedIn();
    updateExpire($user['id']);
    require_once 'utils.php';
    
    
    /* Page code */
    include("header.php");
    
    if (isset($_POST['wpdb'])) {
        $wpdbname = $_POST['wpdb'];
        $destdir = $uploadfolder."/wpimport";
        $wpdb = $destdir.'/'.$wpdbname;
    }
    
    if (isset($_FILES['userdb'])||isset($_POST['userdb_name'])) {
        $destdir = $uploadfolder."/wpimport";
        while (str_contains($destdir, '//')) $destdir = str_replace('//', '/', $destdir);
        if (!is_dir($destdir)) mkdir($destdir, 0755, true);
        if (isset($_FILES['userdb'])) {
        if ($_FILES['userdb']) {
            
            $tmp_name = $_FILES["userdb"]["tmp_name"];
            $name = basename($_FILES["userdb"]["name"]);
            $name = 'tmp.db';
            move_uploaded_file($tmp_name, $destdir.'/'.$name);
            $fname = preg_replace('/^'.preg_quote($uploadfolder, '/').'/i','',$destdir.'/'.$name);
            
            $wpdb = $destdir.'/'.$name;
        }
        }
        if (isset($_POST['userdb_name'])) {
        if ($_POST['userdb_name'] != '') {
            $name = basename($_POST['userdb_name']);
            $wpdb = $destdir.'/'.$name;
        }
        }
            
        if ($wpdb != ''&&is_file($wpdb)) {

            $pdo_temp = new \PDO("sqlite:$wpdb");
            
            echo '<div class="row mb-3">';
            $result = $pdo_temp->prepare("SELECT MAX(p.ID) as id, t.name as category FROM wp_posts p INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id INNER JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category' INNER JOIN wp_terms t ON tt.term_id = t.term_id GROUP BY t.name");
            $result->execute();
            $sections = $result->fetchAll();
            echo '<h1>Import Sections</h1>';
            /* Section creation form */
            echo '<form action="import_wp.php" method="POST">';
            echo '<table>';
            echo '<tr><th>Import?</th><th>Title</th><th>Slug</th><th>Parent</th></tr>';
            $i = 0;
            foreach($sections as $row) {
                if ($row['id'] == "1") continue;
                echo '<tr>';
                echo '<td><input class="form-check-input" type="checkbox" role="switch" id="secimport_'.$i.'" value="toimport" name="secimport_'.$i.'" checked></td>';
                echo '<td><input type="text" name="title_'.$i.'" id="sectitle_'.$i.'" value="'.$row['category'].'" /></td>';
                echo '<td>'.slugify($row['category']).'</td>';
                echo '<td><select name="section_'.$i.'" id="secsection_'.$i.'"/>';
                echo '<option value="1">/</option>';
                foreach($sections as $rowt) {
                    if ($rowt['id'] == "1") continue;
                    if ($rowt['category'] == $row['category']) continue;
                    echo '<option value="'.slugify($rowt['category']).'">'.$rowt['category'].'</option>';
                }
                echo '</select></td>';
                echo '</tr>';
                $i++;
            }
            echo '</table>';
            echo '<input type="hidden" name="wpdb" id="wpdb" value="'.$name.'" />';
            echo '<input type="submit" value="Import sections" /></form>';
            echo '</div>';
            
            $wpdb = '';
        }

    }
    
    if (isset($wpdb)) {
        
        if (!isset($_POST['pages'])) {
            //print_r($_POST);
            $sectmp = array();
            foreach($_POST as $postvar => $value) {
                if (str_starts_with($postvar, 'secimport_') && $_POST[$postvar] == 'toimport') {
                    $imp_id = str_replace('secimport_', '', $postvar);
                    $sectitle = $_POST['title_'.$imp_id];
                    $secslug = slugify($sectitle);
                    $secsection = 1;
                    $origslug = $secslug;
                    $s = 2;
                    while (slug_exists($secslug, $secsection)) {
                        $secslug = $origslug.'-'.$s;
                        $s++;
                    }
                    $insqry = $pdo->prepare('INSERT INTO sections (title, slug, parent) VALUES ( ?, ? , ? ) ');
                    $insqry->execute(array($sectitle, $secslug, $secsection));
                    $result = $pdo->prepare('SELECT id FROM sections WHERE slug = ? AND parent = ?');
                    $result->execute(array($secslug, $secsection));
                    $row = $result->fetch();
                    $secid = $row['id'];
                    $sectmp[$secid] = $_POST['section_'.$imp_id];
                }
            }
            foreach($sectmp as $secid => $value) {
                try {
                    $result = $pdo->prepare('SELECT id FROM sections WHERE slug = ?');
                    $result->execute(array($sectmp[$secid]));
                    $row = $result->fetch();
                    $secsection = $row['id'];
                    if ($secsection == null) $secsection = 1;
                } catch (Exception $e) {
                    $secsection = 1;
                }
                $updqry = $pdo->prepare('UPDATE sections SET parent = ? WHERE id = ?');
                $updqry->execute(array($secsection, $secid));
                //check again slug
                $result = $pdo->prepare('SELECT title FROM sections WHERE id = ?');
                $result->execute(array($secid));
                $row = $result->fetch();
                $secslug = slugify($row['title']);
                $origslug = $secslug;
                $s = 2;
                while (slug_exists($secslug, $secsection)) {
                    $secslug = $origslug.'-'.$s;
                    $s++;
                }
                $updqry = $pdo->prepare('UPDATE sections SET slug = ? WHERE id = ?');
                $updqry->execute(array($secslug, $secid));
            }
            
            $pdo_temp = new \PDO("sqlite:$wpdb");
            
            $result = $pdo->prepare('SELECT id,slug,title,public,parent FROM sections WHERE deleted_on IS NULL');
            $result->execute();
            $sections = $result->fetchAll();
            
            $result = $pdo_temp->prepare("SELECT wp_posts.id as id, wp_posts.post_content as post_content, wp_posts.post_title, wp2.post_title as parent FROM wp_posts LEFT JOIN ( SELECT id, post_title FROM wp_posts WHERE post_type = 'category') wp2 ON wp_posts.post_parent = wp2.id WHERE (wp_posts.post_type = 'page' OR wp_posts.post_type = 'post') AND wp_posts.post_status = 'publish' ;");
            $result->execute();
            $pages = $result->fetchAll();
            
            $result = $pdo->prepare('SELECT templates.id,templates.slug,templates.title, COUNT(pages.id) as page_count FROM templates LEFT JOIN pages ON templates.id = pages.template_id WHERE templates.deleted_on IS NULL GROUP BY templates.id,templates.slug,templates.title');
            $result->execute();
            $templates = $result->fetchAll();
            
            echo '<div class="row mb-3">';
            echo '<h1>Pages</h1>';
            echo '<form action="import_wp.php" method="POST">';
            echo '<table>';
            echo '<tr><th>Import?</th><th>Title</th><th>Slug</th><th>Section</th><th>Template</th></tr>';
            $i = 0;
            foreach($pages as $row) {
                echo '<tr>';
                echo '<td><input class="form-check-input" type="checkbox" role="switch" id="pageimport_'.$i.'" value="toimport" name="pageimport_'.$i.'" checked></td>';
                echo '<td><input type="text" name="title_'.$i.'" id="pagetitle_'.$i.'" value="'.$row['post_title'].'" /></td>';
                echo '<td>'.slugify($row['post_title']).'</td>';
                echo '<td><select name="section_'.$i.'" id="section_'.$i.'"/>';
                echo '<option value="1">/</option>';
                foreach($sections as $rowt) {
                    if ($rowt['id'] == "1") continue;
                    $secsel = '';
                    if ($rowt['title'] == $row['parent']) $secsel = 'selected';
                    if ($secsel == '' && $rowt['title'] == $row['post_title']) $secsel = 'selected';
                    echo '<option value="'.$rowt['id'].'" '.$secsel.'>'.$rowt['title'].'</option>';
                }
                echo '</select></td>';
                echo '<td><select name="template_'.$i.'" id="pagetemplate_'.$i.'"/>';
                echo '<option value="">---</option>';
                foreach($templates as $rowt) {
                    echo '<option value="'.$rowt['id'].'">'.$rowt['title'].'</option>';
                }
                echo '</select></td>';
                echo '<input type="hidden" name="pageid_'.$i.'" id="pageid_'.$i.'" value="'.$row['id'].'" />';
                echo '</tr>';
                $i++;
            }
            echo '</table>';
            echo '<input type="hidden" name="wpdb" id="wpdb" value="'.$wpdbname.'" />';
            echo '<input type="hidden" name="pages" id="dopages" value="pages" />';
            echo '</div>';
            echo '<input type="submit" value="Import pages" /></form>';
            
        } else {
            
            $pdo_temp = new \PDO("sqlite:$wpdb");
            
            $result = $pdo->prepare('SELECT id,slug,title,public,parent FROM sections WHERE deleted_on IS NULL');
            $result->execute();
            $sections = $result->fetchAll();
            
            $result = $pdo_temp->prepare("SELECT wp_posts.id as id, wp_posts.post_content as post_content, wp_posts.post_title, wp2.post_title as parent FROM wp_posts LEFT JOIN ( SELECT id, post_title FROM wp_posts WHERE post_type = 'category') wp2 ON wp_posts.post_parent = wp2.id WHERE (wp_posts.post_type = 'page' OR wp_posts.post_type = 'post') AND wp_posts.post_status = 'publish' ;");
            $result->execute();
            $pages = $result->fetchAll();
            
            $result = $pdo->prepare('SELECT templates.id,templates.slug,templates.title, COUNT(pages.id) as page_count FROM templates LEFT JOIN pages ON templates.id = pages.template_id WHERE templates.deleted_on IS NULL GROUP BY templates.id,templates.slug,templates.title');
            $result->execute();
            $templates = $result->fetchAll();
            
            $pagetmp = array();
            foreach($_POST as $postvar => $value) {
                if (str_starts_with($postvar, 'pageimport_') && $_POST[$postvar] == 'toimport') {
                    $imp_id = str_replace('pageimport_', '', $postvar);
                    $pagetitle = $_POST['title_'.$imp_id];
                    $pageslug = slugify($pagetitle);
                    $o_pageid = $_POST['pageid_'.$imp_id];
                    $pageformat = 'html';
                    $pagecredits = "";
                    $pagesubtitle = "";
                    $pagecontent = "";
                    foreach($pages as $row) {
                        if ($o_pageid == strval($row['id'])) {
                            $pagecontent = $row['post_content'];
                            //replace files in wp-content
                            $pagecontent = preg_replace('/src *= *".*?\/wp-content\/uploads\//i', 'src="/upload/',$pagecontent);
                            $pagecontent = preg_replace('/href *= *".*?\/wp-content\/uploads\//i', 'href="/upload/',$pagecontent);
                            break;
                        }
                    }
                    $pagesection = $_POST['section_'.$imp_id];
                    $pagsecslug = '';
                    $secparent = 0;
                    foreach($sections as $rowt) {
                        if (strval($rowt['id']) == $pagesection) {
                            $pagsecslug = $rowt['slug'];
                            $secparent = $rowt['parent'];
                            break;
                        }
                    }
                    if ($pagesection == null) $pagesection = 1;
                    if ($pageslug == $pagsecslug) { 
                        $pagesection = $secparent;
                    }
                    $origslug = $pageslug;
                    $s = 2;
                    while (slug_exists($pageslug, $pagesection)||in_array($pageslug, $protected_pages)) {
                        $pageslug = $origslug.'-'.$s;
                        $s++;
                    }
                    $insqry = $pdo->prepare('INSERT INTO pages (title, slug, subtitle, credits, section_id, format) VALUES ( ?, ?, ?, ?, ?, ? ) ');
                    $insqry->execute(array($pagetitle, $pageslug, $pagesubtitle, $pagecredits, $pagesection, $pageformat));
                    $result = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND section_id = ? AND deleted_on IS NULL');
                    $result->execute(array($pageslug, $pagesection));
                    $row = $result->fetch();
                    $pageid = $row['id'];
                    $updqry = $pdo->prepare('UPDATE pages SET content = ? WHERE id = ?');
                    $updqry->execute(array($pagecontent, $pageid));
                    $template = $_POST['template_'.$imp_id];
                    if ($template != '') {
                        $updqry = $pdo->prepare('UPDATE pages SET template_id = ? WHERE id = ?');
                        $updqry->execute(array($template, $pageid));
                    }
                    array_push($pagetmp, $pagetitle);
                }
            }
            echo 'Imported pages:';
            //print_r($_POST);
            echo '<ul>';
            foreach($pagetmp as $titletmp) echo '<li>'.$titletmp.'</li>';
            echo '</ul>';
            unlink($wpdb);
            echo '<p><a href="index.php"> Return to the dashboard</a></p>';
        }
        
    } else {
        echo '<div class="row mb-3">';
        echo '<ol>
        <li>Get files from "wp-content/uploads" from your Wordpress instance and upload them on ZorbaSites under "upload" folder</li>
        <li>Get a database dump from your Wordpress instance</li>
        <li>Convert the dump to SQLite: <a target="_blank" href="https://github.com/mysql2sqlite/mysql2sqlite">https://github.com/mysql2sqlite/mysql2sqlite</a></li>
        <li>Upload Wordpress database in SQlite format:</li>
        </ol>';
        echo '<form action="import_wp.php" method="post" enctype="multipart/form-data">
        <input name="userdb" type="file" />
        <input type="submit" value="Upload" />
        </form>';

        echo '<li>Alternatively (for very large files) load the db via FTP in the "uploads/wpimport", and then write its name here:</li>';
        echo '<form action="import_wp.php" method="post" >
        <input name="userdb_name" type="text" />
        <input type="submit" value="Load from existing file" />
        </form>';
        
        echo '</div>';
    }
    
    
    
    include("footer.php");
    /* End page code */
    
} else{
    echo "<a href='user.php'>Log in here</a>";
}


?>

<?php

require_once 'config.php';

require 'vendor/autoload.php';

$admin_location = basename(__DIR__); //The default is "admin", but you can rename the folder to reduce bot attacks

$protected_pages = [ $admin_location, "maintenance", "theme", "index" ]; //These pages/sections cannot be deleted, moved or created

$pdo = new \PDO("sqlite:$db");

use FastVolt\Helper\Markdown;


function sql_clean($text) {
    $clean = str_replace("\n","",$text);
    $clean = htmlspecialchars($clean);
    return $clean;
}

function slugify($text) {
    $slug = preg_replace('/[^a-z0-9\-]/', '_', strtolower($text));
    while (str_contains($slug, '__')) {
        $slug = str_replace("__","_",$slug);
    }
    $slug = preg_replace('/^_+/', '', $slug);
    $slug = preg_replace('/_+$/', '', $slug);
    return $slug;
}

//Setting constraint UNIQUE to (slug, parent) for sections and (slug, section_id) for pages would not be flexible enough
function slug_exists($slug, $sec, $type = '', $id = -1) {
    global $pdo;
    if ($type == 'template') {
        $foundid = -1;
        $exists = false;
        $result = $pdo->prepare('SELECT id FROM templates WHERE slug = ? AND deleted_on IS NULL');
        $result->execute(array($slug));
        $row = $result->fetch();
        if ($row) {
            $foundid = $row['id'];
            $exists = true;
            if ($foundid == $id && $type == 'template') {
                $exists = false;
                $foundid = -1;
            }
        }
        return $exists;
    }
    //slugs must be unique between sections and pages, withthin the same parent section
    $foundid = -1;
    $exists = false;
    $result = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND section_id = ? AND deleted_on IS NULL');
    $result->execute(array($slug, $sec));
    $row = $result->fetch();
    if ($row) {
        $foundid = $row['id'];
        $exists = true;
        if ($foundid == $id && $type == 'page') {
            $exists = false;
            $foundid = -1;
        }
    }
    if ($exists == false) {
        $result = $pdo->prepare('SELECT id FROM sections WHERE slug = ? AND parent = ? AND deleted_on IS NULL');
        $result->execute(array($slug, $sec));
        $row = $result->fetch();
        if ($row) {
            $foundid = $row['id'];
            $exists = true;
            if ($foundid == $id && $type == 'section') {
                $exists = false;
            }
        }
    }
    
    return $exists;
}

function get_sections_path($secid) {
    global $pdo;
    $secpath = array();
    $result = $pdo->prepare('SELECT * FROM sections WHERE sections.id = ?');
    $result->execute(array($secid));
    $thissec = $result->fetch();
    if ($thissec['slug'] == 'root') $thissec['slug'] = '';
    array_unshift($secpath, $thissec['slug']);
    while (!is_null($thissec['parent'])) {
        $result = $pdo->prepare('SELECT * FROM sections WHERE sections.id = ?');
        $result->execute(array($thissec['parent']));
        $thissec = $result->fetch();
        if ($thissec['slug'] == 'root') $thissec['slug'] = '';
        array_unshift($secpath, $thissec['slug']);
    }
    return $secpath;
}

function get_page_path($pageid) {
    global $pdo;
    $path = '';
    $result = $pdo->prepare('SELECT pages.slug,sections.slug as section_slug, sections.id as section_id, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
    $result->execute(array($pageid));
    $pages = $result->fetchAll();
    foreach($pages as $row) {
        $secpath = get_sections_path($row['section_id']);
        foreach ($secpath as $sec) {
            $path .= '/'.$sec;
        }
        $path .= '/'.$row['slug'];
        if ($row['slug'] != 'index') { 
            $path .= '/';
        } else {
            $path .= '.html';
        }
        break;
    }
    $path = str_replace('//','/',$path);
    return $path;
}

function list_src_pages() {
    global $pdo;
    global $basedir;
    //This function generates a list of pages that can be used as content source. They can be ZorbaSites pages, or just pages available as example of the choosen theme
    $src_pages = array();
    foreach(scandir($basedir.'/theme/') as $row) {
        if (!str_ends_with($row, '.html')) continue;
        $thispage = array();
        $thispage['id'] = '';
        $thispage['path'] = '/theme/'.$row;
        array_push($src_pages, $thispage);
    }
    sort($src_pages);
    $result = $pdo->prepare('SELECT pages.id, pages.slug, pages.title, pages.public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.deleted_on IS NULL AND sections.deleted_on IS NULL ORDER BY pages.id');
    $result->execute();
    $pages = $result->fetchAll();
    foreach($pages as $row) {
        $thispage = array();
        $thispage['id'] = $row['id'];
        $thispage['path'] = get_page_path($row['id']);
        array_push($src_pages, $thispage);
    }
    //TODO: add also templates
    return $src_pages;
}

function list_subdirs($dir) {
    global $uploadfolder;
    $files = scandir($dir);
    $results = array();
    foreach ($files as $key => $value) {
        $path = realpath($dir . '/' . $value);
        if (!is_dir($path) && $value != ".keep") {
            $results[$value] = preg_replace('/^'.preg_quote($uploadfolder, '/').'/i','',$path);
        } else if ($value != "." && $value != ".." && $value != ".keep") {
            $results[$value] = list_subdirs($path);
        }
    }
    return $results;
}

function get_upload_dirs($current_path = '') {
    global $basedir;
    global $uploadfolder;
    if (is_dir($uploadfolder.'/'.$current_path) && str_contains($current_path, '..')==false && $current_path != '') {
      $folderlist = list_subdirs($uploadfolder.'/'.$current_path);
    } else {
      $folderlist = list_subdirs($uploadfolder);
    }
    return $folderlist;
}

function get_page_from_path($mypath) {
    global $pdo;
    global $basedir;
    $myid = -1;
    $result = $pdo->prepare('SELECT pages.id, pages.slug, pages.title, pages.public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.deleted_on IS NULL AND sections.deleted_on IS NULL ORDER BY pages.id');
    $result->execute();
    $pages = $result->fetchAll();
    foreach($pages as $row) {
        if (get_page_path($row['id']) == $mypath) {
            $myid = $row['id'];
            break;
        }
    }
    return $myid;
}

function replace_variables($text, $pageid) {
    $result = $pdo->prepare('SELECT pages.*,sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
    $result->execute(array($pageid));
    $page = $result->fetch();
    $result = $pdo->prepare('SELECT * FROM sections WHERE sections.id = ?');
    $result->execute(array($page['section_id']));
    $section = $result->fetch();
    $variables = array(
        '/\{\{ *page.title *\}\}/i' => $page['title'],
        '/\{\{ *page.slug *\}\}/i' => $page['slug'],
        '/\{\{ *page.path *\}\}/i' => get_page_path($page['id']),
        '/\{\{ *section.title *\}\}/i' => $section['title']
        );
        //foreach variable, preg_replace it case insensitive in the text
}

function markdown_to_html($md) {
    $html = $md;
    $markdown = new Markdown(); // or Markdown::new()
    $markdown->setContent($md);
    $html = $markdown->getHtml();
    return $html;
}

function include_pages($html) {
    $fullcontent = $html;
    //TODO: allow including other templates or pages with {{ template:slug }}
    return $fullcontent;
}

function render_template($templateid, $content = '', $pageid = -1) {
    global $pdo;
    $fullcontent = '';
    $result = $pdo->prepare('SELECT templates.* FROM templates WHERE templates.id = ?');
    $result->execute(array($templateid));
    $template = $result->fetch();
    $fullcontent = $template['content'];
    $fullcontent = preg_replace('/\{\{ *content *\}\}/i', $content, $fullcontent);
    if ($pageid > -1) $fullcontent = replace_variables($fullcontent, $pageid);
    $fullcontent = include_pages($fullcontent);
    return $fullcontent;
}

function generate_page($pageid, $urlprefix = '') {
    global $pdo;
    
    $result = $pdo->prepare('SELECT pages.*,sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
    $result->execute(array($pageid));
    $page = $result->fetch();
    $fullcontent = $page['content'];
    if ($page['format']=='md') $fullcontent = markdown_to_html($page['content']);
    if (is_null($page['template_id'])==false) {
        $fullcontent = render_template($page['template_id'], $fullcontent);
    }
    $fullcontent = preg_replace('~(?:src|action|href)=[\'"]\K(?!http)[^\'"]*~',$urlprefix."$0",$fullcontent); //TODO: maybe check wether the file actually exists
    return $fullcontent;
}

//this is used to get a raw previes of a template
function generate_template($pageid, $urlprefix = '') {
    global $pdo;
    
    $result = $pdo->prepare('SELECT templates.* FROM templates WHERE templates.id = ?');
    $result->execute(array($pageid));
    $page = $result->fetch();
    $fullcontent = $page['content'];
    $fullcontent = include_pages($fullcontent);
    $fullcontent = preg_replace('~(?:src|action|href)=[\'"]\K(?!http)[^\'"]*~',$urlprefix."$0",$fullcontent); //TODO: maybe check wether the file actually exists
    return $fullcontent;
}

function render_website() {
    //This function renders the entire website
    //cycle on all sections, avoid non published
    if (is_null($page['deleted_on']) && $page['public'] == 1 ) {
        $rendercontent = generate_page($pageid, '/theme/');
        $renderpath = $basedir.'/'.get_page_path($pageid);
        if (str_ends_with($renderpath, '/')) $renderpath .= 'index.html';
        $renderpath = preg_replace('/\/+/','/',$renderpath);
        $renderdir = preg_replace('/\/[^\/]*$/', '/', $renderpath);
        if (!is_dir($renderdir)) mkdir($renderdir, 0755, true);
        file_put_contents($renderpath, $rendercontent);
        //TODO: optionally, insert in the same folder a standard .htaccess file
    }
}

?>

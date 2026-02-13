<?php

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once 'config.php';

$admin_location = basename(__DIR__); //The default is "admin", but you can rename the folder to reduce bot attacks

$protected_pages = [ $admin_location, "maintenance", "theme", "index" ]; //These pages/sections cannot be deleted, moved or created

$pdo = null;
if ($installed) $pdo = new \PDO("sqlite:$db");

if (version_compare(phpversion(), '8.0.0') >= 0) {
    require 'vendor/autoload.php';
} else {
    require 'php7_support.php';
}
//use FastVolt\Helper\Markdown;


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
    // Pages with the same slug and path as a section will become the section's home
    $foundid = -1;
    $exists = false;
    $result = $pdo->prepare('SELECT id, section_id FROM pages WHERE slug = ? AND section_id = ? AND deleted_on IS NULL');
    $result->execute(array($slug, $sec));
    $row = $result->fetch();
    if ($row) {
        $foundid = $row['id'];
        $exists = true;
        if (($foundid == $id && $type == 'page') || ($sec == $row['section_id']&& $type == 'section')) {
            $exists = false;
            $foundid = -1;
        }
    }
    if ($exists == false) {
        $result = $pdo->prepare('SELECT id, parent FROM sections WHERE slug = ? AND parent = ? AND deleted_on IS NULL');
        $result->execute(array($slug, $sec));
        $row = $result->fetch();
        if ($row) {
            $foundid = $row['id'];
            $exists = true;
            if (($foundid == $id && $type == 'section') || ( $sec == $row['parent'] && $type = 'page')) {
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

function list_subdirs($dir, $prepend = '') {
    global $uploadfolder;
    $files = scandir($dir);
    $results = array();
    foreach ($files as $key => $value) {
        $path = realpath($dir . '/' . $value);
        if (!is_dir($path) && $value != ".keep") {
            $results[$value] = $prepend.preg_replace('/^'.preg_quote($uploadfolder, '/').'/i','',$path);
        } else if ($value != "." && $value != ".." && $value != ".keep") {
            $results[$value] = list_subdirs($path, $prepend);
        }
    }
    return $results;
}

function get_upload_dirs($current_path = '', $prepend = '') {
    global $basedir;
    global $uploadfolder;
    if (is_dir($uploadfolder.'/'.$current_path) && str_contains($current_path, '..')==false && $current_path != '') {
      $folderlist = list_subdirs($uploadfolder.'/'.$current_path, $prepend);
    } else {
      $folderlist = list_subdirs($uploadfolder, $prepend);
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
    global $pdo;
    $replaced = $text;
    $result = $pdo->prepare('SELECT pages.*,sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
    $result->execute(array($pageid));
    $page = $result->fetch();
    $result = $pdo->prepare('SELECT * FROM sections WHERE sections.id = ?');
    $result->execute(array($page['section_id']));
    $section = $result->fetch();
    $variables = array(
        '/\{\{ *page\.title *\}\}/i' => $page['title'],
        '/\{\{ *page\.slug *\}\}/i' => $page['slug'],
        '/\{\{ *page\.path *\}\}/i' => get_page_path($page['id']),
        '/\{\{ *section\.title *\}\}/i' => $section['title']
    );
    foreach($variables as $search => $replace) {
        $replaced = preg_replace($search, $replace, $replaced);
    }
    return $replaced;
}

//Thanks to https://www.linkedin.com/pulse/write-simple-php-script-convert-md-html-callan-milne-bqwuc
function mdToHTML (
    $input,
    $subtitleElemType = 'h2'  #String HTML Tag name to use for second-level headings
) {
    $htmlContent = $input;
    // Convert paragraphs
    $htmlContent = preg_replace(
        '/([\S ]+)/', 
        '<p>$1</p>', 
        $input
    );
  
    // Convert headings
    $htmlContent = preg_replace(
        '/<p>## ([\S ]+)<\/p>/', 
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemType,
            $subtitleElemType
        ),
        $htmlContent
    );
  
    // Convert lists
    $htmlContent = preg_replace(
        '/<p>- ([\S ]+)<\/p>/', 
        '<li>$1</li>', 
        $htmlContent
    );
    
    $htmlContent = preg_replace(
        '/<p>\* ([\S ]+)<\/p>/', 
        '<li>$1</li>', 
        $htmlContent
    );

    $htmlContent = preg_replace(
        '/((<li>.*<\/li>\s*)+)/', 
        '<ul>$1</ul>', 
        $htmlContent
    );
    
    $htmlContent = preg_replace(
        '/<p>[0-9]+\. ([\S ]+)<\/p>/', 
        '<lio>$1</lio>', 
        $htmlContent
    );

    $htmlContent = preg_replace(
        '/((<lio>.*<\/lio>\s*)+)/', 
        '<ol>$1</ol>', 
        $htmlContent
    );
    $htmlContent = preg_replace(
        '/(<\/*)lio>/', 
        '$1li>', 
        $htmlContent
    );
    
    //Convert bold and italic
    $htmlContent = preg_replace(
        '/\*\*([^\*]+)\*\*/', 
        '<b>$1</b>', 
        $htmlContent
    );
    $htmlContent = preg_replace(
        '/\*([^\*]+)\*/', 
        '<i>$1</i>', 
        $htmlContent
    );
    
    //Links and images
    $htmlContent = preg_replace(
        '/\!\[([^\)]*)\]\("*([^")]+)"*\)/', 
        '<img title="$1" src="$2"/>', 
        $htmlContent
    );
    $htmlContent = preg_replace(
        '/\[(.*?)\]\((.+?)\)/', 
        '<a href="$2">$1</a>', 
        $htmlContent
    );
    
    //Quote
    $htmlContent = preg_replace(
        '/<p>> ([\S ]+)<\/p>/', 
        '<pre>$1</pre>', 
        $htmlContent
    );
    $htmlContent = preg_replace(
        '/<\/pre>(\s*)<pre>/', 
        '$1', 
        $htmlContent
    );

    // Output HTML
    return $htmlContent;
}        

function markdown_to_html($md) {
    $html = $md;
    if(class_exists('FastVolt\Helper\Markdown')) {
        $markdown = new FastVolt\Helper\Markdown(); // or Markdown::new()
        $markdown->setContent($md);
        $html = $markdown->getHtml();
    } else {
        $html = mdToHTML($md);
    }
    return $html;
}

function include_pages($html, $pageid) {
    global $pdo;
    $fullcontent = $html;
    $result = $pdo->prepare('SELECT pages.*,sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
    $result->execute(array($pageid));
    $current_page = $result->fetch();
    preg_match_all("/\{\{ *page: *([^ ]+) *\}\}/i", $fullcontent, $pages, PREG_PATTERN_ORDER);
    //print_r($pages);
    foreach($pages[0] as $i => $tofind) {
        $toreplace = $pages[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.id, pages.content FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        if ($page) {
            if ($page['id'] != $current_page['id']) $rep_content = include_pages($page['content'], $pageid);
            if ($pageid > -1) $rep_content = replace_variables($rep_content, $pageid);
        }
        if (is_null($rep_content)) $rep_content = '';
        $fullcontent = str_replace($tofind, $rep_content, $fullcontent);
    }
    preg_match_all("/\{\{ *template: *([^ ]+) *\}\}/i", $fullcontent, $templates, PREG_PATTERN_ORDER);
    foreach($templates[0] as $i => $tofind) {
        $toreplace = $templates[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT templates.id, templates.content FROM templates WHERE templates.'.$find_col.' = ? AND templates.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $template = $result->fetch();
        if ($template) {
            if ($template['id'] != $current_page['template_id']) $rep_content = render_template($template['id'], '', $pageid);
        }
        if (is_null($rep_content)) $rep_content = '';
        $fullcontent = str_replace($tofind, $rep_content, $fullcontent);
    }
    preg_match_all("/\{\{ *pagepath: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.id, pages.content FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, get_page_path($page['id']), $fullcontent);
    }
    return $fullcontent;
}

function relative_url_fix($content) {
    global $pdo;
    $fullcontent = $content;
    $allpages = array();
    $result = $pdo->prepare('SELECT id FROM pages WHERE deleted_on IS NULL');
    $result->execute();
    $res = $result->fetchAll();
    foreach($res as $row) {
        array_push($allpages, get_page_path($row['id']));
    }
    $urlprefix = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $urlprefix = preg_replace('/\/admin\/*[^\/]*$/i', '/', $urlprefix);
    preg_match_all('/(?:src|action|href) *= *[\'"]\K(?!http)[^\'"]*/i', $fullcontent, $urls, PREG_OFFSET_CAPTURE);
    //print_r($urls);
    foreach(array_reverse($urls[0]) as $i => $match) {
        $tofind = $match[0];
        $pos = $match[1];
        $newlink = $urlprefix.'/theme/'.$tofind;
        if (str_starts_with($tofind, '#')) continue;
        if (preg_match('/^[\/\.]*upload\/.*/i', $tofind)) $newlink = preg_replace('/^\.*\/*upload\//i', $urlprefix.'/upload/', $tofind);
        if (in_array($tofind, $allpages)) $newlink = $urlprefix.$tofind;
        while (str_contains($newlink, '//')) {
            $newlink = str_replace('//', '/', $newlink);
        }
        $fullcontent = substr($fullcontent, 0, $pos).$newlink.substr($fullcontent, $pos+strlen($tofind));
    }
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
    $fullcontent = include_pages($fullcontent, $pageid);
    if ($pageid > -1) $fullcontent = replace_variables($fullcontent, $pageid);
    return $fullcontent;
}

function generate_page($pageid) {
    global $pdo;
    
    $result = $pdo->prepare('SELECT pages.*,sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.id = ?');
    $result->execute(array($pageid));
    $page = $result->fetch();
    $fullcontent = $page['content'];
    if ($page['format']=='md') $fullcontent = mdToHTML($page['content']);
    if (is_null($page['template_id'])==false) {
        $fullcontent = render_template($page['template_id'], $fullcontent, $page['id']);
    } else {
        $fullcontent = include_pages($fullcontent, $pageid);
        if ($pageid > -1) $fullcontent = replace_variables($fullcontent, $pageid);
    }
    $fullcontent = relative_url_fix($fullcontent);
    return $fullcontent;
}

//this is used to get a raw previes of a template
function generate_template($pageid) {
    global $pdo;
    
    $result = $pdo->prepare('SELECT templates.* FROM templates WHERE templates.id = ?');
    $result->execute(array($pageid));
    $page = $result->fetch();
    $fullcontent = $page['content'];
    $fullcontent = include_pages($fullcontent, -1);
    $fullcontent = relative_url_fix($fullcontent);
    return $fullcontent;
}

function render_website() {
    //This function renders the entire website
    //cycles on all sections, avoid non published
    global $pdo;
    global $basedir;
    $result = $pdo->prepare('SELECT pages.id, pages.slug, pages.title, pages.public, pages.format, pages.section_id, pages.deleted_on, sections.slug as section_slug, sections.title as section_title, sections.public as section_public, pages.template_id, templates.slug as template_slug, templates.title as template_title FROM pages LEFT JOIN sections on pages.section_id = sections.id LEFT JOIN templates ON pages.template_id = templates.id WHERE pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
    $result->execute();
    $pages = $result->fetchAll();
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
        if (is_null($thispage['deleted_on']) && $ispublic) {
            $rendercontent = generate_page($thispage['id']);
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
}


function clean_website() {
    global $pdo;
    global $basedir;
    //This function cycles on all sections and removes deleted and non published pages
    $result = $pdo->prepare('SELECT pages.id, pages.slug, pages.title, pages.public, pages.format, pages.section_id, pages.deleted_on, sections.slug as section_slug, sections.title as section_title, sections.public as section_public, pages.template_id, templates.slug as template_slug, templates.title as template_title FROM pages LEFT JOIN sections on pages.section_id = sections.id LEFT JOIN templates ON pages.template_id = templates.id WHERE pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
    $result->execute();
    $pages = $result->fetchAll();
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
        if (is_null($thispage['deleted_on'])==false || $ispublic==false) {
            $pagepath = get_page_path($thispage['id']);
            $renderpath = $basedir.'/'.$pagepath;
            if (str_ends_with($renderpath, '/')) $renderpath .= 'index.html';
            $renderpath = preg_replace('/\/+/','/',$renderpath);
            $renderdir = preg_replace('/\/[^\/]*$/', '/', $renderpath);
            if (file_exists($renderpath)) unlink($renderpath);
            if (is_dir($renderdir) && count(glob($renderdir."/*")) === 0) rmdir($renderdir); //Delete the directory only if empty
            echo 'Deleted path: '.$pagepath.'</br>';
        }
    }
}

?>

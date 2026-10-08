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

if (version_compare(phpversion(), '8.0.0') < 0) {
    require 'php7_support.php';
}

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
    $sectionpages = '';
    $result = $pdo->prepare('SELECT pages.id, pages.title, pages.slug, sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE sections.id = ? AND pages.deleted_on IS NULL');
    $result->execute(array($page['section_id']));
    $spages = $result->fetchAll();
    foreach($spages as $row) {
        $sectionpages .= '<li><a href="'.get_page_path($row['id']).'">'.$row['title'].'</a></li>'."\n";
    }
    $variables = array(
        '/\{\{ *page\.title *\}\}/i' => $page['title'],
        '/\{\{ *page\.slug *\}\}/i' => $page['slug'],
        '/\{\{ *page\.subtitle *\}\}/i' => $page['subtitle'],
        '/\{\{ *page\.credits *\}\}/i' => $page['credits'],
        '/\{\{ *page\.featuredimage *\}\}/i' => $page['featuredimage'],
        '/\{\{ *page\.path *\}\}/i' => get_page_path($page['id']),
        '/\{\{ *section\.title *\}\}/i' => $section['title'],
        '/\{\{ *section\.pageslist *\}\}/i' => $sectionpages
    );
    foreach($variables as $search => $replace) {
        if (is_null($replace)) $replace = '';
        $replaced = preg_replace($search, $replace, $replaced);
    }
    return $replaced;
}

//Rewritten idea from https://dev.to/dcblog/use-php-to-generate-table-of-contents-from-heading-tags-5bma
function set_anchors($html) {
    $fullcontent = $html;
    preg_match_all('/<h([1-6])(?:\s[^>]*)?>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $text = trim(strip_tags($match[2]));
        if( preg_match("/.*<\s*a\s.*/is",$match[2])) continue; //Ignore if there's already a link
        $aslug = strtolower(str_replace("--","-",preg_replace('/[^\da-z]/i', '-', $text)));
        $anchor = '<a name="'.$aslug.'">'.$text.'</a>';
        //$fullcontent = str_replace($text,$anchor,$fullcontent);
        $thisheader = '<h'.$match[1].'>'.$anchor.'</h'.$match[1].'>';
        $fullcontent = str_replace($match[0],$thisheader,$fullcontent);
    }
    return $fullcontent;
}

function generateToC($html) {

    preg_match_all('/<h([1-6])(?:\s[^>]*)?>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER);
    $output = '';
    $prev = 0;
    foreach ($matches as $match) {
        $curr = $match[1];

        $text = trim(strip_tags($match[2]));
        $aslug = strtolower(str_replace("--","-",preg_replace('/[^\da-z]/i', '-', $text)));

        if ($curr > $prev) {

            for ($i = $prev; $i < $curr; $i++) {
                $output .= '<ol class="page-toc">';
            }

        } elseif ($curr < $prev) {

            for ($i = $prev; $i > $curr; $i--) {
                $output .= '</li></ol>';
            }
            $output .= '</li>';

        } else {

            if ($prev > 0) {
                $output .= '</li>';
            }
        }

        $output .= '<li><a href="#'.$aslug.'">'.$text.'</a>';

        $prev = $curr;
    }

    if ($output != '') {
        $output .= '</li>';

        for ($i = $prev; $i > 0; $i--) {
            $output .= '</ol>';

            if ($i > 1) {
                $output .= '</li>';
            }
        }

    }

    return $output;
}


//Thanks to https://www.linkedin.com/pulse/write-simple-php-script-convert-md-html-callan-milne-bqwuc
function mdToHTML (
    $input,
    $subtitleElemType = 'h'  #String HTML Tag name to use for second-level headings
) {
    $htmlContent = $input;

    // Convert tables
    $htmlContent = markdownTablesToHtml($htmlContent);

    // Convert paragraphs
    $htmlContent = preg_replace(
        '/([\S ]+)/',
        '<p>$1</p>',
        $htmlContent
    );

    // Convert headings
    $subtitleElemTypeNum = $subtitleElemType.'6';
    $htmlContent = preg_replace(
        '/<p>###### ([\S ]+)<\/p>/',
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemTypeNum,
            $subtitleElemTypeNum
        ),
        $htmlContent
    );
    $subtitleElemTypeNum = $subtitleElemType.'5';
    $htmlContent = preg_replace(
        '/<p>##### ([\S ]+)<\/p>/',
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemTypeNum,
            $subtitleElemTypeNum
        ),
        $htmlContent
    );
    $subtitleElemTypeNum = $subtitleElemType.'4';
    $htmlContent = preg_replace(
        '/<p>#### ([\S ]+)<\/p>/',
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemTypeNum,
            $subtitleElemTypeNum
        ),
        $htmlContent
    );
    $subtitleElemTypeNum = $subtitleElemType.'3';
    $htmlContent = preg_replace(
        '/<p>### ([\S ]+)<\/p>/',
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemTypeNum,
            $subtitleElemTypeNum
        ),
        $htmlContent
    );
    $subtitleElemTypeNum = $subtitleElemType.'2';
    $htmlContent = preg_replace(
        '/<p>## ([\S ]+)<\/p>/',
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemTypeNum,
            $subtitleElemTypeNum
        ),
        $htmlContent
    );
    $subtitleElemTypeNum = $subtitleElemType.'1';
    $htmlContent = preg_replace(
        '/<p># ([\S ]+)<\/p>/',
        sprintf(
            '<%s>$1</%s>',
            $subtitleElemTypeNum,
            $subtitleElemTypeNum
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
    /*$htmlContent = preg_replace(
        '/\!\[([^\)]*)\]\("*([^")]+)"*\)/',
        '<img title="$1" src="$2"/>',
        $htmlContent
    );*/
    preg_match_all('/\!\[([^\)]*)\]\("*([^")]+)"*\)/i', $htmlContent, $images, PREG_PATTERN_ORDER);
    foreach($images[0] as $i => $tofind) {
        $imgtitle = $images[1][$i];
        $imgdata = explode('|', $images[2][$i]);
        $imgurl = $imgdata[0];
        $imglink = false;
        $imgwidth = '';
        $imgheight = '';
        if (count($imgdata) > 1) {
            if (in_array("link", $imgdata)) $imglink = true;
            foreach($imgdata as $d => $tmpdata) {
                if (str_starts_with($tmpdata, 'width=')) {
                    $tmpnum = explode('=', $tmpdata)[1];
                    $imgwidth = 'width="'.$tmpnum.'"';
                }
                if (str_starts_with($tmpdata, 'height=')) {
                    $tmpnum = explode('=', $tmpdata)[1];
                    $imgheight = 'height="'.$tmpnum.'"';
                }
            }
        }
        $imghtml = '<img title="'.$imgtitle.'" '.$imgwidth.' '.$imgheight.' src="'.$imgurl.'"/>';
        if ($imglink) $imghtml = '<a href="'.$imgurl.'" target="_blank">'.$imghtml.'</a>';
        $htmlContent = str_replace($tofind, $imghtml, $htmlContent);
    }
    /*$htmlContent = preg_replace(
        '/\[(.*?)\]\((.+?)\)/',
        '<a href="$2">$1</a>',
        $htmlContent
    );*/
    preg_match_all('/\[(.*?)\]\((.+?)\)/i', $htmlContent, $links, PREG_PATTERN_ORDER);
    foreach($links[0] as $i => $tofind) {
        $linktext = $links[1][$i];
        $linkdata = explode('|', $links[2][$i]);
        $linkurl = $linkdata[0];
        $linktarget = '';
        if (count($linkdata) > 1) {
            foreach($linkdata as $d => $tmpdata) {
                if (str_starts_with($tmpdata, 'target=')) {
                    $tmptxt = explode('=', $tmpdata)[1];
                    $linktarget = 'target="'.$tmptxt.'"';
                }
            }
        }
        $urlhtml =  '<a href="'.$linkurl.'" '.$linktarget.'>'.$linktext.'</a>';
        $htmlContent = str_replace($tofind, $urlhtml, $htmlContent);
    }

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

function _remove_empty_internal($value) {
  return !empty(trim($value)) && !is_null($value);
}

function markdownTablesToHtml($markdown) {
  /*
   * Looking for:
   * - one row for header
   * - one row for separator
   * - at least one row for data
   */

    $pattern = '/[^|]\n
(\s*\|[^\n|]+(?:\|[^\n]*)+\|\s*)
(\s*\|[\s\-]+(?:\|[\s\-]*)+\|\s*)
(\s*\|[^\n|]+(?:\|[^\n]*)+\|\s*)+
[^|]\n/mx';

    preg_match_all($pattern, $markdown, $tables, PREG_PATTERN_ORDER);
    //print_r($tables);

    foreach($tables[0] as $t => $tbtext) {
        $html = '';
        $lines = preg_split('/\n/', $tbtext);  //$tables[0][$t]
        $lines = array_values(array_filter($lines, '_remove_empty_internal'));
        //print_r($lines);

        $maxcols = 0;
        //Use the second line to calculate how many columns we have in total
        foreach(explode('|', $lines[1]) as $s => $sep ) {
            if (str_contains($sep,'-')) $maxcols++;
        }

        $headers = [];
        // First line
        foreach(explode('|', $lines[0]) as $h => $head ) {
            if (count($headers) == 0 && $head == '') continue;
            $head = trim($head);
            //echo '%'.$head.'%';
            if ($head != '|') array_push($headers, $head);
        }
        //print_r($headers);

        $html = '<table>' . "\n";
        $html .= "    <thead>\n";
        $html .= "        <tr>\n";

        foreach ($headers as $h => $header) {
            if ($h >= $maxcols) continue;
            $html .= "            <th>$header</th>\n";
        }

        $html .= "        </tr>\n";
        $html .= "    </thead>\n";

        // Table body
        $html .= "    <tbody>\n";

        for ($l = 2; $l < count($lines); $l++) {

            $cells = [];
            $row = trim($lines[$l]);
            foreach(explode('|', $row) as $c => $cell ) {
                if (count($cells) == 0 && $cell == '') continue;
                if ($cell != '|') array_push($cells, $cell);
            }

            $html .= "        <tr>\n";

            foreach ($cells as $c => $cell) {
                if ($c >= $maxcols) continue;
                $html .= "            <td>$cell</td>\n";
            }

            $html .= "        </tr>\n";
        }

        $html .= "    </tbody>\n";

        $html .= '</table>';

        $markdown = str_replace($tbtext, $html, $markdown);
    }

    return $markdown;

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
    preg_match_all("/\{\{ *pagetitle: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.id, pages.title, pages.content FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, $page['title'], $fullcontent);
    }
    preg_match_all("/\{\{ *pagesubtitle: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.* FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, $page['subtitle'], $fullcontent);
    }
    preg_match_all("/\{\{ *pagecredits: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.* FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, $page['credits'], $fullcontent);
    }
    preg_match_all("/\{\{ *pagesection: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.*,sections.title as sectitle FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, $page['sectitle'], $fullcontent);
    }
    preg_match_all("/\{\{ *pagesectionpath: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.*,sections.title as sectitle, sections.id as sec_id FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, get_sections_path($page['sec_id']), $fullcontent);
    }
    preg_match_all("/\{\{ *pagefeaturedimage: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $result = $pdo->prepare('SELECT pages.* FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE pages.'.$find_col.' = ? AND pages.deleted_on IS NULL AND sections.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $page = $result->fetch();
        $fullcontent = str_replace($tofind, $page['featuredimage'], $fullcontent);
    }

    preg_match_all("/\{\{ *sectionpages: *([^ ]+) *\}\}/i", $fullcontent, $pagepaths, PREG_PATTERN_ORDER);
    //print_r($pagepaths);
    foreach($pagepaths[0] as $i => $tofind) {
        $toreplace = $pagepaths[1][$i];
        $rep_content = '';
        $find_col = 'slug';
        if (is_numeric($toreplace)) $find_col = 'id';
        $sectionpages = '';
        $result = $pdo->prepare('SELECT pages.id, pages.title, pages.slug, sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE sections.'.$find_col.' = ? AND pages.deleted_on IS NULL');
        $result->execute(array($toreplace));
        $spages = $result->fetchAll();
        foreach($spages as $row) {
            $sectionpages .= '<li><a href="'.get_page_path($row['id']).'">'.$row['title'].'</a></li>'."\n";
        }
        $fullcontent = str_replace($tofind, $sectionpages, $fullcontent);
    }
    return $fullcontent;
}

function relative_url_fix($content) {
    global $pdo;
    global $baseurl;
    $fullcontent = $content;
    $allpages = array();
    $result = $pdo->prepare('SELECT id FROM pages WHERE deleted_on IS NULL');
    $result->execute();
    $res = $result->fetchAll();
    foreach($res as $row) {
        array_push($allpages, get_page_path($row['id']));
    }
    $urlprefix = parse_url($baseurl, PHP_URL_PATH);
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
    //This is for styles like "background-image: url('img.jpg')"
    preg_match_all('/(?:url *\( *)[\'"]\K(?!http)[^\'"]*/i', $fullcontent, $urls, PREG_OFFSET_CAPTURE);
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
    $fullcontent = preg_replace('/\{\{ *page\.toc *\}\}/i', generateToC($content), $fullcontent); //Only generate toc from page content, not template
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
    $fullcontent = set_anchors($fullcontent);
    $fullcontent = preg_replace('/\{\{ *page\.toc *\}\}/i', generateToC($fullcontent), $fullcontent); //generate page toc if included in page content
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
    $result = $pdo->prepare('SELECT pages.id, pages.slug, pages.title, pages.public, pages.format, pages.section_id, pages.deleted_on, sections.slug as section_slug, sections.title as section_title, sections.public as section_public, pages.template_id, templates.slug as template_slug, templates.title as template_title FROM pages LEFT JOIN sections on pages.section_id = sections.id LEFT JOIN templates ON pages.template_id = templates.id');
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
        if ($sec['public'] != 1 || is_null($sec['deleted_on'])==false) $ispublic = false;
        while ( $sec['slug'] != 'root' ) {
            $secid = $sec['parent'];
            $result = $pdo->prepare('SELECT * FROM sections WHERE id = ?');
            $result->execute(array($secid));
            $sec = $result->fetch();
            if ($sec['public'] != 1 || is_null($sec['deleted_on'])==false) {
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

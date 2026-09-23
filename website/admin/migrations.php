<?php

function fieldExsists($pdo, $tablename, $field) {
    $result = $pdo->query("PRAGMA table_info(".$tablename.")");
    $result->setFetchMode(PDO::FETCH_ASSOC);
    $meta = array();
    $exists = false;
    foreach ($result as $row) {
        array_push($meta, $row['name']);
        if ($row['name'] == $field) $exists = true;
    }
    return $exists;
}

function add_page_featuredimage($pdo){
    if (!fieldExsists($pdo, 'pages', 'featuredimage')) {
        $updqry = $pdo->prepare('ALTER TABLE pages ADD COLUMN featuredimage TEXT');
        $updqry->execute();
        echo 'Created column page.featuredimage';
    }
}

function run_migrations($pdo){
    add_page_featuredimage($pdo);
}

?>

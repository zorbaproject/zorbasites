<?php

require("user_config.php");
if(isLoggedIn()){
    $user=isLoggedIn();
    updateExpire($user['id']);
    require_once 'utils.php';
    $dsn = "sqlite:$db";
    $pdo = new \PDO($dsn);
    
    /* Page code */
    //include("header.php");
    
    if(isset($_GET['page'])) {
        $pageid = $_GET['page'];
        $fullcontent = generate_page($pageid);
        echo $fullcontent;
    }
    
    if(isset($_GET['template'])) {
        $pageid = $_GET['template'];
        $fullcontent = generate_template($pageid);
        echo $fullcontent;
    }
    
    //include("footer.php");
    /* End page code */
    
} else{
    echo "<a href='user.php'>Log in here</a>";
}

?>


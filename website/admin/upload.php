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
    
    $current_path = '';
    $pathstr = '';
    if (isset($_GET['path'])) {
        $pathstr = $_GET['path'];
        $fpath = explode('/',$_GET['path']);
        $upfiles = get_upload_dirs();
        foreach ($fpath as $pathel) {
            $upfiles = $upfiles[$pathel];
            if (is_array($upfiles)) $current_path .= $pathel.'/';
        }
    }
    
    if(!empty($_POST['todelete'])) {
        if ($current_path != '') {
            foreach($_POST['todelete'] as $check) {
                $check_path = preg_replace('/\/[^\/]+$/','/',$check);
                $check_name = preg_replace('/^.+\//', '', $check);
                if (str_starts_with($check, $current_path) && $current_path == $check_path) {
                    $upfiles = get_upload_dirs();
                    foreach ($fpath as $pathel) {
                        $upfiles = $upfiles[$pathel];
                    }
                    if ($upfiles[$check_name] == $check) {
                        unlink($uploadfolder.$current_path.$check_name);
                        echo '<p>Deleted: '.$uploadfolder.$current_path.$check_name.'</p>';
                    }
                }
            }
        }
    }
    
    if (isset($_FILES['userfile'])) {
        if ($_FILES['userfile']) {
            
            $uploaded = array();
            foreach ($_FILES["userfile"]["error"] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES["userfile"]["tmp_name"][$key];
                    // basename() may prevent filesystem traversal attacks;
                    // further validation/sanitation of the filename may be appropriate
                    $name = basename($_FILES["userfile"]["name"][$key]);
                    $ext = '';
                    $bname = '';
                    $allowed = false;
                    foreach ($allowed_files as $tmpext) {
                        if (str_ends_with(strtolower($name), $tmpext)) {
                            $ext = $tmpext;
                            $bname = preg_replace('/'.$tmpext.'$/i','',$name);
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) continue;
                    $today = strtotime("now");
		    $destdir = $uploadfolder.$current_path;
		    while (str_contains($destdir, '//')) $destdir = str_replace('//', '/', $destdir);
                    if ($destdir == $uploadfolder) $destdir = $uploadfolder.date("Y",$today).'/'.date("m",$today);
                    if (!is_dir($destdir)) mkdir($destdir, 0755, true);
                    $i = 2;
                    while (file_exists($destdir.'/'.$name)) {
                        $name = $bname.'-'.intval($i).$ext;
                        $i++;
                    }
                    move_uploaded_file($tmp_name, $destdir.'/'.$name);
                    $fname = preg_replace('/^'.preg_quote($uploadfolder, '/').'/i','',$destdir.'/'.$name);
                    array_push($uploaded, $fname);
                }
            }
            echo '<p>Successfully uploaded:</p>';
            echo '<ul>';
            foreach ($uploaded as $fullname) {
                echo '<li>'.$fullname.'</li>';
            }
            echo '</ul>';
        }
    }
    
    $allfiles = get_upload_dirs($current_path);
    //print_r($allfiles);
    echo '<form action="upload.php?path='.$pathstr.'" method="post">';
    echo '<ul class="list-group">';
    foreach ($allfiles as $key => $fullname) {
        if (is_array($fullname)) {
            echo '<li class="list-group-item"><i class="bi bi-folder2"></i><a href ="upload.php?path='.$current_path.$key.'">'.$key.'</a></li>';
        } else {
            echo '<li class="list-group-item">
            <input type="checkbox" name="todelete[]" value="'.$fullname.'">
            <i class="bi bi-file-earmark"></i><a target="_blank" href="../upload/'.$fullname.'">'.$key.'</a></li>';
        }
    }
    echo '<input type="submit" value="Delete selected" />';
    echo '</ul>';
    echo '</form>';
    
    echo 'Upload files:<br />
    <form action="upload.php?path='.$pathstr.'" method="post" enctype="multipart/form-data">
    <!--input name="userfolder[]" type="file" webkitdirectory multiple /-->
    <input name="userfile[]" type="file" multiple />
    <input type="submit" value="Upload" />
    </form>';
    
    
    
    include("footer.php");
    /* End page code */
    
} else{
    echo "<a href='user.php'>Log in here</a>";
}

?>


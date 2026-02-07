<?php

$db = 'zorbasite.db';

$salt='dOqU11k0f5L7'; //Please change this secret for every website
$sess_time=24*60*60; //session expires in seconds before user has to login again.

$allowed_files = ['.pdf', '.png', '.jpg', '.jpeg', '.mp4' , '.mp3', '.ogg'];

$basedir = preg_replace('/[^\/]*$/', '', realpath(__DIR__));

$uploadfolder = $basedir.'upload/';

$installed = file_exists($db);

?>

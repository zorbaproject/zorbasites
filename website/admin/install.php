<?php

//https://github.com/handylulu/Simple-Php-Sqlite-Login

require_once 'config.php';

if ($installed) {
    require_once 'utils.php';
    $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE name='users'");
    if ($tableCheck->fetch() === false){
        $installed = false;
    }
}


if ($installed) {
    echo "The website has already been installed. <a href='user.php'>Log in here.</a>";
} else {
    //echo "The file $db does not exist";
    
    if(isset($_POST['user']) && isset($_POST['password'])) {
        
        require_once 'utils.php';        
        $user = slugify($_POST['user']);
        $email = sql_clean($_POST['email']);
        $fullname = sql_clean($_POST['fullname']);
        $password = $_POST['password'];
        $password = hash("sha512", $password);
        
        $statements = [
        'DROP TABLE IF EXISTS "active_users";',
        'CREATE TABLE "active_users" (
            "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
            "user_id" integer(6,0) NOT NULL DEFAULT 1,
            "session_id" text(256,0),
            "hash" integer(256,0),
            "expires" integer(64,0),
            CONSTRAINT "user_id" FOREIGN KEY ("user_id") REFERENCES "users" ("ID") ON DELETE CASCADE ON UPDATE CASCADE
        );',
        'INSERT INTO "main".sqlite_sequence (name, seq) VALUES ("active_users", \'41\');',
        'DROP TABLE IF EXISTS "users";',
        'CREATE TABLE "users" (
            "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
            "user_name" text(128,0),
            "name" text(128,0) NOT NULL,
            "email" text(128,0) NOT NULL,
            "password" text(256,0),
            "user_type" integer(2,0) NOT NULL DEFAULT 1
        );',
        'INSERT INTO "main".sqlite_sequence (name, seq) VALUES ("users", \'1\');',
        'CREATE TABLE IF NOT EXISTS sections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug  VARCHAR (255) NOT NULL,
            title  VARCHAR (255),
            public INTEGER NOT NULL DEFAULT 0,
            parent INTEGER,
            deleted_on TEXT,
            CONSTRAINT "parent_section" FOREIGN KEY ("parent") REFERENCES "section" ("ID") ON DELETE CASCADE ON UPDATE CASCADE
        );',
        'INSERT INTO "main".sqlite_sequence (name, seq) VALUES ("sections", \'0\');',
        'CREATE TABLE IF NOT EXISTS templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug  VARCHAR (255) NOT NULL,
            title  VARCHAR (255),
            content  TEXT,
            deleted_on TEXT
        );',
        'CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug  VARCHAR (255) NOT NULL,
            title  VARCHAR (255),
            subtitle TEXT,
            credits TEXT,
            format  VARCHAR (10) NOT NULL DEFAULT "html",
            template_id INTEGER,
            section_id INTEGER NOT NULL DEFAULT 1,
            content  TEXT,
            public INTEGER NOT NULL DEFAULT 0,
            onlysource INTEGER NOT NULL DEFAULT 0,
            metadata TEXT,
            deleted_on TEXT,
            CONSTRAINT "section_id" FOREIGN KEY ("section_id") REFERENCES "section" ("ID") ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT "template_id" FOREIGN KEY ("template_id") REFERENCES "templates" ("ID") ON DELETE CASCADE ON UPDATE CASCADE
        );',
        'INSERT INTO users (user_name, name, email, password, user_type) VALUES ("'.$user.'", "'.$fullname.'","'.$email.'","'.$password.'", 1);',
        'INSERT INTO sections (slug, title, public) VALUES ("root","Root", 1);',
        'INSERT INTO pages (slug, title, public) VALUES ("index","My new website", 1);',
        'INSERT INTO pages (slug, title, public) VALUES ("maintenance","Website under maintenance", 1);'
        ];
                                
                                
        $dsn = "sqlite:$db";
        
        // create a PDO instance
        try {
            $pdo = new \PDO($dsn);
            
            // create tables
            foreach($statements as $statement){
                $pdo->exec($statement);
            }
            echo "DB created, <a href='index.php'>start creating your website</a>.";
        } catch(\PDOException $e) {
            echo $statement.' \n';
            echo 'ERROR: '.$e->getMessage();
        }
        
        //Writing temporary index page
        $maintenance = file_get_contents($basedir."maintenance/index.html");
        $updqry = $pdo->prepare('UPDATE pages SET content = ? WHERE slug = ?');
        $updqry->execute(array($maintenance, "maintenance"));
        file_put_contents($basedir."index.html", $maintenance);
        unlink($basedir."index.php");
    } else {
        include("header.php");    
        echo 'Please, create the first user: </br>';
        echo '<form id="register" method="post" name="register" >
        <label>Full name:</label>
        <input type="text" name="fullname" id="fullname"/> </br>
        <label>Email:</label>
        <input type="text" name="email" id="email"/> </br>
        <label>Username:</label>
        <input type="text" name="user" id="user"/> </br>
        <label>Password:</label>
        <input type="password" name="password" id="password"/> </br>
        <input type="submit" value="Create user" id="submit" />
        </form>';
        include("footer.php");
    }
    
}
?>

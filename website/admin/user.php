<?php
//Credits: https://github.com/handylulu/Simple-Php-Sqlite-Login

require("user_config.php");

if(isset($_GET['logout']) AND $_GET['logout']=='y'){ //get the logout variable from user.php?logout=y
	global $salt,$sess_time,$dbu;
	if(isLoggedIn()){
		$user = isLoggedIn();
		$user_id = $user['id'];
		$dbnu = new PDO('sqlite:'.$dbu);
		$dbnu->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$stmt = $dbnu->prepare('DELETE FROM active_users WHERE user_id = :user_id');
		$stmt->bindValue(":user_id",$user_id, PDO::PARAM_INT);
		$stmt->execute();
	}
}
if(isLoggedIn()){ // if is logged in, redirect to a script of choice and stop the script.
	$user=isLoggedIn();
	updateExpire($user['id']);
	#header('location:'.$header_redirect);
	echo "<a href='".$header_redirect."'>Get to the dashboard</a> <meta http-equiv='refresh' content='0; url=".$header_redirect."'>";
	exit();
}

if(isset($_POST['submitButton'])){
	if (empty($_POST['email'])) {
		die('Error: email is required.');
			}
	elseif (empty($_POST['password'])) {
		die('Error: password is required.');
		}
	$password=hash("sha512",$_POST['password']);
	//echo $_POST['email'].'<br>'.$password.'<br>';
	$dbnu = new PDO('sqlite:'.$dbu);
	$dbnu->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $dbnu->prepare('SELECT * FROM users WHERE email = :email AND password = :password LIMIT 1');
	$stmt->bindParam(":email",$_POST['email'], PDO::PARAM_STR);
	$stmt->bindParam(":password",$password, PDO::PARAM_STR);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if(!empty($row)) {
	$sessID = SQLite3::escapeString(session_id());
	$hash= SQLite3::escapeString(hash("sha512",$sessID.$salt.$_SERVER['HTTP_USER_AGENT']));
	$expires = time()+$sess_time;
	//$dbnu->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $dbnu->prepare('INSERT INTO active_users (user_id,session_id,hash,expires) VALUES (:user_id,:session_id, :hash, :expires)');
	$stmt->bindParam(":user_id",$row['id'], PDO::PARAM_INT);
	$stmt->bindParam(":session_id",$sessID, PDO::PARAM_STR);
	$stmt->bindParam(":hash",$hash, PDO::PARAM_STR);
	$stmt->bindParam(":expires",$expires, PDO::PARAM_INT);
	$stmt->execute();
	//header('Location:'.$_SERVER["PHP_SELF"]);
	echo "<a href='".$_SERVER["PHP_SELF"]."'>Get to the dashboard</a> <meta http-equiv='refresh' content='0; url=".$_SERVER["PHP_SELF"]."'>";
	exit(); 
	}
	else {echo '<h1>Error:Your login credentials are wrong</h1>';}
}
if (!isLoggedIn()) { //show login form if not logged in
?>
<!DOCTYPE HTML>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
    <meta name="generator" content="Hugo 0.84.0">
    <title>ZorbaSites Admin Login</title>

    <link rel="canonical" href="https://getbootstrap.com/docs/5.0/examples/sign-in/">



    <!-- Bootstrap core CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <!-- Favicons -->
<link rel="apple-touch-icon" href="/docs/5.0/assets/img/favicons/apple-touch-icon.png" sizes="180x180">
<link rel="icon" href="/docs/5.0/assets/img/favicons/favicon-32x32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/docs/5.0/assets/img/favicons/favicon-16x16.png" sizes="16x16" type="image/png">
<link rel="manifest" href="/docs/5.0/assets/img/favicons/manifest.json">
<link rel="mask-icon" href="/docs/5.0/assets/img/favicons/safari-pinned-tab.svg" color="#7952b3">
<link rel="icon" href="/docs/5.0/assets/img/favicons/favicon.ico">
<meta name="theme-color" content="#7952b3">

    <style>
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }
    </style>


    <!-- Custom styles for this template -->
    <link href="signin.css" rel="stylesheet">
  </head>
  <body class="text-center">

<main class="form-signin">
  <form id="login" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
    <img class="mb-4" src="assets/zorbasites.svg" alt="" width="72" height="57">
    <h1 class="h3 mb-3 fw-normal">Please sign in</h1>

    <div class="form-floating">
      <input type="email" class="form-control" name="email" id="email" placeholder="name@example.com">
      <label for="email">Email address</label>
    </div>
    <div class="form-floating">
      <input type="password" class="form-control" name="password" id="password" placeholder="Password">
      <label for="password">Password</label>
    </div>

    <button class="w-100 btn btn-lg btn-primary" type="submit" name="submitButton" id="submitButton">Sign in</button>
    <!--p class="mt-5 mb-3 text-muted">&copy; 2017–2021</p-->
  </form>
</main>


  </body>
</html>

<?php } ?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
    <meta name="generator" content="Hugo 0.84.0">
    <title>ZorbaSites Admin Dashboard</title>

    <link rel="canonical" href="https://getbootstrap.com/docs/5.0/examples/dashboard/">



    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <!-- script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script -->

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- TinyMCE -->
    <script src='assets/tinymce/js/tinymce/tinymce.min.js' referrerpolicy='origin'></script>
    <script type="text/javascript">
      tinymce.init({
        selector: 'textarea#tiny',
        license_key: 'gpl',
            plugins: [
            'code', 'a11ychecker','advlist','advcode','advtable','autolink','checklist','export',
            'lists','link','image','charmap','anchor','searchreplace','visualblocks',
            'powerpaste','fullscreen','formatpainter','insertdatetime','media','table','help','wordcount',
            'codesample', 'style'
            ],
            toolbar: 'code | undo redo | a11ycheck casechange blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify |' +
            'bullist numlist checklist outdent indent | removeformat | codesample table help'
      })
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/codemirror.min.css" /><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/codemirror.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/mode/javascript/javascript.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
         <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    
    <link rel="stylesheet" href="jsontree.css" />
    
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
    <link href="dashboard.css" rel="stylesheet">
  </head>

  <body>
  
  
  
  <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
      <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="./">ZorbaSite</a>
      
      <div class="d-flex justify-content-between">
          
          <ul class="nav col-12 col-lg-auto me-lg-auto mb-2 justify-content-center mb-md-0">
              <li><a href="./" class="nav-link px-2 text-secondary">Dashboard</a></li>
              <li><a href="upload.php" class="nav-link px-2 text-white">Manage files</a></li>
              <li><form action="index.php" method="POST"><input type="hidden" name="render" id="pagerender" value="now"/><button type="submit" class="btn btn-success me-2">Render website</button></form></li>
          </ul>
          
          <!--form class="col-12 col-lg-auto mb-3 mb-lg-0 me-lg-3">
          <input type="search" class="form-control form-control-dark" placeholder="Search..." aria-label="Search">
          </form-->
          
          <div class="text-end">
              <a href="user.php?logout=y"><button type="button" class="btn btn-outline-light me-2">Logout</button></a>
          </div >
      </div>
  </header>
  
  <div class="container-fluid">
      <div class="row">
          <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
              <div class="position-sticky pt-3">
                  <?php
                  $result = $pdo->prepare('SELECT * FROM sections WHERE deleted_on IS NULL AND public = 1 ORDER BY id');
                  $result->execute();
                  $barsections = $result->fetchAll();
                  foreach ($barsections as $sec) {
                  if ($sec['slug'] != 'root') {
                  $secpath = get_sections_path($sec['id']);
                  $secpath_str = '';
                  foreach ($secpath as $sc) {
                  $secpath_str .= '/'.$sc;
                  }
                  $secpath_str = str_replace('//','/',$secpath_str);
                  echo '<h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                  <span>'.$secpath_str.'</span>
                  <a class="link-secondary" href="#" aria-label="Add a new report">
                  <span data-feather="plus-circle"></span>
                  </a>
                  </h6>';
                  }
                  echo '<ul class="nav flex-column mb-2">';
                  $result = $pdo->prepare('SELECT pages.id, pages.title, pages.slug, sections.slug as section_slug, sections.title as section_title, sections.public as section_public FROM pages LEFT JOIN sections on pages.section_id = sections.id WHERE sections.id = ? ORDER BY pages.id');
                  $result->execute(array($sec['id']));
                  $barpages = $result->fetchAll();
                  foreach($barpages as $pg) {
                  echo '<li class="nav-item">
                  <a class="nav-link" href="edit_page.php?id='.$pg['id'].'">
                  <span data-feather="file-text"></span>'.$pg['title'].'</a>
                  </li>';
                  }
                  echo '</ul>';
                  }
                  ?>
                  
                  </div>
                  
                  <!-- https://github.com/toughengineer/simple-json-tree-view -->
                  <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                      <span>Files</span>
                      <a class="link-secondary" href="#" aria-label="Add a new report">
                          <span data-feather="plus-circle"></span>
                      </a>
                  </h6>
                  <p></p>
                  <script type="text/javascript">
                      function showJsonData() {
                      var tree = createJsonTreeDom(json, true);
                      var holder = document.getElementById('test');
                      holder.removeChild(holder.querySelector('*'));
                      holder.appendChild(tree);
                      }
                  </script>
                  <div>
                      <!-- button onclick="for (var e of document.querySelectorAll('#test li.folder')) e.classList.remove('folded')">Expand all</button -->
                      <!-- button onclick="for (var e of document.querySelectorAll('#test li.folder')) e.classList.add('folded')">Collapse all</button -->
                      <!--button onclick="for (var e of document.querySelectorAll('#test .tree > ul > li.folder')) e.classList.remove('folded')">Expand level 1</button -->
                      <!--button onclick="for (var e of document.querySelectorAll('#test .tree > ul > li.folder')) e.classList.add('folded')">Collapse level 1</button -->
                  </div>
                  <div>
                      <label for="filter">Filter:</label>
                      <input id="filter" type="text" placeholder="enter regex"
                             oninput="filter(this.value)"
                             onkeypress="if (event.key == 'Enter') exapandFilteredItems(document.getElementById('test'))" />
                      <!-- button onclick="exapandFilteredItems(document.getElementById('test'))">Expand filtered items</button -->
                      <span id="regexpError" style="color: darkred;"></span>
                      <script type="text/javascript">
                          function filter(pattern) {
                          var errorMsgElement = document.getElementById('regexpError');
                          errorMsgElement.textContent = '';
                          filterItems(pattern, document.getElementById('test'), errorMsg => errorMsgElement.textContent = errorMsg);
                          }
                      </script>
                  </div>
                  <div class="container">
                      <div id="test" class="container horizontal-scrollable" style="margin: 1em; border: 1px solid gray;"></div>
                  </div>
                  
                  <script type="text/javascript" src="jsontree.js"></script>
                  <script type="text/javascript">
                      var json = <?php echo json_encode(get_upload_dirs('', '/upload/')); ?>;
                      
                      var tree = createJsonTreeDom(json, true);
                      document.getElementById('test').appendChild(tree);
                  </script>
                  
              </nav>
              
              <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                      
                      
                      <div class="container">
                          
                          

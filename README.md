# ZorbaSites

Simple CMS, built in PHP, to create and render static HTML websites.

The first review of ZorbaSites was released in 2009, basically as a web file editor based on TinyMCE. Now, in 2026, it has been completely rewritten to support Bootstrap templates and both HTML and MarkDown.

ZorbaSites philosohy is that static websites should not need real time rendering: the CMS is only used to draft templates and pages but then, when the website is ready for publishing, pure static HTML is generated. This allows for maximum efficiency and safety, since there's no chance for the end user to interact with PHP or any database, and it's also a lot easier to replicate using cloud caches (e.g.: Cloudflare/Cloudfront).

## Installation
### Requirements
ZorbaSites only needs a web hosting with PHP and SQLite support, which is what most free web hosting services offer. It's important to make sure the htaccess file is enabled, and the **admin/zorbasites.db** file is not publicly accessible. Also it's important to replace the **salt** string in **admin/config.php** for each website, just to make sure password hashing is safe. 

### Install on a FTP accessible web hosting
Just copy the content of **website** folder into your web hosting space: it can be on the root directory or in a subfolder. Then open a browser on the **/admin/install.php** file to start creating you website. You'll have to create an admin user and set a few info about your website.

### Install on a docker host
If you want to install ZorbaSites on a docker host, just copy the entire repository somewhere and enter the **docker** folder. Then edit the docker-compose.yaml file according to your needs and run 

```
docker compose up -d
```
Then proceed to reach the **/admin/install.php** page with a browser to start configuring your website.

## Security
ZorbaSites renders a completely static website, and the **admin** folder is actually needed only for editing. This means it's possibile to add some lines like these

```
order deny,allow
allow from YOURIPADDRESS
deny from all
```
inside the .htaccess file in **admin** to make sure only safe IP addresses can reach this. It's also possible to just copy all the other folders on more webservers, to have redundancy (using a load balancer or round robin) or caching.

In any case, the .htaccess file should ALWAYS contain these lines:

```
<FilesMatch "\.db">
        Require all denied
</FilesMatch>
```
to make sure the ZorbaSites database file is never accessible.


## Usage

### Theme
Themes can be based on anything, but the recommended framework is Bootstrap. A website can have only one theme, and its files should be uploaded in the **theme** folder. Usually, a theme comes with demo pages: they should be placed right inside the **theme** folder. When building a page or a template it's possibile to copy the content from one of the demo pages.
When the website gets rendered, URLs pointing to files inside the **theme** folder will be automatically fixed with the correct path (prepending "/theme/").

### Pages and sections
Pages are organized in sections: each section is going to be a folder, and each page is going to be a subfolder in its own section. If a page has the same name and parent section of an existing section, that page is going to be that section home page.

There are two protected pages that can be made not public, but cannot be deleted: the root index and the **/maintenance/** page.

Pages can be written both in HTML or in MarkDown format. Pages written in MarkDown will automatically be translated in HTML using standard tags that should work with any Bootstrap based CSS. Pages written in HTML can be modified with a WYSIWYG editor based on TinyMCE, or directly as source code. The second option is recommended when there's something more that simple page with text and pictures (for example videos or something interactive, maybe based on Javascript).

#### Rendering and cleaning
It's possible to render only one page, or the entire website at once. By default, the rendering function does not clean up deleted or unpublished pages: this is done by a separate function. It's a design choice to make sure things don't get deleted by mistake.

### Including variables
Pages and templates can include some variables, to make it easier to include some data: for example the variable

```
{{ page.title }}
```
will be replaced with the title of the page.
The full list of variables is:

- page.title
- page.slug
- page.subtitle
- page.credits
- page.path
- section.title

All these variables are related to the current page and its section.

There's also another variable, that will be replaced with the path of the page with the specified id: 

```
{{ pagepath: id }}
```
This is useful to make a link that always points to the same page, even if it gets moved. A similar variable provides the current title of the page with the specified id:

```
{{ pagetitle: id }}
```
This can also be useful for links and menubars.

### Templates
Templates must be written in HTML, and can contain both page variables and the special variable **content**. Templates can also be included in other templates. For example, this could be the **header** template:

```
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-light" id="mainNav">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand" href="{{ pagepath: 1 }}">MyWebsite</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu
                    <i class="fas fa-bars"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav ms-auto py-4 py-lg-0">
                        <li class="nav-item"><a class="nav-link px-lg-3 py-3 py-lg-4" href="{{ pagepath: 4 }}">MyPage</a></li>
                        <li class="nav-item"><a class="nav-link px-lg-3 py-3 py-lg-4" href="https://github.com/">External link</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <!-- Page Header-->
        <header class="masthead" style="background-image: url('/upload/2026/02/head.jpg')">
            <div class="container position-relative px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-md-10 col-lg-8 col-xl-7">
                        <div class="post-heading">
                            <h1>{{ page.title }}</h1>
                            <h2 class="subheading">{{ page.subtitle }}</h2>
                            <span class="meta">
                              {{ page.credits }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </header>
```
This **header** template could then be included inside a **post** template, so every post will inherit. Of course it would be possible to use only a single template, but using a separate template for the header makes it possible to use the same header also in other templates (for example in a "photogallery" template).
The **post** template could be something like this:

```
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>{{ page.title }}</title>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic,700italic" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800" rel="stylesheet" type="text/css" />
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body>
        {{ template: header }}
        <!-- Post Content-->
        <article class="mb-4">
            <div class="container px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-md-10 col-lg-8 col-xl-7">
                      {{ content }}
                    </div>
                </div>
            </div>
        </article>
        <!-- Footer-->
        <footer class="border-top">
            <div class="container px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-md-10 col-lg-8 col-xl-7">
                        <div class="small text-center text-muted fst-italic">Published under Creative Commons BY 3.0</div>
                    </div>
                </div>
            </div>
        </footer>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
    </body>
</html>
```
This way, all pages related to the **post** template will have their content inserted where the {{ content }} variable is placed. Please, take note that pages can be included specifying both the id or the slug, but it's recommended to use to id since the slug might not be unique.

Please, take note that in this example the **css/styles.css** file should be found inside the **theme* folder (so its full URL should be /theme/css/styles.css).

# Credits
To speed up coding ZorbaSites, a few other projects and snippets have been used:

- https://github.com/handylulu/Simple-Php-Sqlite-Login
- https://gist.github.com/daraul/7057c25495dc0284d1c4e77997d25938
- https://www.tiny.cloud/solutions/wysiwyg-bootstrap-rich-text-editor/
- https://github.com/Ionaru/easy-markdown-editor
- https://gist.github.com/juliyvchirkov/8f325f9ac534fe736b504b93a1a8b2ce
- https://www.linkedin.com/pulse/write-simple-php-script-convert-md-html-callan-milne-bqwuc
- https://github.com/toughengineer/simple-json-tree-view

Many thanks to all authors for sharing their code.

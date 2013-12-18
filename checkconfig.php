<?php
/**
 * COPS (Calibre OPDS PHP Server) Configuration check
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 *
 */

    require_once ("config.php");
    require_once ("base.php");

    header ("Content-Type:text/html; charset=UTF-8");

    $err = getURLParam ("err", -1);
    $full = getURLParam ("full");
    $error = NULL;
    switch ($err) {
        case 1 :
            $error = "Database error";
            break;
    }
// Move doctype and html to below the php block (can't send header otherwise)
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>COPS Configuration Check</title>
    <link rel="stylesheet" type="text/css" href="<?php echo getUrlWithVersion(getCurrentCss ()) ?>" media="screen" />
<style type="text/css">
/* make sure there are no surprises from any custom(ised) stylesheet */
body {
    font-size:0.85em;
}
h2 {
    color:rgb(32,32,32); 
}
.container {
    margin-left:auto;
    margin-right:auto;
}
.frontpage._1col {
/* checkconfig.php don't like fluid layout */
    display:block;
    width:auto;
}
li,
.frontpage._1col li {
/* checkconfig.php don't like float */
    float:none;
    display:block;
	opacity:0.8;
}
.frontpage._1col .configval {
	padding:0 3px 0px 3px;
    border:1px solid gray;
    background-color: rgb(192, 192, 192);
/* looks better than small-caps yet does nearly the same */
	font-size:small;
	text-transform:uppercase;
}
.frontpage._1col .setting {
    font-weight:bold;
/*	color:rgb(255,255,255);*/
}
a,
a:hover,
a:focus,
a:active{color:rgb(0,0,255); text-decoration:none; font-weight:bold; outline:none; }
/* :visited only allows color change */
a:visited{ color:rgb(0,0,255); }
.error {
    font-weight:bold;
	color:red;
    text-align:center;
}
.frontpage a { display:inline; }
</style>
</head>
<body>
<div class="container">
    <header>
    <div class="header">
        <div class="headcenter">
            <div><h1>COPS Configuration Check</h1></div>
        </div>
    </div>
    </header>
    <div id="content" style="display: none;"></div>
    <section>
        <?php
        if (!is_null ($error)) {
        ?>
        <div class="headcenter">
        <div>
            <h2>You've been redirected because COPS is not configured properly</h2>
            <h2 class="error"><?php echo $error ?></h2>
        </div>
        </div>
        <?php
        }
        ?>
        <article class="frontpage _1col">
            <h2>Check if GD is properly installed and loaded</h2>
            <h4>
            <?php
            if (extension_loaded('gd') && function_exists('gd_info')) {
                echo "GD OK";
            } else {
                echo "Please install the php5-gd extension and make sure it's enabled";
            }
            ?>
            </h4>
        </article>
        <article class="frontpage _1col">
            <h2>Check if Sqlite is properly installed and loaded</h2>
            <h4>
            <?php
            if (extension_loaded('pdo_sqlite')) {
                echo "SQLite OK";
            } else {
                echo "Please install the php5-sqlite extension and make sure it's enabled";
            }
            ?>
            </h4>
        </article>
        <article class="frontpage _1col">
            <h2>Check if libxml is properly installed and loaded</h2>
            <h4>
            <?php
            if (extension_loaded('libxml')) {
                echo "LibXML OK";
            } else {
                echo "Please make sure libxml is enabled";
            }
            ?>
            </h4>
        </article>
        <article class="frontpage _1col">
            <h2>Check if the rendering will be done on client side or server side</h2>
            <h4>
            <?php
            if (useServerSideRendering ()) {
                echo "Server side rendering";
            } else {
                echo "Client side rendering";
            }
            ?>
            </h4>
        </article>
<?php
$i = 0;
foreach (Base::getDbList () as $name => $database) {
?><hr />
        <article class="frontpage _1col">
            <h2>Check if Calibre database path <span class="configval"><?php echo "{$database}"; ?></span> is not an URL</h2>
            <h4>
            <?php
            if (!preg_match ("#^http#", $database)) {
                echo "{$database} OK";
            } else {
                echo "Calibre path has to be local (no URL allowed)";
            }
            ?>
            </h4>
        </article>
        <article class="frontpage _1col">
            <h2>Check if Calibre database <span class="configval"><?php echo "{$name}"; ?></span> exists and is readable</h2>
            <?php
            if (is_readable (Base::getDbFileName ($i))) {
                echo "<h4>";
                echo "{$name} OK";
                echo "</h4>";
            } else {
                echo "<div class='error'>{$name} File " . Base::getDbFileName ($i) . " not found, Please check</div>
<ul>
<li>Value of <span class='configval'>\$config['calibre_directory']</span> in config_local.php</li>
<li>Value of <a class='configval' href='http://php.net/manual/en/ini.core.php#ini.open-basedir'>open_basedir</a> in your php.ini</li>
<li>The access rights of the Calibre Database</li>
<li>Synology users please read <a href='https://github.com/seblucas/cops/wiki/Howto---Synology'>this</a></li>
</ul>";
            }
            ?>
        </article>
   <?php if (is_readable (Base::getDbFileName ($i))) { ?>
        <article class="frontpage _1col">
            <h2>Check if Calibre database <span class="configval"><?php echo "{$name}"; ?></span> can be opened with PHP</h2>
            <h4>
            <?php
            try {
                $db = new PDO('sqlite:'. Base::getDbFileName ($i));
                echo "{$name} OK";
            } catch (Exception $e) {
                echo "{$name} If the file is readable, check your php configuration. Exception detail : " . $e;
            }
            ?>
            </h4>
        </article>
        <article class="frontpage _1col">
            <h2>Check if Calibre database <span class="configval"><?php echo "{$name}"; ?></span> contains at least some of the needed tables</h2>
            <h4>
            <?php
            try {
                $db = new PDO('sqlite:'. Base::getDbFileName ($i));
                $count = $db->query("select count(*) FROM sqlite_master WHERE type='table' AND name in ('books', 'authors', 'tags', 'series')")->fetchColumn();
                if ($count == 4) {
                    echo "{$name} OK";
                } else {
                    echo "{$name} Not all Calibre tables were found. Are you sure you're using the correct database.";
                }
            } catch (Exception $e) {
                echo "{$name} If the file is readable, check your php configuration. Exception detail : " . $e;
            }
            ?>
            </h4>
        </article>
        <?php if ($full) { ?>
        <article class="frontpage _1col">
            <h2>Check if all Calibre books are found</h2>
            <h4>
            <?php
            try {
                $db = new PDO('sqlite:'. Base::getDbFileName ($i));
                $result = $db->prepare("select books.path || '/' || data.name || '.' || lower (format) as fullpath from data join books on data.book = books.id");
                $result->execute ();
                while ($post = $result->fetchObject ())
                {
                    if (!is_file (Base::getDbDirectory ($i) . $post->fullpath)) {
                        echo "<p>" . Base::getDbDirectory ($i) . $post->fullpath . "</p>";
                    }
                }
            } catch (Exception $e) {
                echo "{$name} If the file is readable, check your php configuration. Exception detail : " . $e;
            }
            ?>
            </h4>
        </article>
        <?php } ?>
   <?php } ?>
<?php $i++; } ?>
    </section>
    <footer>
    <a class="footleft" href="index.php?page=19"><div title="" class="hicon hicon32"><i class="icon-wrench icon-2x"></i></div></a><!-- icon-cogs -->
    <a class="footright" class="fancyabout" href="index.php?page=16"><div title="" class="hicon hicon32"><i class="icon-info-sign icon-2x"></i></div></a>
	</footer>
</div>
</body>
</html>

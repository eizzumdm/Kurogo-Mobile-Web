<?php
/**
  * This script handles all incoming requests
  * @package Core
  */

/**
  * The initialize library sets up the environment
  */
require_once realpath(dirname(__FILE__).'/../lib/initialize.php');

//
// Configure web application
// modifies $path for us to strip prefix and device
//

$path = isset($_GET['_path']) ? $_GET['_path'] : '';

Initialize($path); 

function _phpFile($_file) {
    if ($file = realpath_exists($_file)) {
        require_once $file;
        exit;
    }
    
    _404();
}

function _outputFile($_file) {
    if ($file = realpath_exists($_file)) {
        CacheHeaders($file);
        header('Content-type: '.mime_type($file));
        readfile($file);
        exit;
    }

    _404();
}

function _outputSiteFile($matches) {
  _outputFile(SITE_DIR.'/'.$matches[1].'/'.$matches[2]);
}

function _outputTypeFile($matches) { 
  $file = $matches[3];

  $platform = $GLOBALS['deviceClassifier']->getPlatform();
  $pagetype = $GLOBALS['deviceClassifier']->getPagetype();
  
  $testDirs = array(
    THEME_DIR.'/'.$matches[1].$matches[2],
    SITE_DIR.'/'.$matches[1].$matches[2],
    TEMPLATES_DIR.'/'.$matches[1].$matches[2],
  );
  
  $testFiles = array(
    "$pagetype-$platform/$file",
    "$pagetype/$file",
    "$file",
  );
  
  foreach ($testDirs as $dir) {
    foreach ($testFiles as $file) {
      if ($file = realpath_exists("$dir/$file")) {
          _outputFile($file);
      }
    }
  }

  _404();
}

function _outputImageLoaderFile($matches) {
  $fullPath = ImageLoader::load($matches[1]);
  
  if ($fullPath) {
    CacheHeaders($fullPath);    
    header('Content-type: '.mime_type($fullPath));
    echo file_get_contents($fullPath);
    exit;
  }

  _404();
}

function _outputAPICall() {
  require_once realpath(LIB_DIR.'/PageViews.php');
  
  if (isset($_REQUEST['module']) && $_REQUEST['module']) {
    $id = $_REQUEST['module'];
    $api = realpath_exists(LIB_DIR."/api/$id.php");
    
    if ($id == 'shuttles') {
      $id = 'transit';
      $api = realpath_exists(LIB_DIR."/api/$id.php");
    }
    
    if ($api) {
      PageViews::log_api($id, $GLOBALS['deviceClassifier']->getPlatform());
      
      $GLOBALS['siteConfig']->loadAPIFile($id, true, true);
      require_once($api);
      exit;
    }
  }
  
  _404();
}

//
// Handle page request
//

$url_patterns = array(
  array(
    'pattern' => ';^.*favicon.ico$;', 
    'func'    => '_outputFile',
    'params'  => array(THEME_DIR.'/common/images/favicon.ico'),
  ),
  array(
    'pattern' => ';^.*ga.php$;',
    'func'    => '_phpFile',
    'params'  => array(LIB_DIR.'/ga.php'),
  ),
  array(
    'pattern' => ';^.*(modules|common)(/.*images)/(.*)$;',
    'func'    => '_outputTypeFile',
  ),
  array(
    'pattern' => ';^.*'.ImageLoader::imageDir().'/(.+)$;',
    'func'    => '_outputImageLoaderFile',
  ),
  array(
    'pattern' => ';^.*(media)(/.*)$;',
    'func'    => '_outputSiteFile',
  ),
  array(
    'pattern' => ';^.*api/$;',
    'func'    => '_outputAPICall',
  ),
);
 
// try the url patterns. Run the path through each pattern testing for a match
foreach ($url_patterns as $pattern_data) {
    if (preg_match($pattern_data['pattern'], $path, $matches)) {
        $params = isset($pattern_data['params']) ? $pattern_data['params'] : array($matches);
        call_user_func_array($pattern_data['func'], $params);
    }
}

// No pattern matches. Attempt to load a module

require_once realpath(LIB_DIR.'/Module.php');
require_once realpath(LIB_DIR.'/PageViews.php');

$id = 'home';
$page = 'index';

$args = array_merge($_GET, $_POST);
unset($args['_path']);

// undo magic_quotes_gpc if set. It really shouldn't be. Stop using it.
if (get_magic_quotes_gpc()) {
    function deepStripSlashes($v) {
      return is_array($v) ? array_map('deepStripSlashes', $v) : stripslashes($v);
    }
    $args = deepStripslashes($args);
}

/* if the path is "empty" route to the default page. Will search the config file in order:
 * DEFAULT-PAGETYPE-PLATFORM
 * DEFAULT-PAGETYPE
 * DEFAULT
 * home is the default
 */
if (!strlen($path) || $path == '/') {
  $platform = strtoupper($GLOBALS['deviceClassifier']->getPlatform());
  $pagetype = strtoupper($GLOBALS['deviceClassifier']->getPagetype());

  if (!$url = $GLOBALS['siteConfig']->getVar("DEFAULT-{$pagetype}-{$platform}", Config::SUPRESS_ERRORS)) {
    if (!$url = $GLOBALS['siteConfig']->getVar("DEFAULT-{$pagetype}", Config::SUPRESS_ERRORS)) {
      if (!$url = $GLOBALS['siteConfig']->getVar("DEFAULT", Config::SUPRESS_ERRORS)) {
        $url = 'home';
      }
    }
  } 
  
  if (!preg_match("/^http/", $url)) {
    $url = URL_BASE . $url . "/";
  }
  
  header("Location: $url");
  exit;
} 

$parts = explode('/', ltrim($path, '/'), 2);
$id = $parts[0];

/* see if there's a redirect for this path */
if ($url_redirects = $GLOBALS['siteConfig']->getSection('urls', ConfigFile::SUPRESS_ERRORS)) {
  if (array_key_exists($id, $url_redirects)) {
    if (preg_match("#^http(s)?://#", $url_redirects[$id])) {
       $url = $url_redirects[$id];
    } else {
      $parts[0] = $url_redirects[$id];
      $url = URL_BASE . implode("/", $parts);
    }
    header("Location: " . $url);
    exit;
  }
}

// find the page part
if (isset($parts[1])) {
  if (strlen($parts[1])) {
    $page = basename($parts[1], '.php');
  }
  
} else {
  // redirect with trailing slash for completeness
  header("Location: ./$id/");
  exit;
}

/* log this page view */
PageViews::increment($id, $GLOBALS['deviceClassifier']->getPlatform());

$module = Module::factory($id, $page, $args);
$module->displayPage();
exit;

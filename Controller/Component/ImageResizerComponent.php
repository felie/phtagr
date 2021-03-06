<?php
/**
 * PHP versions 5
 *
 * phTagr : Tag, Browse, and Share Your Photos.
 * Copyright 2006-2012, Sebastian Felis (sebastian@phtagr.org)
 *
 * Licensed under The GPL-2.0 License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2012, Sebastian Felis (sebastian@phtagr.org)
 * @link          http://www.phtagr.org phTagr
 * @package       Phtagr
 * @since         phTagr 2.2b3
 * @license       GPL-2.0 (http://www.opensource.org/licenses/GPL-2.0)
 */
if (!class_exists('phpThumb') && !App::import('Vendor', 'phpthumb', array('file' => 'phpthumb' .DS . 'phpthumb.class.php'))) {
  debug("Please install phpthumb properly");
}

class ImageResizerComponent extends Component {

  var $controller = null;
  var $components = array('Command');
  var $_semaphoreId = false;

  function initialize(&$controller) {
    $this->controller =& $controller;
    // allow only to converts at the same time to reduce system load
    if (function_exists('sem_get')) {
      $this->_semaphoreId = sem_get(4711, 2);
    }
  }

  /** Resize an image
    @param src Source image filename
    @param dst Destination image filename
    @param options Options
      - height Maximum height of the resized image. Default is 220.
      - quality Quality of the resized image. Default is 85
      - rotation Rotation in degree. Default i s0
      - square Square the image. Default is false. If set, only width is considered.
      - clearMetaData Clear all meta data. Default is true */
  function resize($src, $dst, $options = array()) {
    $options = am(array(
      'size' => 220,
      'quality' => 85,
      'rotation' => 0,
      'square' => false,
      'clearMetaData' => true
      ), $options);

    if (!is_readable($src)) {
      Logger::err("Could not read source $src");
      return false;
    }
    if (!is_writeable(dirname($dst))) {
      Logger::err("Could not write to path ".dirname($dst));
      return false;
    }
    if (!isset($options['width']) || !isset($options['height'])) {
      $size = getimagesize($src);
      $options['width'] = $size[0];
      $options['height'] = $size[1];
    }

    $phpThumb = new phpThumb();

    $phpThumb->src = $src;
    $phpThumb->w = $options['size'];
    $phpThumb->h = $options['size'];
    $phpThumb->q = $options['quality'];
    $phpThumb->ra = $options['rotation'];

    $phpThumb->config_imagemagick_path = $this->controller->getOption('bin.convert', 'convert');
    $phpThumb->config_prefer_imagemagick = true;
    $phpThumb->config_imagemagick_use_thumbnail = false;
    $phpThumb->config_output_format = 'jpg';
    $phpThumb->config_error_die_on_error = true;
    $phpThumb->config_document_root = '';
    $phpThumb->config_temp_directory = APP . 'tmp';
    $phpThumb->config_allow_src_above_docroot = true;

    $phpThumb->config_cache_directory = dirname($dst);
    $phpThumb->config_cache_disable_warning = false;
    $phpThumb->cache_filename = $dst;

    if ($options['square'] && $options['height'] > 0) {
      $this->_getSquareOption(&$phpThumb, &$options);
    }

    $t0 = microtime(true);
    if ($this->_semaphoreId) {
      sem_acquire($this->_semaphoreId);
    }
    $t1 = microtime(true);
    $result = $phpThumb->GenerateThumbnail();
    $t2 = microtime(true);
    if ($this->_semaphoreId) {
      sem_release($this->_semaphoreId);
    }
    if ($result) {
      Logger::debug("Render {$options['size']}x{$options['size']} image in ".round($t2-$t1, 4)."ms to '{$phpThumb->cache_filename}'");
      $phpThumb->RenderToFile($phpThumb->cache_filename);
    } else {
      Logger::err("Could not generate thumbnail: ".$phpThumb->error);
      Logger::err($phpThumb->debugmessages);
      die('Failed: '.$phpThumb->error);
    }

    if ($options['clearMetaData']) {
      $this->clearMetaData($dst);
    }
    return true;
  }

  /* Set phpThumb options for square image
    @param phpThumb phpThumb object (reference)
    @param options Array of options */
  function _getSquareOption($phpThumb, $options) {
    $width = $options['width'];
    $height = $options['height'];
    if ($width < $height) {
      $ratio = ($width / $height);
      $size = $options['size'] / $ratio;
      $phpThumb->sx = 0;
      $phpThumb->sy = intval(($size - $options['size']) / 2);
    } else {
      $ratio = ($height / $width);
      $size = $options['size'] / $ratio;
      $phpThumb->sx = intval(($size - $options['size']) / 2);
      $phpThumb->sy = 0;
    }

    if ($phpThumb->ra == 90 || $phpThumb->ra == 270) {
      $tmp = $phpThumb->sx;
      $phpThumb->sx = $phpThumb->sy;
      $phpThumb->sy = $tmp;
    }

    $phpThumb->sw = $options['size'];
    $phpThumb->sh = $options['size'];

    $phpThumb->w = $size;
    $phpThumb->h = $size;

    //Logger::debug(sprintf("square: %dx%d %dx%d",
    //  $phpThumb->sx, $phpThumb->sy,
    //  $phpThumb->sw, $phpThumb->sh));
  }

  /** Clear image metadata from a file
    @param filename Filename to file to clean */
  function clearMetaData($filename) {
    if (!file_exists($filename)) {
      Logger::err("Filename '$filename' does not exists");
      return false;
    }
    if (!is_writeable($filename)) {
      Logger::err("Filename '$filename' is not writeable");
      return false;
    }

    $bin = $this->controller->getOption('bin.exiftool', 'exiftool');
    if ($this->Command->run($bin, array('-all=', '-overwrite_original', $filename)) != 0) {
      Logger::err("Cleaning of meta data of file '$filename' failed");
      return false;
    }
    Logger::debug("Cleaned meta data of '$filename'");
    return true;
  }

}

?>

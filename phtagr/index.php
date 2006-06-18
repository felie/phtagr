<?php
// index.php for phtagr
//
// This file includes the real content over $phtagr_prefix/main.php

if (file_exists ('config.php'))
  include 'config.php';

if (!isset ($phtagr_prefix))
  $phtagr_prefix='./phtagr';

if (!isset ($phtagr_data_directory))
  $phtagr_data_directory=getcwd().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR;

if (!isset ($phtagr_url_prefix))
{
  $phtagr_url_prefix=substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], DIRECTORY_SEPARATOR));
}

include "$phtagr_prefix/main.php";

?>

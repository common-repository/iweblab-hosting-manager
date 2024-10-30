<?php
namespace NMOD\IweblabHostingManager;

/**
**  Plugin Name: IWEBLAB Hosting Manager
**  Plugin URI: 
**  Description: Gestione di alcune funzionalità avanzate degli hosting di IWEBLAB.it
**  Author: Nicola Modugno
**  Author URI: https://nicolamodugno.it
**  Version: 0.1.2
**  Requires at least: 5.0.0
**  License: GPL2
*/


if ( ! defined( 'ABSPATH' ) ) exit;




const IWEBLAB_SPIDER_LIST = 'https://support.iweblab.it/plugin/spiders_list.csv';
const PLUGIN_VERSION      = '0.1.2';




include_once 'plugin_settings.php';
if ( is_admin() )
  include_once 'plugin_admin.php';
<?php

/**
 * Plugin Name: CDN Localizer
 * Description: Detects .js and .css files using CDNs and provides an option to create local copies.
 * Version: 1.0
 * Author: Maru-Yasa
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Include the Composer autoload file.
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Your plugin code starts here.
use MaruYasa\WpCdnLocalizer\CDNLocalizer;

new CDNLocalizer();

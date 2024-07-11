<?php

namespace MaruYasa\WpCdnLocalizer;

use voku\helper\HtmlDomParser;

class CDNLocalizer
{
    private $cdn_mappings;
    private $whitelist = [];
    private $page_slug = 'cdn-localizer';

    private $cdn_local_dir = 'wp-cdn-localizer';

    private $tabs = [
        'cdn_resources' => 'Detected CDN Resources',
        'local_resources' => 'Detected Local Resources',
        'settings' => 'Settings'
    ];

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('init', array($this, 'init_cdn_replacement'));

        $this->cdn_mappings = $this->get_option('mappings', array());
        $this->whitelist = $this->get_option('whitelist', array());
    }

    private function get_option($key, $default = null)
    {
        return get_option('cdn_localizer_' . $key, $default);
    }

    public function add_admin_menu()
    {
        add_action('admin_enqueue_scripts', array($this, 'add_cdn_localizer_styles'));
        add_menu_page('CDN Localizer', 'CDN Localizer', 'manage_options', $this->page_slug, array($this, 'cdn_localizer_admin_page'), 'dashicons-rest-api');
    }

    public function add_cdn_localizer_styles()
    {
        wp_enqueue_style('cdn_localizer_styles', plugin_dir_url(__FILE__) . '../assets/css/style.css');
    }

    private function page_cdn_resources()
    {
        $cdn_resources = $this->detect_cdn_resources()['cdn_resources'];
        $cdn_mappings = $this->cdn_mappings;

        ?>
        <form method="post">
            <p>
                <?php wp_nonce_field('cdn_localizer_save', 'cdn_localizer_nonce'); ?>
                <input type="submit" name="cdn_localizer_save" class="button button-primary" value="Localize Selected Resources">
            </p>
            <table class="widefat wp-cdn-localizer-table">
                <thead>
                    <tr>
                        <th>Origin</th>
                        <th>Resource</th>
                        <th>Localize Url</th>
                        <th>Type</th>
                        <th>Localize</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cdn_resources as $resource => $meta) : ?>
                    <?php
                    $localize = isset($cdn_mappings[$resource]) ? $cdn_mappings[$resource] : '';
                    $isLocalized = isset($cdn_mappings[$resource]);
                    ?>
                    <tr>
                        <td><?php echo esc_html($meta['origin']); ?></td>
                        <td><?php echo esc_url($resource); ?></td>
                        <td><?php echo esc_url($localize); ?></td>
                        <td><?php echo esc_html($meta['type']); ?></td>
                        <td style="display: flex; justify-content: center;">
                            <input type="checkbox" name="localize[]" value="<?php echo esc_attr($resource); ?>" <?php if ($isLocalized) : ?>checked<?php endif; ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <?php
    }

    private function page_local_resources()
    {
        $local_resources = $this->detect_cdn_resources()['local_resources'];
        ?>
        <table class="widefat wp-cdn-localizer-table">
            <thead>
            <tr>
                <th>Origin</th>
                <th>Resource</th>
                <th>Type</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($local_resources as $resource => $meta) : ?>
                <tr>
                    <td><?php echo esc_html($meta['origin']); ?></td>
                    <td><?php echo esc_url($resource); ?></td>
                    <td><?php echo esc_html($meta['type']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function page_settings()
    {
        if (isset($_POST['cdn_localizer_save_whitelist'])) {
            check_admin_referer('cdn_localizer_save_whitelist', 'cdn_localizer_nonce_whitelist');
            $whitelist_input = isset($_POST['cdn_localizer_whitelist']) ? sanitize_text_field($_POST['cdn_localizer_whitelist']) : '';
            $this->whitelist = array_map('trim', explode("\n", $whitelist_input));
            update_option('cdn_localizer_whitelist', $this->whitelist);
        }

        ?>
        <form method="post">
            <h2>Whitelist</h2>
            <p>Enter URLs (one per line) to whitelist:</p>
            <textarea name="cdn_localizer_whitelist" rows="10" cols="50"><?php echo esc_textarea(implode("\n", $this->whitelist)); ?></textarea>
            <p>
                <?php wp_nonce_field('cdn_localizer_save_whitelist', 'cdn_localizer_nonce_whitelist'); ?>
                <input type="submit" name="cdn_localizer_save_whitelist" class="button button-primary" value="Save Whitelist">
            </p>
        </form>
        <?php
    }

    public function cdn_localizer_admin_page()
    {
        if (isset($_POST['cdn_localizer_save'])) {
            $this->process_localization();
        }

        // Get the active tab from the $_GET param
        $default_tab = 'cdn_resources';
        $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

        ?>
        <div class="wrap">
            <h1>CDN Localizer</h1>
            <p style="margin-top: 0;">Localize resources from CDN to your local server.</p>

            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_slug => $tab_name) : ?>
                    <a href="?page=<?php echo $this->page_slug ?>&tab=<?php echo $tab_slug; ?>" class="nav-tab <?php if ($tab === $tab_slug) : ?>nav-tab-active<?php endif; ?>"><?php echo $tab_name; ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="tab-content">
                <?php call_user_func([$this, "page_" . $tab]); ?>
            </div>
        </div>
        <?php
    }

    public function detect_cdn_resources()
    {
        $cdn_resources = array();
        $resources = array();

        $files = $this->get_all_theme_files();

        foreach ($files as $file) {
            $file_content = file_get_contents($file);
            $html = HtmlDomParser::str_get_html($file_content);
            $file_name = basename($file);

            // Detect <link> tags (CSS)
            foreach ($html->find('link[rel=stylesheet]') as $element) {
                $css_url = $element->href;
                if ($this->is_cdn_url($css_url)) {
                    $cdn_resources[$css_url] = [
                        "type" => "css",
                        "origin" => $file_name
                    ];
                } else {
                    $resources[$css_url] = [
                        "type" => "css",
                        "origin" => $file_name
                    ];
                }
            }

            // Detect <script> tags (JS)
            foreach ($html->find('script[src]') as $element) {
                $js_url = $element->src;
                if ($this->is_cdn_url($js_url)) {
                    $cdn_resources[$js_url] = [
                        "type" => "js",
                        "origin" => $file_name
                    ];
                } else {
                    $resources[$js_url] = [
                        "type" => "js",
                        "origin" => $file_name
                    ];
                }
            }
        }

        return [
            'cdn_resources' => $cdn_resources,
            'local_resources' => $resources
        ];
    }

    private function get_all_theme_files()
    {
        $theme_directory = get_template_directory();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($theme_directory));
        $php_files = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $php_files[] = $file->getPathname();
            }
        }

        return $php_files;
    }

    private function is_cdn_url($url)
    {
        if (in_array($url, $this->whitelist)) {
            return false;
        }
        return (strpos($url, 'https://') === 0) && (strpos($url, home_url()) !== 0);
    }

    public function process_localization()
    {
        if (!isset($_POST['cdn_localizer_nonce']) || !wp_verify_nonce($_POST['cdn_localizer_nonce'], 'cdn_localizer_save')) {
            wp_die('Security check failed');
        }

        $localize = isset($_POST['localize']) ? $_POST['localize'] : array();
        $upload_dir = wp_upload_dir();
        $cdn_local_dir = $upload_dir['basedir'] . $this->cdn_local_dir;

        if (!file_exists($cdn_local_dir)) {
            wp_mkdir_p($cdn_local_dir);
        }

        $_cdn_mappings = [];

        // Remove unused localized resources
        foreach ($this->cdn_mappings as $resource_url => $localize_url) {
            if (!in_array($resource_url, $localize)) {
                $filename = Helper::generateFileName($localize_url);
                $local_path = $cdn_local_dir . '/' . $filename;
                if (file_exists($local_path)) {
                    unlink($local_path);
                }
            }
        }

        // Download localized resources from CDN
        foreach ($localize as $resource_url) {
            $filename = Helper::generateFileName($resource_url);
            $local_path = $cdn_local_dir . '/' . $filename;

            // If file already exists, skip
            if (file_exists($local_path) && isset($this->cdn_mappings[$resource_url])) {
                $_cdn_mappings[$resource_url] = $this->cdn_mappings[$resource_url];
                continue;
            }

            $resource_content = wp_remote_get($resource_url);
            if (is_wp_error($resource_content)) {
                continue;
            }

            file_put_contents($local_path, wp_remote_retrieve_body($resource_content));
            $_cdn_mappings[$resource_url] = $upload_dir['baseurl'] . '/cdn-local/' . $filename;
        }

        update_option('cdn_localizer_mappings', $_cdn_mappings);
        $this->cdn_mappings = $_cdn_mappings;
    }

    public function init_cdn_replacement() {
        ob_start(array($this, 'replace_cdn_urls'));
    }

    public function replace_cdn_urls($content) {
        if (empty($this->cdn_mappings)) {
            return $content;
        }

        $html = HtmlDomParser::str_get_html($content);
        if (!$html) {
            return $content;
        }

        // Replace URLs in <link> tags
        foreach ($html->find('link[rel=stylesheet]') as $element) {
            if (isset($this->cdn_mappings[$element->href])) {
                $element->href = $this->cdn_mappings[$element->href];
            }
        }

        // Replace URLs in <script> tags
        foreach ($html->find('script[src]') as $element) {
            if (isset($this->cdn_mappings[$element->src])) {
                $element->src = $this->cdn_mappings[$element->src];
            }
        }

        $html = $html->save();
        do_action('cdn_localizer_after_replaced_cdn_urls');
        return $html;
    }
}

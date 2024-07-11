<?php

namespace MaruYasa\WpCdnLocalizer;

use voku\helper\HtmlDomParser;

class CDNLocalizer {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_script', array($this, 'detect_cdn_resources'), 9999);
    }

    public function add_admin_menu() {
        add_menu_page('CDN Localizer', 'CDN Localizer', 'manage_options', 'cdn-localizer', array($this, 'cdn_localizer_admin_page'), 'dashicons-rest-api');
    }

    public function cdn_localizer_admin_page() {
        if (isset($_POST['cdn_localizer_save'])) {
            $this->process_localization();
        }

        $this->detect_cdn_resources();
        $this->detect_cdn_resources_via_head();

        $cdn_resources = get_option('cdn_localizer_resources', array());
        $local_resources = get_option('local_localizer_resources', array());

        ?>
        <div class="wrap">
            <h1>CDN Localizer</h1>
            <p style="margin-top: 0;">Localize resources from CDN to your local server.</p>
            <form method="post">
                <h2>Detected CDN Resources</h2>
                <p>
                    <input type="submit" name="cdn_localizer_save" class="button button-primary" value="Localize Selected Resources">
                </p>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Origin</th>
                            <th>Resource</th>
                            <th>Type</th>
                            <th>Localize</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cdn_resources as $resource => $meta): ?>
                        <tr>
                            <td><?php echo esc_html($meta['origin']); ?></td>
                            <td><?php echo esc_url($resource); ?></td>
                            <td><?php echo esc_html($meta['type']); ?></td>
                            <td><input type="checkbox" name="localize[]" value="<?php echo esc_attr($resource); ?>"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <h2>Detected Local Resources</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Origin</th>
                        <th>Resource</th>
                        <th>Type</th>
                        <th>Localize</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($local_resources as $resource => $meta): ?>
                    <tr>
                        <td><?php echo esc_html($meta['origin']); ?></td>
                        <td><?php echo esc_url($resource); ?></td>
                        <td><?php echo esc_html($meta['type']); ?></td>
                        <td><input type="checkbox" name="localize[]" value="<?php echo esc_attr($resource); ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function detect_cdn_resources() {
        global $wp_scripts, $wp_styles;
        $cdn_resources = array();
        foreach (array($wp_scripts, $wp_styles) as $wp_resource) {
            foreach ($wp_resource->registered as $handle => $resource) {
                if ($this->is_cdn_url($resource->src)) {
                    $cdn_resources[$resource->src] = ($wp_resource === $wp_styles) ? 'css' : 'js';
                }
            }
        }

        update_option('cdn_localizer_resources', $cdn_resources);
    }

    public function detect_cdn_resources_via_head() {
        $cdn_resources = array();
        $resources = array();
        foreach ([
            ...$this->get_all_template_token($file_type="header"),
            ...$this->get_all_template_token($file_type="footer")
        ] as $header_token) {
            ob_start();
            call_user_func("get_".$header_token['file_type'], [$header_token['token']]);
            $head_content = ob_get_clean();
        
            $html = HtmlDomParser::str_get_html($head_content);
            $file_type = $header_token['file_type'];
            $header_token = $header_token['token'];
        
            // Detect <link> tags (CSS)
            foreach ($html->find('link[rel=stylesheet]') as $element) {
                $css_url = $element->href;
                if ($this->is_cdn_url($css_url)) {
                    $cdn_resources[$css_url] = [
                        "type" => "css",
                        "origin" =>$file_type.'-'.$header_token
                    ];
                } else {
                    $resources[$css_url] = [
                        "type" => "css",
                        "origin" => $file_type.'-'.$header_token
                    ];
                }
            }
        
            // Detect <script> tags (JS)
            foreach ($html->find('script[src]') as $element) {
                $js_url = $element->src;
                if ($this->is_cdn_url($js_url)) {
                    $cdn_resources[$js_url] = [
                        "type" => "js",
                        "origin" => $file_type.'-'.$header_token
                    ];
                } else {
                    $resources[$js_url] = [
                        "type" => "js",
                        "origin" => $file_type.'-'.$header_token
                    ];
                }
            }
        }
    
        update_option('cdn_localizer_resources', $cdn_resources);
        update_option('local_localizer_resources', $resources);
    }

    private function get_all_template_token($file_type="header") {
         // Get the path to the active theme directory
        $theme_directory = get_template_directory();

        // Use glob to find all header-*.php files
        $_files = glob($theme_directory . '/' . $file_type . '-*.php');

        // Return an array of file names (base names)
        $_files = array_map(
            'basename',
            $_files
        );

        $files = [];
        foreach ($_files as $file) {
            $files[] = [
                'file_type' => $file_type,
                'token' => preg_replace('/' . $file_type . '-(.*)\.php/', '$1', $file)
            ];
        }

        return $files;
    }

    private function is_cdn_url($url) {
        // Check if the URL starts with https:// and is not from the same domain as the website
        return (strpos($url, 'https://') === 0) && (strpos($url, home_url()) !== 0);
    }

    public function process_localization() {
        if (!isset($_POST['localize']) || !is_array($_POST['localize'])) {
            return;
        }

        // TODO: Implement process_localization() method.
    }

    private function update_resource_url($old_url, $new_url) {
        // TODO: Implement update_resource_url() method.
    }
}
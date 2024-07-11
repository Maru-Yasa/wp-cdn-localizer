<?php

namespace MaruYasa\WpCdnLocalizer;

use voku\helper\HtmlDomParser;

class CDNLocalizer
{
    private $cdn_mappings;
    private $page_slug = 'cdn-localizer';

    private $tabs = [
        'cdn_resources' => 'Detected CDN Resources',
        'local_resources' => 'Detected Local Resources',
        'mappings' => 'Mappings'
    ];

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // add_action('wp_enqueue_script', array($this, 'detect_cdn_resources'), 9999);
        add_action('init', array($this, 'init_cdn_replacement'));


        $this->cdn_mappings = $this->get_option('mappings', array());
    }

    private function get_option($key, $default = null)
    {
        return get_option('cdn_localizer_' . $key, $default);
    }

    public function add_admin_menu()
    {
        add_menu_page('CDN Localizer', 'CDN Localizer', 'manage_options', $this->page_slug, array($this, 'cdn_localizer_admin_page'), 'dashicons-rest-api');
    }

    private function page_cdn_resources()
    {
        $cdn_resources = $this->detect_cdn_resources()['cdn_resources'];
        ?>
            <form method="post">
                <p>
                    <?php wp_nonce_field('cdn_localizer_save', 'cdn_localizer_nonce'); ?>
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
                        <?php foreach ($cdn_resources as $resource => $meta) : ?>
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
        <?php
    }

    private function page_local_resources()
    {
        $local_resources = $this->detect_cdn_resources()['local_resources'];
        ?>
            <table class="widefat">
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

    private function page_mappings()
    {
        $mappings = $this->cdn_mappings;
        ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>CDN</th>
                        <th>Localized</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $origin => $local) : ?>
                        <tr>
                            <td><?php echo esc_html($origin); ?></td>
                            <td><?php echo esc_url($local); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php
    }

    public function cdn_localizer_admin_page()
    {
        if (isset($_POST['cdn_localizer_save'])) {
            $this->process_localization();
        }

        //Get the active tab from the $_GET param
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

    public function detect_cdn_resources($file_types = ['header', 'footer'])
    {
        $cdn_resources = array();
        $resources = array();
        foreach ([
            ...in_array('header', $file_types) ? $this->get_all_template_token(file_type: "header") : [],
            ...in_array('footer', $file_types) ? $this->get_all_template_token(file_type: "footer") : []
        ] as $header_token) {
            ob_start();
            call_user_func("get_" . $header_token['file_type'], [$header_token['token']]);
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
                        "origin" => $file_type . '-' . $header_token
                    ];
                } else {
                    $resources[$css_url] = [
                        "type" => "css",
                        "origin" => $file_type . '-' . $header_token
                    ];
                }
            }

            // Detect <script> tags (JS)
            foreach ($html->find('script[src]') as $element) {
                $js_url = $element->src;
                if ($this->is_cdn_url($js_url)) {
                    $cdn_resources[$js_url] = [
                        "type" => "js",
                        "origin" => $file_type . '-' . $header_token
                    ];
                } else {
                    $resources[$js_url] = [
                        "type" => "js",
                        "origin" => $file_type . '-' . $header_token
                    ];
                }
            }
        }

        return [
            'cdn_resources' => $cdn_resources,
            'local_resources' => $resources
        ];
    }

    private function get_all_template_token($file_type = "header")
    {
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

    private function is_cdn_url($url)
    {
        // Check if the URL starts with https:// and is not from the same domain as the website
        return (strpos($url, 'https://') === 0) && (strpos($url, home_url()) !== 0);
    }

    public function process_localization()
    {
        if (!isset($_POST['cdn_localizer_nonce']) || !wp_verify_nonce($_POST['cdn_localizer_nonce'], 'cdn_localizer_save')) {
            wp_die('Security check failed');
        }

        $localize = isset($_POST['localize']) ? $_POST['localize'] : array();
        $upload_dir = wp_upload_dir();
        $cdn_local_dir = $upload_dir['basedir'] . '/cdn-local';
        
        if (!file_exists($cdn_local_dir)) {
            wp_mkdir_p($cdn_local_dir);
        }

        $this->cdn_mappings = array();

        foreach ($localize as $resource_url) {
            $resource_content = wp_remote_get($resource_url);
            if (is_wp_error($resource_content)) {
                continue;
            }

            $filename = basename(parse_url($resource_url, PHP_URL_PATH));
            $local_path = $cdn_local_dir . '/' . $filename;
            file_put_contents($local_path, wp_remote_retrieve_body($resource_content));

            $this->cdn_mappings[$resource_url] = $upload_dir['baseurl'] . '/cdn-local/' . $filename;
        }

        update_option('cdn_localizer_mappings', $this->cdn_mappings);

        var_dump($this->cdn_mappings);
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

        return $html->save();
    }

}

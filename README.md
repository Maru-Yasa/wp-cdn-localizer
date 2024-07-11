# Wordpress CDN Localizer (Work in Progress)

Wordpress CDN Localizer is a WordPress plugin that detects external JavaScript and CSS files loaded from Content Delivery Networks (CDNs) and provides an option to create local copies of these resources. It then dynamically replaces the CDN URLs with local URLs on-the-fly, without modifying your theme files.

## Features

- Detects external .js and .css files loaded from CDNs
- Creates local copies of selected CDN resources
- Dynamically replaces CDN URLs with local URLs without modifying theme files
- Easy-to-use admin interface for managing CDN resources

## How It Works

1. **Detection**: The plugin scans the `<head>` section of your website for `<link>` and `<script>` tags that load resources from known CDN domains or any external HTTPS URL.

2. **Admin Interface**: In the WordPress admin panel (Settings > Wordpress CDN Localizer), you can view all detected CDN resources and select which ones to localize.

3. **Localization**: When you choose to localize resources, the plugin:
   - Downloads the selected resources
   - Saves them in a local directory (wp-content/uploads/cdn-local/)
   - Creates a mapping between the original CDN URL and the new local URL

4. **URL Replacement**: Using output buffering, the plugin intercepts the final HTML output of your site. It then replaces the original CDN URLs with the local URLs for all resources you've chosen to localize.

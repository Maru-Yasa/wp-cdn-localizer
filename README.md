# Wordpress CDN Localizer

Wordpress CDN Localizer is a WordPress plugin that detects external JavaScript and CSS files loaded from Content Delivery Networks (CDNs) and provides an option to create local copies of these resources. It then dynamically replaces the CDN URLs with local URLs on-the-fly, without modifying your theme files.

## Features

- Detects external .js and .css files loaded from CDNs
- Creates local copies of selected CDN resources
- Dynamically replaces CDN URLs with local URLs without modifying theme files
- Easy-to-use admin interface for managing CDN resources

## How It Works

1. **Detection**: The plugin scans the active theme directory section of your wordpress for `<link>` and `<script>` tags that load resources from known CDN domains or any external HTTPS URL.

2. **Admin Interface**: In the WordPress admin panel (CDN Localizer), you can view all detected CDN resources and select which ones to localize.

3. **Localization**: When you choose to localize resources, the plugin:
   - Downloads the selected resources
   - Saves them in a local directory (wp-content/uploads/wp-cdn-local/)
   - Creates a mapping between the original CDN URL and the new local URL

4. **URL Replacement**: Using output buffering, the plugin intercepts the final HTML output of your site. It then replaces the original CDN URLs with the local URLs for all resources you've chosen to localize.

## Usage

1. Go to CDN Localizer in your WordPress admin panel.
3. You'll see a list of detected CDN resources. Check the boxes next to the resources you want to localize.
4. Click "Localize Selected Resources" to create local copies and enable URL replacement.
5. The selected resources will now be served from your local server instead of the CDN.

## Considerations

- Always test this plugin on a staging site before using it on a production site.
- Localized resources won't receive automatic updates from the CDN. You may need to manually update them periodically.
- Serving resources locally may increase the load on your server and potentially affect site performance, especially on high-traffic sites.
- This plugin may not be compatible with some caching plugins or CDN plugins. Test thoroughly if you're using such plugins.

## Troubleshooting

If you encounter any issues:

1. Ensure that your server has write permissions for the wp-content/uploads directory.
2. Check your browser's developer tools for any JavaScript or CSS errors.
3. Temporarily deactivate other plugins to check for conflicts.
4. If problems persist, deactivate the CDN Localizer plugin and your site will revert to using the original CDN URLs.

## Support

For support, feature requests, or bug reports, please open an issue on the plugin's GitHub repository.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

---

Remember to always keep your WordPress installation and all plugins up to date for the best performance and security.

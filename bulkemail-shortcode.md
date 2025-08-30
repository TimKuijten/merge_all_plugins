# Correo Masivo Shortcode

Place the shortcode `[kt_bulk_mailer]` on a page or template to display the Correo Masivo form on the front end. Scripts and styles are enqueued automatically when the shortcode is present, and the UI inherits the Pipeline plugin's visual style. Only users with the `manage_options` capability can view and use the form.

Example in a template:

```php
echo do_shortcode('[kt_bulk_mailer]');
```

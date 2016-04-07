# Mailjet WP Mail Dropin

A dropin replacement for the WordPress wp_mail function

## Installation
- Copy repository into your plugins or plugins-mu directory
- Define `MAILJET_API_KEY` and `MAILJET_SECRET_KEY` via wp-config.php or via environment variables (found in your mailjet account settngs)

```php

 defined( 'MAILJET_API_KEY' ) or define( 'MAILJET_API_KEY', 'your_secret_key' );
 
 defined( 'MAILJET_SECRET_KEY' ) or define( 'MAILJET_SECRET_KEY', 'your_secret_key' );
 
```
- Enable the plugin


## `from_email` handling

WordPress default filtering will set a sent email's `from` address to wordpress@{domain}.{tld}. 
This may be undesirable when working with multiple environments for a single project because mailjet requires that from email addresses are first validated.
To fix the from address per project, `wp_mail_from` can be filtered		

```php
add_filter( 'wp_mail_from', function() {
  return 'email@example.com'
} );
```

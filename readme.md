=== Contact Form 7 Database Addon - CFDB7 ===
Contributors: arshidkv12
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=H5F3Z6S3MNTXA&lc=IN&item_name=wp%2dlogin%2dlimit&amount=5%2e00&currency_code=USD&button_subtype=services&bn=PP%2dBuyNowBF%3abtn_buynowCC_LG%2egif%3aNonHosted
Tags: cf7, contact form 7, contact form 7 db, cf7 database, wpcf7
Requires at least: 4.8
Tested up to: 6.7
Stable tag: 1.2.10
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.0

Save and manage Contact Form 7 messages. Never lose important data. It is a lightweight contact form 7 database plugin.


== Description ==

The "CFDB7" plugin saves contact form 7 submissions to your WordPress database. Export the data to a CSV file.
By simply installing the plugin, it will automatically begin to capture form submissions from contact form 7. 


= Features of CFDB 7 =

* No configuration is needed
* Save Contact Form 7 form submitted data to the database.
* Single database table for all contact form 7 forms
* Easy to use and lightweight plugin
* Developer friendly & easy to customize
* Display all created contact form 7 form list.
* Export CF7 DB (CF7 Database - cf7db) data in CSV file

= Form Email Testing Tool =
* [MailMug - SMTP Sandbox](https://mailmug.net) 

= Plugins =
* [PostBox Email Log](https://wordpress.org/plugins/postbox-email-logs/)
* [WP mail smtp](https://wordpress.org/plugins/wp-mail-smtp-mailer/)

= Pro Addons =
* [Advanced MYSQL DB](https://ciphercoin.com/downloads/contact-form-7-column-base-mysql-database-addon/)
Separate MySQL column for each cf7 input field
* [CFDB7 DB Switcher](https://ciphercoin.com/downloads/cfdb7-database-switcher/)
Connect CFDB7 to an external database or another DB
* [Drag & Drop File Upload](https://ciphercoin.com/downloads/filedrop-contact-form-7/)
Contact form 7 drag and drop files upload plugin.
* [Already Submitted?](https://ciphercoin.com/downloads/cfdb7-unique-field/)
Trigger error if a field is already submitted
* [Popup Message](https://ciphercoin.com/downloads/cf7-popup-message/)
Replace your validation and success messages with beautiful popup messages to attract visitors.
* [Export PDF File](https://ciphercoin.com/downloads/cfdb7-export-pdf-addon/)
Easy to export contact forms from database to PDF file
* [Import CSV to Database](https://ciphercoin.com/downloads/cfdb7-import-csv-to-database/)
Import data from the CSV file to the CFDB7 database

Support : [http://www.ciphercoin.com/contact/](https://www.ciphercoin.com/contact/)
Extensions : [Contact form 7 more Add-ons](https://ciphercoin.com/contact-form-7-database-cfdb7-add-ons/)

== Frequently Asked Questions ==
= 1. How do you change the CSV delimiter to a semicolon? =
To change the CSV delimiter to a semicolon, add the following code to your theme's **functions.php** file:
```
add_filter('cfdb7_csv_delimiter', function( $delimiter ){
    return ';';
});
```


== Installation ==

1. Download and extract plugin files to a wp-content/plugin directory.
2. Activate the plugin through the WordPress admin interface.
3. Done!




== Screenshots ==
1. Admin

== Changelog ==

= 1.2.10 =
Fixed csv header issues

= 1.2.9 =
Changed csv delimiter to comma with filter

= 1.2.8 =
Changed csv delimiter to semicolon

= 1.2.7 =
Extra protection for files

= 1.2.6.8 =
Added cfdb7_admin_subpage_columns hook



























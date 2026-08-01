<?php

if (!defined('ABSPATH')) {
    exit;
}

class CFDB7_Settings
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

    }

    public function admin_assets($hook)
    {
        if ($hook !== 'contact-forms_page_cfdb7-settings') {
            return;
        }

        wp_enqueue_style(
            'cfdb7-settings',
                plugins_url('css/settings.css', CFDB7_PLUGIN_FILE),
            array(),
            '1.4.0'
        );
    }

    public function register_menu()
    {
        add_submenu_page(
            'cfdb7-list.php',
            __('CFDB7 Settings', 'contact-form-cfdb7'),
            __('Settings', 'contact-form-cfdb7'),
            'manage_options',
            'cfdb7-settings',
            array($this, 'settings_page')
        );
    }

    public function register_settings()
    {
        register_setting(
            'cfdb7_settings',
            'cfdb7_csv_delimiter',
            array(
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitize_delimiter'),
                'default'           => ',',
            )
        );

        add_settings_section(
            'cfdb7_export_section',
            __('CSV Export', 'contact-form-cfdb7'),
            '__return_false',
            'cfdb7_settings'
        );

        add_settings_field(
            'cfdb7_csv_delimiter',
            __('Delimiter', 'contact-form-cfdb7'),
            array($this, 'delimiter_field'),
            'cfdb7_settings',
            'cfdb7_export_section'
        );

        /* Premium Features */
        add_settings_section(
            'cfdb7_premium_section',
            __('Premium Features', 'contact-form-cfdb7'),
            '__return_false',
            'cfdb7_settings'
        );

        add_settings_field(
            'cfdb7_entry_automation',
            __('Entry Automation', 'contact-form-cfdb7'),
            array($this, 'entry_automation_field'),
            'cfdb7_settings',
            'cfdb7_premium_section'
        );

        add_settings_field(
            'cfdb7_pdf_export',
            __('PDF Export', 'contact-form-cfdb7'),
            array($this, 'pdf_export_field'),
            'cfdb7_settings',
            'cfdb7_premium_section'
        );

        add_settings_field(
            'cfdb7_csv_import',
            __('CSV Import', 'contact-form-cfdb7'),
            array($this, 'csv_import_field'),
            'cfdb7_settings',
            'cfdb7_premium_section'
        );
    }

    public function sanitize_delimiter($value)
    {
        $allowed = array(',', ';', '|', "\t");

        return in_array($value, $allowed, true) ? $value : ',';
    }

    public function delimiter_field()
    {
        $value = get_option('cfdb7_csv_delimiter', ',');

        $options = array(
            ',' => __('Comma (,)', 'contact-form-cfdb7'),
            ';' => __('Semicolon (;)', 'contact-form-cfdb7'),
            '|' => __('Pipe (|)', 'contact-form-cfdb7'),
        );
        ?>

        <select name="cfdb7_csv_delimiter">
            <?php foreach ($options as $delimiter => $label) : ?>
                <option value="<?php echo esc_attr($delimiter); ?>" <?php selected($value, $delimiter); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php
    }

    public function settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('CFDB7 Settings', 'contact-form-cfdb7'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('cfdb7_settings');
                do_settings_sections('cfdb7_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }


    private function premium_feature($title, $description, $url, $installed = false){
        ?>
        <fieldset class="cfdb7-premium-setting">

            <label>
                <input type="checkbox" disabled <?php checked($installed); ?> />
                <strong><?php echo esc_html($title); ?></strong>
            </label>

            <p class="description">
                <?php echo esc_html($description); ?>
            </p>

            <?php if (!$installed) : ?>

                <p style="margin:8px 0;color:#d63638;">
                    🔒 <?php esc_html_e('Requires the premium add-on.', 'contact-form-cfdb7'); ?>
                </p>

                <a class="button button-secondary"
                target="_blank"
                href="<?php echo esc_url($url); ?>">
                    <?php esc_html_e('Install Add-on', 'contact-form-cfdb7'); ?>
                </a>

            <?php else : ?>

                <span style="color:#00a32a;">
                    ✓ <?php esc_html_e('Installed', 'contact-form-cfdb7'); ?>
                </span>

            <?php endif; ?>

        </fieldset>
        <?php
    }

    public function entry_automation_field(){
        $installed = class_exists('CFDB7_Public_Export_CSV');

        $this->premium_feature(
            __('Automatically export entries', 'contact-form-cfdb7'),
            __('Automatically export Contact Form 7 submissions to CSV on schedule or when new entries arrive.', 'contact-form-cfdb7'),
            'https://ciphercoin.com/downloads/public-export-csv/',
            $installed
        );
    }

    public function pdf_export_field(){
        $installed = function_exists('cfdb7_pdf_on_activate');

        $this->premium_feature(
            __('Export entries as PDF', 'contact-form-cfdb7'),
            __('Generate professional PDF reports from Contact Form 7 submissions.', 'contact-form-cfdb7'),
            'https://ciphercoin.com/downloads/cfdb7-export-pdf-addon/',
            $installed
        );
    }

    public function csv_import_field(){
        $installed = class_exists('CFDB7_csv_import_page');

        $this->premium_feature(
            __('Import CSV into database', 'contact-form-cfdb7'),
            __('Import existing CSV files directly into the Contact Form 7 database.', 'contact-form-cfdb7'),
            'https://ciphercoin.com/downloads/cfdb7-import-csv-to-database/',
            $installed
        );
    }
}
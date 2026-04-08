<?php
/**
 * Plugin Name: CF7 Telegram Notifications
 * Description: Send Contact Form 7 submissions to a Telegram channel with formatted details and attachments.
 * Version: 1.0.0
 * Author: <a href="https://www.davecamerini.it">Davecamerini</a>
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CF7_Telegram_Notifications {
    private const OPTION_KEY = 'cf7_tn_settings';
    private const SETTINGS_GROUP = 'cf7_tn_settings_group';
    private const MENU_SLUG = 'cf7-telegram-notifications';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_head', [$this, 'print_admin_icon_styles']);
        add_action('admin_post_cf7_tn_send_test', [$this, 'handle_send_test']);
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7_submission']);
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __('CF7 Telegram Notifications', 'cf7-telegram-notifications'),
            __('CF7 Telegram Notifications', 'cf7-telegram-notifications'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_settings_page'],
            plugins_url('Mon.png', __FILE__),
            58
        );
    }

    public function print_admin_icon_styles(): void {
        ?>
        <style>
            #adminmenu #toplevel_page_cf7-telegram-notifications .wp-menu-image img {
                opacity: .65;
                filter: grayscale(100%) brightness(90%);
                transition: opacity .15s ease-in-out, filter .15s ease-in-out;
            }

            #adminmenu #toplevel_page_cf7-telegram-notifications:hover .wp-menu-image img,
            #adminmenu #toplevel_page_cf7-telegram-notifications.current .wp-menu-image img,
            #adminmenu #toplevel_page_cf7-telegram-notifications.wp-has-current-submenu .wp-menu-image img {
                opacity: 1;
                filter: grayscale(0%) brightness(112%);
            }
        </style>
        <?php
    }

    public function register_settings(): void {
        register_setting(self::SETTINGS_GROUP, self::OPTION_KEY, [$this, 'sanitize_settings']);

        add_settings_section(
            'cf7_tn_main_section',
            __('Telegram Settings', 'cf7-telegram-notifications'),
            function () {
                echo '<p>' . esc_html__('Configure your Telegram bot and choose which CF7 forms should trigger notifications.', 'cf7-telegram-notifications') . '</p>';
            },
            self::MENU_SLUG
        );

        add_settings_field(
            'bot_token',
            __('Bot API Token', 'cf7-telegram-notifications'),
            [$this, 'render_bot_token_field'],
            self::MENU_SLUG,
            'cf7_tn_main_section'
        );

        add_settings_field(
            'chat_id',
            __('Channel / Chat ID', 'cf7-telegram-notifications'),
            [$this, 'render_chat_id_field'],
            self::MENU_SLUG,
            'cf7_tn_main_section'
        );

        add_settings_field(
            'form_scope',
            __('Form Trigger Scope', 'cf7-telegram-notifications'),
            [$this, 'render_form_scope_field'],
            self::MENU_SLUG,
            'cf7_tn_main_section'
        );

        add_settings_field(
            'selected_form_id',
            __('Specific CF7 Form', 'cf7-telegram-notifications'),
            [$this, 'render_selected_form_field'],
            self::MENU_SLUG,
            'cf7_tn_main_section'
        );

        add_settings_field(
            'skip_empty_fields',
            __('Skip Empty Fields', 'cf7-telegram-notifications'),
            [$this, 'render_skip_empty_fields_field'],
            self::MENU_SLUG,
            'cf7_tn_main_section'
        );

        add_settings_field(
            'excluded_fields',
            __('Excluded Field Names', 'cf7-telegram-notifications'),
            [$this, 'render_excluded_fields_field'],
            self::MENU_SLUG,
            'cf7_tn_main_section'
        );
    }

    public function sanitize_settings(array $input): array {
        $settings = $this->get_settings();

        $settings['bot_token'] = isset($input['bot_token']) ? sanitize_text_field((string) $input['bot_token']) : '';
        $settings['chat_id'] = isset($input['chat_id']) ? sanitize_text_field((string) $input['chat_id']) : '';

        $form_scope = isset($input['form_scope']) ? sanitize_text_field((string) $input['form_scope']) : 'all';
        $settings['form_scope'] = in_array($form_scope, ['all', 'single'], true) ? $form_scope : 'all';

        $selected_form_id = isset($input['selected_form_id']) ? absint($input['selected_form_id']) : 0;
        $settings['selected_form_id'] = $selected_form_id;
        $settings['skip_empty_fields'] = !empty($input['skip_empty_fields']) ? '1' : '0';
        $settings['excluded_fields'] = isset($input['excluded_fields']) ? sanitize_text_field((string) $input['excluded_fields']) : '';

        return $settings;
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CF7 Telegram Notifications', 'cf7-telegram-notifications') . '</h1>';

        if (isset($_GET['cf7_tn_test']) && $_GET['cf7_tn_test'] === 'sent') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Test message sent to Telegram.', 'cf7-telegram-notifications') . '</p></div>';
        } elseif (isset($_GET['cf7_tn_test']) && $_GET['cf7_tn_test'] === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Unable to send test message. Check token and chat ID.', 'cf7-telegram-notifications') . '</p></div>';
        }

        echo '<form method="post" action="options.php">';

        settings_fields(self::SETTINGS_GROUP);
        do_settings_sections(self::MENU_SLUG);
        submit_button(__('Save Settings', 'cf7-telegram-notifications'));

        echo '</form>';
        echo '<hr />';
        echo '<h2>' . esc_html__('Send Test Message', 'cf7-telegram-notifications') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="cf7_tn_send_test" />';
        wp_nonce_field('cf7_tn_send_test_action', 'cf7_tn_send_test_nonce');
        submit_button(__('Send Test to Telegram', 'cf7-telegram-notifications'), 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';
    }

    public function render_bot_token_field(): void {
        $settings = $this->get_settings();
        printf(
            '<input type="password" name="%1$s[bot_token]" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($settings['bot_token'])
        );
        echo '<p class="description">' . esc_html__('Example: 123456789:AA... (from @BotFather)', 'cf7-telegram-notifications') . '</p>';
    }

    public function render_chat_id_field(): void {
        $settings = $this->get_settings();
        printf(
            '<input type="text" name="%1$s[chat_id]" value="%2$s" class="regular-text" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($settings['chat_id'])
        );
        echo '<p class="description">' . esc_html__('Use your channel username (e.g. @mychannel) or chat ID.', 'cf7-telegram-notifications') . '</p>';
    }

    public function render_form_scope_field(): void {
        $settings = $this->get_settings();
        $scope = $settings['form_scope'];

        echo '<label><input type="radio" name="' . esc_attr(self::OPTION_KEY) . '[form_scope]" value="all" ' . checked($scope, 'all', false) . ' /> ' . esc_html__('All forms', 'cf7-telegram-notifications') . '</label><br />';
        echo '<label><input type="radio" name="' . esc_attr(self::OPTION_KEY) . '[form_scope]" value="single" ' . checked($scope, 'single', false) . ' /> ' . esc_html__('Only one specific form', 'cf7-telegram-notifications') . '</label>';
    }

    public function render_selected_form_field(): void {
        $settings = $this->get_settings();
        $forms = $this->get_cf7_forms();

        echo '<select name="' . esc_attr(self::OPTION_KEY) . '[selected_form_id]">';
        echo '<option value="0">' . esc_html__('Select a form', 'cf7-telegram-notifications') . '</option>';

        foreach ($forms as $form) {
            printf(
                '<option value="%1$d" %2$s>%3$s (#%1$d)</option>',
                (int) $form->ID,
                selected((int) $settings['selected_form_id'], (int) $form->ID, false),
                esc_html($form->post_title ?: __('Untitled Form', 'cf7-telegram-notifications'))
            );
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Used only when "Only one specific form" is selected.', 'cf7-telegram-notifications') . '</p>';
    }

    public function render_skip_empty_fields_field(): void {
        $settings = $this->get_settings();
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[skip_empty_fields]" value="1" ' . checked($settings['skip_empty_fields'], '1', false) . ' /> ' . esc_html__('Do not include empty fields in Telegram message.', 'cf7-telegram-notifications') . '</label>';
    }

    public function render_excluded_fields_field(): void {
        $settings = $this->get_settings();
        printf(
            '<input type="text" name="%1$s[excluded_fields]" value="%2$s" class="regular-text" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($settings['excluded_fields'])
        );
        echo '<p class="description">' . esc_html__('Comma-separated field names to skip (example: acceptance, privacy, marketing).', 'cf7-telegram-notifications') . '</p>';
    }

    public function handle_send_test(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'cf7-telegram-notifications'));
        }

        check_admin_referer('cf7_tn_send_test_action', 'cf7_tn_send_test_nonce');

        $settings = $this->get_settings();
        $result = false;

        if (!empty($settings['bot_token']) && !empty($settings['chat_id'])) {
            $message = $this->build_test_message();
            $result = $this->send_telegram_message($settings['bot_token'], $settings['chat_id'], $message);
        }

        $redirect_url = add_query_arg(
            ['page' => self::MENU_SLUG, 'cf7_tn_test' => $result ? 'sent' : 'error'],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_cf7_submission($contact_form): void {
        if (!function_exists('wpcf7_submission') && !class_exists('WPCF7_Submission')) {
            return;
        }

        $settings = $this->get_settings();

        if (empty($settings['bot_token']) || empty($settings['chat_id'])) {
            return;
        }

        $form_id = (int) $contact_form->id();
        if (!$this->should_notify_for_form($form_id, $settings)) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $posted_data = (array) $submission->get_posted_data();
        $uploaded_files = (array) $submission->uploaded_files();

        $message = $this->build_message($contact_form, $posted_data, $settings);
        $this->send_telegram_message($settings['bot_token'], $settings['chat_id'], $message);

        if (!empty($uploaded_files)) {
            $this->send_telegram_attachments($settings['bot_token'], $settings['chat_id'], $uploaded_files);
        }
    }

    private function get_settings(): array {
        $defaults = [
            'bot_token' => '',
            'chat_id' => '',
            'form_scope' => 'all',
            'selected_form_id' => 0,
            'skip_empty_fields' => '1',
            'excluded_fields' => '',
        ];

        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, $defaults);
    }

    private function should_notify_for_form(int $form_id, array $settings): bool {
        if (($settings['form_scope'] ?? 'all') === 'all') {
            return true;
        }

        return $form_id === (int) ($settings['selected_form_id'] ?? 0);
    }

    private function get_cf7_forms(): array {
        if (!post_type_exists('wpcf7_contact_form')) {
            return [];
        }

        return get_posts([
            'post_type' => 'wpcf7_contact_form',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    private function build_message($contact_form, array $posted_data, array $settings): string {
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $form_title = method_exists($contact_form, 'title') ? $contact_form->title() : __('Unknown Form', 'cf7-telegram-notifications');
        $form_id = method_exists($contact_form, 'id') ? (int) $contact_form->id() : 0;
        $excluded = $this->get_excluded_fields($settings);
        $skip_empty = ($settings['skip_empty_fields'] ?? '1') === '1';

        $lines = [];
        $lines[] = "📩 <b>New Contact Form 7 Submission</b>";
        $lines[] = '';
        $lines[] = '🌐 <b>Site:</b> ' . esc_html($site_name);
        $lines[] = '🧾 <b>Form:</b> ' . esc_html($form_title) . ' (#' . $form_id . ')';
        $lines[] = '🕒 <b>Time:</b> ' . esc_html(wp_date('Y-m-d H:i:s'));
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━';
        $lines[] = '📌 <b>Submitted Data</b>';

        foreach ($posted_data as $key => $value) {
            if (in_array($key, ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', '_wpcf7_posted_data_hash'], true)) {
                continue;
            }

            $label = is_string($key) ? $key : (string) $key;
            $formatted_value = $this->stringify_value($value);
            if (in_array(strtolower($label), $excluded, true)) {
                continue;
            }
            if ($skip_empty && trim($formatted_value) === '') {
                continue;
            }

            $lines[] = '• <b>' . esc_html($label) . ':</b> ' . esc_html($formatted_value);
        }

        return implode("\n", $lines);
    }

    private function build_test_message(): string {
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $lines = [];
        $lines[] = "✅ <b>CF7 Telegram Test Message</b>";
        $lines[] = '';
        $lines[] = '🌐 <b>Site:</b> ' . esc_html($site_name);
        $lines[] = '🕒 <b>Time:</b> ' . esc_html(wp_date('Y-m-d H:i:s'));
        $lines[] = '';
        $lines[] = esc_html__('If you received this message, Telegram settings are correct.', 'cf7-telegram-notifications');

        return implode("\n", $lines);
    }

    private function get_excluded_fields(array $settings): array {
        $raw = (string) ($settings['excluded_fields'] ?? '');
        if ($raw === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $raw));
        $items = array_filter($items, static function ($field) {
            return $field !== '';
        });

        return array_map('strtolower', $items);
    }

    private function stringify_value($value): string {
        if (is_array($value)) {
            $flat = array_map(
                static function ($item): string {
                    if (is_scalar($item) || $item === null) {
                        return (string) $item;
                    }

                    return wp_json_encode($item);
                },
                $value
            );

            return implode(', ', array_filter($flat, static function ($v) {
                return $v !== '';
            }));
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return wp_json_encode($value);
    }

    private function send_telegram_message(string $bot_token, string $chat_id, string $message): bool {
        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $bot_token);

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'body' => [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'true',
            ],
        ]);

        return !is_wp_error($response);
    }

    private function send_telegram_attachments(string $bot_token, string $chat_id, array $uploaded_files): void {
        foreach ($uploaded_files as $field_name => $files) {
            $files = is_array($files) ? $files : [$files];

            foreach ($files as $file_path) {
                if (!is_string($file_path) || $file_path === '' || !file_exists($file_path)) {
                    continue;
                }

                $caption = sprintf(
                    __('Attachment from field: %s', 'cf7-telegram-notifications'),
                    is_string($field_name) ? $field_name : (string) $field_name
                );

                $this->send_telegram_document($bot_token, $chat_id, $file_path, $caption);
            }
        }
    }

    private function send_telegram_document(string $bot_token, string $chat_id, string $file_path, string $caption): void {
        if (!function_exists('curl_file_create')) {
            $this->send_telegram_message(
                $bot_token,
                $chat_id,
                '<b>Attachment upload skipped</b>' . "\n" . esc_html(basename($file_path))
            );
            return;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendDocument', $bot_token);

        wp_remote_post($url, [
            'timeout' => 30,
            'body' => [
                'chat_id' => $chat_id,
                'caption' => $caption,
                'document' => curl_file_create($file_path),
            ],
        ]);
    }
}

new CF7_Telegram_Notifications();

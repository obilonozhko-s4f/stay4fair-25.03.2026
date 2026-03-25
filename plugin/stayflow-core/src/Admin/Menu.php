<?php

declare(strict_types=1);

namespace StayFlow\Admin;

use StayFlow\Registry\ModuleRegistry;
use StayFlow\Settings\SettingsStore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 2.17.0
 * RU: Управление меню. Добавлены настройки ссылки и текста кнопки для Site Notice.
 * EN: Menu management. Added button text and link settings for Site Notice.
 */
final class Menu
{
    public function register(): void
    {
        // RU: Регистрация страниц меню / EN: Register menu pages
        add_menu_page('StayFlow', 'StayFlow', 'manage_options', 'stayflow-core', [$this, 'renderDashboard'], 'dashicons-admin-generic', 58);
        add_submenu_page('stayflow-core', 'Settings', 'Settings', 'manage_options', 'stayflow-core-settings', [$this, 'renderSettings']);
        add_submenu_page('stayflow-core', 'Content Registry', 'Content Registry', 'manage_options', 'stayflow-core-content-registry', [$this, 'renderContentRegistry']);
        add_submenu_page('stayflow-core', 'Policies', 'Policies', 'manage_options', 'stayflow-core-policies', [$this, 'renderPolicies']);
        add_submenu_page('stayflow-core', 'Site Notice', 'Site Notice', 'manage_options', 'stayflow-site-notice', [$this, 'renderSiteNotice']);
        add_submenu_page('stayflow-core', 'Owners', 'Owners', 'manage_options', 'stayflow-owners', [$this, 'renderOwnersTable']);
        add_submenu_page('stayflow-core', 'Finance', 'Finance Hub', 'manage_options', 'stayflow-finance', [$this, 'renderFinanceHub']);

        add_action('admin_init', function() {
            register_setting('stayflow_policies_group', 'stayflow_registry_policies');
            register_setting('stayflow_content_group', 'stayflow_registry_content');
            register_setting('stayflow_notice_group', 'stayflow_site_notice_settings');
            
            if (current_user_can('manage_options') && !current_user_can('switch_users')) {
                $role = get_role('administrator');
                if ($role) {
                    $role->add_cap('switch_users');
                }
            }
        });
    }

    public function renderOwnersTable(): void
    {
        if (class_exists('\\StayFlow\\Admin\\OwnersTable')) {
            (new OwnersTable())->render();
        }
    }

    public function renderFinanceHub(): void
    {
        if (class_exists('\\StayFlow\\Admin\\FinanceHub')) {
            (new FinanceHub())->render();
        }
    }

    public function renderDashboard(): void
    {
        $modules = ModuleRegistry::all();
        $modules[] = [
            'key' => 'site_notice',
            'title' => 'Site Notice (Popup)',
            'desc' => 'Globales Popup für Ankündigungen oder Wartung.',
            'icon' => '📢',
            'status' => 'active',
            'link' => 'admin.php?page=stayflow-site-notice'
        ];

        ?>
        <div class="wrap stayflow-dashboard">
            <div class="sf-hero">
                <div>
                    <h1>StayFlow Control Center</h1>
                    <p>SaaS-ready enterprise architecture core</p>
                </div>
                <span class="sf-version">v<?php echo esc_html(STAYFLOW_CORE_VERSION); ?></span>
            </div>
            <div class="sf-kpi-grid">
                <?php $this->kpi('Modules', count($modules)); ?>
                <?php $this->kpi('Active', $this->countByStatus($modules, 'active')); ?>
                <?php $this->kpi('Pending', $this->countByStatus($modules, 'pending')); ?>
                <?php $this->kpi('Coming Soon', $this->countByStatus($modules, 'coming')); ?>
            </div>
            <div class="sf-grid">
                <?php foreach ($modules as $module) { $this->card($module); } ?>
            </div>
        </div>
        <?php $this->adminDashboardStyles(); ?>
        <?php
    }
    
    private function kpi(string $label, int $value): void { 
        echo '<div class="sf-kpi"><div class="sf-kpi-value">' . esc_html((string)$value) . '</div><div class="sf-kpi-label">' . esc_html($label) . '</div></div>'; 
    }
    
    private function countByStatus(array $modules, string $status): int { 
        return count(array_filter($modules, fn($m) => $m['status'] === $status)); 
    }
    
    private function card(array $module): void {
        $isClickable = $module['link'] !== '#';
        $link = ($module['key'] === 'owners') ? 'admin.php?page=stayflow-owners' : (($module['key'] === 'finance') ? 'admin.php?page=stayflow-finance' : $module['link']);
        $url = $isClickable ? admin_url($link) : '#';
        
        $titleHtml = esc_html($module['title']);
        if ($module['key'] === 'finance') {
            $titleHtml = '<span style="font-weight:800; color:#000;">Finance</span><span style="background:#ff9000; color:#000; padding:2px 6px; border-radius:4px; margin-left:5px; font-weight:900;">Taxes</span>';
        }
        
        $tagStart = $isClickable ? '<a href="' . esc_url($url) . '" class="sf-card">' : '<div class="sf-card sf-disabled">';
        echo $tagStart . '<div class="sf-icon">' . esc_html($module['icon']) . '</div><h3 style="margin-bottom:10px;">' . $titleHtml . '</h3><p>' . esc_html($module['desc']) . '</p><span class="sf-badge badge-' . esc_attr($module['status']) . '">' . esc_html(ucfirst($module['status'])) . '</span>' . ($isClickable ? '</a>' : '</div>');
    }

    public function renderSiteNotice(): void
    {
        $optKey = 'stayflow_site_notice_settings';
        $options = get_option($optKey, []);
        
        $enabled     = !empty($options['enabled']) ? 1 : 0;
        $logo_url    = !empty($options['logo_url']) ? $options['logo_url'] : 'https://stay4fair.com/wp-content/uploads/2025/12/gorizontal-color-4.webp';
        $cookie_days = !empty($options['cookie_days']) ? (int)$options['cookie_days'] : 1;
        
        // RU: Новые настройки
        $btn_text    = !empty($options['btn_text']) ? $options['btn_text'] : 'Verstanden / Got it';
        $btn_url     = !empty($options['btn_url']) ? $options['btn_url'] : '';
        $btn_target  = !empty($options['btn_target']) ? $options['btn_target'] : '_self';

        $def_content = "<h2 style=\"text-align: center; color: #082567; margin-top:0;\">Willkommen bei Stay4Fair!</h2>\n<p style=\"text-align: center; color: #334155;\">Wir starten aktuell im Testmodus. Es können noch einige kleine Fehler auftreten, aber unser Team arbeitet mit Hochdruck an der Optimierung. Danke für Ihr Verständnis!</p>\n<hr style=\"border: 0; border-top: 1px dashed #cbd5e1; margin: 20px 0;\">\n<h2 style=\"text-align: center; color: #082567;\">Welcome to Stay4Fair!</h2>\n<p style=\"text-align: center; color: #334155;\">We are currently launching in test mode. Some minor bugs may still occur, but our team is working hard on optimization. Thank you for your understanding!</p>";
        
        $content = !empty($options['content']) ? $options['content'] : $def_content;
        ?>
        <div class="wrap stayflow-admin-wrap">
            <h1 class="sf-page-title">📢 Global Site Notice (Popup)</h1>
            <p style="color: #64748b; margin-bottom: 30px;">Verwalten Sie hier das globale Pop-up-Fenster.</p>
            
            <?php settings_errors('stayflow_notice_group'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('stayflow_notice_group'); ?>
                
                <div class="sf-settings-grid">
                    <div class="sf-settings-card">
                        <h3>⚙️ Popup Status & Verhalten</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label>Popup Aktivieren?</label></th>
                                <td>
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                        <input type="checkbox" name="<?php echo $optKey; ?>[enabled]" value="1" <?php checked($enabled, 1); ?>>
                                        <strong>Ja, Popup anzeigen</strong>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Schließen merken для (Tage)</label></th>
                                <td>
                                    <input type="number" name="<?php echo $optKey; ?>[cookie_days]" value="<?php echo esc_attr((string)$cookie_days); ?>" class="regular-text" style="width: 80px;" min="1" max="365">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="sf-settings-card">
                        <h3>🔘 Button-Einstellungen (CTA)</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label>Button Text</label></th>
                                <td><input type="text" name="<?php echo $optKey; ?>[btn_text]" value="<?php echo esc_attr($btn_text); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Button Link (URL)</label></th>
                                <td>
                                    <input type="url" name="<?php echo $optKey; ?>[btn_url]" value="<?php echo esc_attr($btn_url); ?>" class="large-text" placeholder="https://...">
                                    <p class="description">Lassen Sie dieses Feld leer, wenn die Schaltfläche nur das Pop-up schließen soll.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Ziel (Target)</label></th>
                                <td>
                                    <select name="<?php echo $optKey; ?>[btn_target]">
                                        <option value="_self" <?php selected($btn_target, '_self'); ?>>Gleiches Fenster (_self)</option>
                                        <option value="_blank" <?php selected($btn_target, '_blank'); ?>>Neues Fenster (_blank)</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="sf-settings-card">
                        <h3>🖼️ Medien & Inhalt</h3>
                        <table class="form-table" style="margin-bottom: 20px;">
                            <tr>
                                <th scope="row"><label>Logo URL</label></th>
                                <td><input type="url" name="<?php echo $optKey; ?>[logo_url]" value="<?php echo esc_attr($logo_url); ?>" class="large-text"></td>
                            </tr>
                        </table>
                        <h4 style="margin: 0 0 10px 0; color: #082567;">Popup Text</h4>
                        <?php wp_editor($content, 'site_notice_content_editor', ['textarea_name' => $optKey . '[content]', 'media_buttons' => true, 'textarea_rows' => 12, 'tinymce' => true]); ?>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <?php submit_button('Einstellungen speichern', 'primary', 'submit', false, ['style' => 'background: #082567; border-color: #082567; color: #E0B849; padding: 5px 25px; border-radius: 8px;']); ?>
                </div>
            </form>
        </div>
        <?php $this->adminStyles(); ?>
        <?php
    }

    public function renderSettings(): void
    {
        $defaults = SettingsStore::defaults();
        $saved = get_option(SettingsStore::OPTION_KEY, []);
        $options = array_replace_recursive($defaults, is_array($saved) ? $saved : []);
        $optKey  = SettingsStore::OPTION_KEY;
        ?>
        <div class="wrap stayflow-admin-wrap">
            <h1 class="sf-page-title">⚙️ StayFlow Settings</h1>
            <?php settings_errors('stayflow_core_settings_group'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('stayflow_core_settings_group'); ?>
                <div class="sf-settings-grid">
                    
                    <div class="sf-settings-card">
                        <h3>💳 Finanz- und Steuer-Standards</h3>
                        <table class="form-table">
                            <tr><th scope="row"><label>Plattform-Land</label></th><td><input type="text" name="<?php echo $optKey; ?>[platform_country]" value="<?php echo esc_attr((string)$options['platform_country']); ?>" class="regular-text" style="width: 80px;"></td></tr>
                            <tr><th scope="row"><label>Basiswährung</label></th><td><input type="text" name="<?php echo $optKey; ?>[base_currency]" value="<?php echo esc_attr((string)$options['base_currency']); ?>" class="regular-text" style="width: 80px;"></td></tr>
                            <tr><th scope="row"><label>Standard Provision (%)</label></th><td><input type="number" step="0.1" name="<?php echo $optKey; ?>[commission_default]" value="<?php echo esc_attr((string)$options['commission_default']); ?>" class="regular-text" style="width: 100px;"></td></tr>
                            <tr><th scope="row"><label>MwSt-Satz Modell B (%)</label></th><td><input type="number" step="0.1" name="<?php echo $optKey; ?>[platform_vat_rate]" value="<?php echo esc_attr((string)$options['platform_vat_rate']); ?>" class="regular-text" style="width: 100px;"></td></tr>
                            <tr><th scope="row"><label>MwSt-Satz Modell A (%)</label></th><td><input type="number" step="0.1" name="<?php echo $optKey; ?>[platform_vat_rate_a]" value="<?php echo esc_attr((string)$options['platform_vat_rate_a']); ?>" class="regular-text" style="width: 100px;"></td></tr>
                        </table>
                    </div>

                    <div class="sf-settings-card">
                        <h3>✉️ Документ: Onboarding</h3>
                        <table class="form-table">
                            <tr><th scope="row"><label>Betreff</label></th><td><input type="text" name="<?php echo $optKey; ?>[onboarding][verify_email_sub]" value="<?php echo esc_attr((string)$options['onboarding']['verify_email_sub']); ?>" class="large-text"></td></tr>
                            <tr><th scope="row"><label>Nachricht</label></th><td><textarea name="<?php echo $optKey; ?>[onboarding][verify_email_body]" rows="5" class="large-text"><?php echo esc_textarea((string)$options['onboarding']['verify_email_body']); ?></textarea></td></tr>
                        </table>
                    </div>

                    <div class="sf-settings-card">
                        <h3>📄 Документ: Owner E-Mail</h3>
                        <table class="form-table">
                            <tr><th scope="row"><label>Betreff</label></th><td><input type="text" name="<?php echo $optKey; ?>[owner_pdf][email_subject]" value="<?php echo esc_attr((string)$options['owner_pdf']['email_subject']); ?>" class="large-text"></td></tr>
                            <tr><th scope="row"><label>Nachricht</label></th><td><textarea name="<?php echo $optKey; ?>[owner_pdf][email_body]" rows="5" class="large-text"><?php echo esc_textarea((string)$options['owner_pdf']['email_body']); ?></textarea></td></tr>
                        </table>
                    </div>

                </div>
                <?php submit_button('Einstellungen speichern', 'primary', 'submit', true, ['style' => 'background: #082567; border-color: #082567; color: #E0B849; padding: 5px 25px; border-radius: 8px;']); ?>
            </form>
        </div>
        <?php $this->adminStyles(); ?>
        <?php
    }

    public function renderPolicies(): void
    {
        $optKey = 'stayflow_registry_policies';
        $options = get_option($optKey, []);
        
        $def_flex = "<p><strong>Standard Flexible Cancellation Policy</strong></p>";
        $def_non_ref = "<p><strong>✨ Non-Refundable</strong></p>";

        $flex = !empty($options['free_cancellation']) ? $options['free_cancellation'] : $def_flex;
        $non_ref = !empty($options['non_refundable']) ? $options['non_refundable'] : $def_non_ref;
        ?>
        <div class="wrap stayflow-admin-wrap">
            <h1 class="sf-page-title">🛡️ Cancellation Policies</h1>
            <?php settings_errors('stayflow_policies_group'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('stayflow_policies_group'); ?>
                <div class="sf-settings-grid">
                    <div class="sf-settings-card">
                        <h3>Flexible Stornierung</h3>
                        <?php wp_editor($flex, 'free_cancellation_editor', ['textarea_name' => $optKey . '[free_cancellation]', 'media_buttons' => false, 'textarea_rows' => 10, 'tinymce' => true]); ?>
                    </div>
                    <div class="sf-settings-card">
                        <h3>Nicht erstattbar</h3>
                        <?php wp_editor($non_ref, 'non_refundable_editor', ['textarea_name' => $optKey . '[non_refundable]', 'media_buttons' => false, 'textarea_rows' => 12, 'tinymce' => true]); ?>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <?php submit_button('Policies speichern', 'primary', 'submit', false, ['style' => 'background: #082567; border-color: #082567; color: #E0B849; padding: 5px 25px; border-radius: 8px;']); ?>
                </div>
            </form>
        </div>
        <?php $this->adminStyles(); ?>
        <?php
    }

    public function renderContentRegistry(): void
    {
        $optKey = 'stayflow_registry_content';
        $options = get_option($optKey, []);
        
        $def_voucher = "Check-in instructions...";
        $def_tax_single = "Tax notice single...";
        $def_tax_monthly = "Tax notice monthly...";
        
        $def_cp_a = "The contracting party is Stay4Fair.com...";
        $def_cp_b = "The contracting party for the accommodation is the owner...";

        $def_mod_a_title = "🔵 Modell A (Direkt)";
        $def_mod_b_title = "🟡 Modell B (Vermittlung)";
        $def_mod_a_desc  = "Stay4Fair zahlt die City-Tax...";
        $def_mod_b_desc  = "Sie zahlen die City-Tax...";
        $def_mod_footer  = "Der Wechsel des Modells wird geprüft...";

        $voucher_text = !empty($options['voucher_instructions']) ? $options['voucher_instructions'] : $def_voucher;
        $tax_single   = !empty($options['tax_notice_single']) ? $options['tax_notice_single'] : $def_tax_single;
        $tax_monthly  = !empty($options['tax_notice_monthly']) ? $options['tax_notice_monthly'] : $def_tax_monthly;
        
        $cp_a = !empty($options['contract_party_text_a']) ? $options['contract_party_text_a'] : $def_cp_a;
        $cp_b = !empty($options['contract_party_text_b']) ? $options['contract_party_text_b'] : $def_cp_b;

        $mod_a_title = !empty($options['model_a_compare_title']) ? $options['model_a_compare_title'] : $def_mod_a_title;
        $mod_b_title = !empty($options['model_b_compare_title']) ? $options['model_b_compare_title'] : $def_mod_b_title;
        $mod_a_desc  = !empty($options['model_a_compare_desc']) ? $options['model_a_compare_desc'] : $def_mod_a_desc;
        $mod_b_desc  = !empty($options['model_b_compare_desc']) ? $options['model_b_compare_desc'] : $def_mod_b_desc;
        $mod_footer  = !empty($options['model_compare_footer']) ? $options['model_compare_footer'] : $def_mod_footer;

        ?>
        <div class="wrap stayflow-admin-wrap">
            <h1 class="sf-page-title">📝 Content Registry</h1>
            <?php settings_errors('stayflow_content_group'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('stayflow_content_group'); ?>
                <div class="sf-settings-grid">
                    
                    <div class="sf-settings-card">
                        <h3>🔄 Popup: Modellwechsel</h3>
                        <table class="form-table">
                            <tr><th>Titel A</th><td><input type="text" name="<?php echo $optKey; ?>[model_a_compare_title]" value="<?php echo esc_attr($mod_a_title); ?>" class="large-text"></td></tr>
                            <tr><th>Titel B</th><td><input type="text" name="<?php echo $optKey; ?>[model_b_compare_title]" value="<?php echo esc_attr($mod_b_title); ?>" class="large-text"></td></tr>
                            <tr><th>Footer</th><td><input type="text" name="<?php echo $optKey; ?>[model_compare_footer]" value="<?php echo esc_attr($mod_footer); ?>" class="large-text"></td></tr>
                        </table>
                        <h4>Beschreibung A</h4>
                        <?php wp_editor($mod_a_desc, 'mod_a_desc_editor', ['textarea_name' => $optKey . '[model_a_compare_desc]', 'media_buttons' => false, 'textarea_rows' => 5, 'tinymce' => true]); ?>
                        <h4>Beschreibung B</h4>
                        <?php wp_editor($mod_b_desc, 'mod_b_desc_editor', ['textarea_name' => $optKey . '[model_b_compare_desc]', 'media_buttons' => false, 'textarea_rows' => 5, 'tinymce' => true]); ?>
                    </div>

                    <div class="sf-settings-card">
                        <h3>🤝 Contracting Party: Modell A</h3>
                        <?php wp_editor($cp_a, 'cp_text_a_editor', ['textarea_name' => $optKey . '[contract_party_text_a]', 'media_buttons' => false, 'textarea_rows' => 4, 'tinymce' => true]); ?>
                    </div>

                    <div class="sf-settings-card">
                        <h3>🤝 Contracting Party: Modell B</h3>
                        <?php wp_editor($cp_b, 'cp_text_b_editor', ['textarea_name' => $optKey . '[contract_party_text_b]', 'media_buttons' => false, 'textarea_rows' => 4, 'tinymce' => true]); ?>
                    </div>

                    <div class="sf-settings-card">
                        <h3>📄 Gast-Voucher PDF</h3>
                        <?php wp_editor($voucher_text, 'voucher_instructions_editor', ['textarea_name' => $optKey . '[voucher_instructions]', 'media_buttons' => false, 'textarea_rows' => 8, 'tinymce' => true]); ?>
                    </div>

                    <div class="sf-settings-card">
                        <h3>📄 Owner Buchungsbestätigung</h3>
                        <?php wp_editor($tax_single, 'tax_notice_single_editor', ['textarea_name' => $optKey . '[tax_notice_single]', 'media_buttons' => false, 'textarea_rows' => 12, 'tinymce' => true]); ?>
                    </div>

                    <div class="sf-settings-card">
                        <h3>📄 Owner Monatsabrechnung</h3>
                        <?php wp_editor($tax_monthly, 'tax_notice_monthly_editor', ['textarea_name' => $optKey . '[tax_notice_monthly]', 'media_buttons' => false, 'textarea_rows' => 12, 'tinymce' => true]); ?>
                    </div>

                </div>
                <div style="margin-top: 20px;">
                    <?php submit_button('Content speichern', 'primary', 'submit', false, ['style' => 'background: #082567; border-color: #082567; color: #E0B849; padding: 5px 25px; border-radius: 8px;']); ?>
                </div>
            </form>
        </div>
        <?php $this->adminStyles(); ?>
        <?php
    }

    private function adminStyles(): void
    {
        ?>
        <style>
            .stayflow-admin-wrap { max-width: 1000px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sf-page-title { color: #082567; font-weight: 800; margin-bottom: 5px; }
            .sf-settings-grid { display: grid; grid-template-columns: 1fr; gap: 30px; margin-bottom: 20px; }
            .sf-settings-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
            .sf-settings-card h3 { margin: 0 0 10px 0; color: #082567; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
            .form-table th { font-weight: 600; color: #1e293b; padding-left: 0; width: 200px; }
            .regular-text, .large-text { border-radius: 6px; border: 1px solid #cbd5e1; padding: 6px 10px; width: 100%; box-sizing: border-box; }
        </style>
        <?php
    }

    private function adminDashboardStyles(): void
    {
        ?>
        <style>
            .stayflow-dashboard { max-width: 1200px; } .stayflow-dashboard .notice { display: none; }
            .sf-hero { background: #212F54; color: white; padding: 30px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
            .sf-hero h1 { margin: 0 0 6px; font-size: 26px; color: #ffffff !important; }
            .sf-version { background: #E0B849; color: #111; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .sf-kpi-grid, .sf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .sf-kpi { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
            .sf-card { display: block; background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.06); transition: all 0.2s ease; text-decoration: none; color: inherit; border: 1px solid #e2e8f0; }
            .sf-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(0,0,0,0.12); }
            .sf-icon { font-size: 26px; margin-bottom: 12px; }
            .sf-badge { display: inline-block; margin-top: 10px; padding: 4px 10px; font-size: 11px; border-radius: 20px; font-weight: 600; background: #e6f4ea; color: #1e7e34; }
        </style>
        <?php
    }
}

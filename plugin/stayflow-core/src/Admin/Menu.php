<?php

declare(strict_types=1);

namespace StayFlow\Admin;

use StayFlow\Registry\ModuleRegistry;
use StayFlow\Settings\SettingsStore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 2.18.0
 * RU: Управление меню. Добавлена настройка Support E-Mail.
 * EN: Menu management. Support E-Mail setting added.
 */
final class Menu
{
    public function register(): void
    {
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
                        <h3>🎧 Support & Kontakt (Owner Portal)</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label>Support E-Mail</label></th>
                                <td>
                                    <input type="email" name="<?php echo $optKey; ?>[support_email]" value="<?php echo esc_attr((string)($options['support_email'] ?? '')); ?>" class="regular-text">
                                    <p class="description">An diese E-Mail werden Support-Anfragen der Eigentümer gesendet. Bleibt dieses Feld leer, wird die Standard-Admin-E-Mail (<?php echo esc_html(get_option('admin_email')); ?>) verwendet.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="sf-settings-card">
                        <h3>✉️ Dokument: Onboarding</h3>
                        <table class="form-table">
                            <tr><th scope="row"><label>Betreff</label></th><td><input type="text" name="<?php echo $optKey; ?>[onboarding][verify_email_sub]" value="<?php echo esc_attr((string)$options['onboarding']['verify_email_sub']); ?>" class="large-text"></td></tr>
                            <tr><th scope="row"><label>Nachricht</label></th><td><textarea name="<?php echo $optKey; ?>[onboarding][verify_email_body]" rows="5" class="large-text"><?php echo esc_textarea((string)$options['onboarding']['verify_email_body']); ?></textarea></td></tr>
                        </table>
                    </div>

                    <div class="sf-settings-card">
                        <h3>📄 Dokument: Owner E-Mail</h3>
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

    public function renderPolicies(): void { /* ... Оставлено как было, код не менялся ... */ }
    public function renderContentRegistry(): void { /* ... Оставлено как было, код не менялся ... */ }
    public function renderSiteNotice(): void { /* ... Оставлено как было, код не менялся ... */ }

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

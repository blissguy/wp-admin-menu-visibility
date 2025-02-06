<?php
/*
Plugin Name: Admin Menu Toggler with Dropdowns
Description: Provides a settings page with dropdown toggles to hide/show admin menu and admin bar items. Uses inline CSS and JS for modern styling.
Version: 1.1
Author: Your Name
License: GPL2
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define our option name.
define('AMT_OPTION', 'amt_options');

/* ==========================================================================
   OPTIONS & SANITIZATION
   ========================================================================== */

// Store all available menu items when viewing settings page.
function amt_store_available_menus()
{
    if (! is_admin() || ! isset($_GET['page']) || $_GET['page'] !== 'amt-settings') {
        return;
    }

    $options = amt_get_options();
    $stored_menus = isset($options['available_menus']) ? $options['available_menus'] : array();

    global $menu, $submenu;
    $current_menus = array();

    // Loop through main menu items.
    foreach ($menu as $item) {
        $menu_slug = isset($item[2]) ? $item[2] : '';

        // Skip our own settings and empty slugs
        if (empty($menu_slug) || $menu_slug === 'amt-settings') {
            continue;
        }

        // Check if this is a separator
        $is_separator = empty($item[0]) ||
            $item[0] === '' ||
            (isset($item[4]) && (
                strpos($item[4], 'separator') !== false ||
                strpos($item[4], 'wp-menu-separator') !== false
            )) ||
            $item[0] === '--' ||
            preg_match('/^-+$/', $item[0]);

        if ($is_separator) {
            // Create a unique slug for the separator if none exists
            if (empty($menu_slug)) {
                $menu_slug = 'separator-' . uniqid();
            }

            $current_menus[$menu_slug] = array(
                'title'   => 'Separator --',
                'submenu' => array(),
                'is_separator' => true
            );
            continue;
        }

        // Get the menu title and clean it
        $menu_title = isset($item[0]) ? wp_strip_all_tags($item[0]) : '';

        // Clean up menu titles (remove update counts and bubble notifications)
        $menu_title = preg_replace('/<span.*?>.*?<\/span>/', '', $menu_title); // Remove any span tags
        $menu_title = preg_replace('/\s*\(\d+\)/', '', $menu_title); // Remove counts in parentheses
        $menu_title = preg_replace('/\s+\d+$/', '', $menu_title); // Remove trailing numbers
        $menu_title = preg_replace('/\s+Updates$/', '', $menu_title); // Remove "Updates" text
        $menu_title = preg_replace('/\s+\d+(?=\s|$)/', '', $menu_title); // Remove numbers that appear at the end or before spaces
        $menu_title = trim($menu_title); // Clean up any remaining whitespace

        // Skip if we end up with an empty title or separator
        if (empty($menu_title) || $menu_title === '--') {
            continue;
        }

        $current_menus[$menu_slug] = array(
            'title'   => $menu_title,
            'submenu' => array()
        );

        // Loop through submenu items.
        if (isset($submenu[$menu_slug]) && is_array($submenu[$menu_slug])) {
            foreach ($submenu[$menu_slug] as $sub_item) {
                $sub_slug = isset($sub_item[2]) ? $sub_item[2] : '';
                if (empty($sub_slug)) {
                    continue;
                }

                $sub_title = isset($sub_item[0]) ? wp_strip_all_tags($sub_item[0]) : '';

                // Clean up submenu titles
                $sub_title = preg_replace('/<span.*?>.*?<\/span>/', '', $sub_title); // Remove any span tags
                $sub_title = preg_replace('/\s*\(\d+\)/', '', $sub_title); // Remove counts in parentheses
                $sub_title = preg_replace('/\s+\d+$/', '', $sub_title); // Remove trailing numbers
                $sub_title = preg_replace('/\s+Updates$/', '', $sub_title); // Remove "Updates" text
                $sub_title = trim($sub_title); // Clean up any remaining whitespace

                // Skip empty titles
                if (!empty($sub_title)) {
                    $current_menus[$menu_slug]['submenu'][$sub_slug] = $sub_title;
                }
            }
        }
    }

    // Merge current menus with stored menus to preserve hidden items
    foreach ($current_menus as $menu_slug => $menu_data) {
        if (!isset($stored_menus[$menu_slug])) {
            $stored_menus[$menu_slug] = $menu_data;
        } else {
            // Preserve the title from current menu if available
            if (isset($menu_data['title'])) {
                $stored_menus[$menu_slug]['title'] = $menu_data['title'];
            }

            // Merge submenu items
            if (isset($menu_data['submenu']) && is_array($menu_data['submenu'])) {
                foreach ($menu_data['submenu'] as $sub_slug => $sub_title) {
                    $stored_menus[$menu_slug]['submenu'][$sub_slug] = $sub_title;
                }
            }
        }
    }

    // Update options with merged menus
    $options['available_menus'] = $stored_menus;
    update_option(AMT_OPTION, $options);
}
add_action('admin_init', 'amt_store_available_menus');

// Store all available admin bar items when viewing settings page.
function amt_store_available_admin_bar_items()
{
    if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'amt-settings') {
        return;
    }

    $options = amt_get_options();
    $stored_admin_bar = isset($options['available_admin_bar']) ? $options['available_admin_bar'] : array();

    global $wp_admin_bar;
    if (!isset($wp_admin_bar) || !($wp_admin_bar instanceof WP_Admin_Bar)) {
        require_once(ABSPATH . WPINC . '/class-wp-admin-bar.php');
        $wp_admin_bar = new WP_Admin_Bar;
        do_action('admin_bar_init');
    }

    $nodes = $wp_admin_bar->get_nodes();
    $current_admin_bar = array();

    if (is_array($nodes)) {
        foreach ($nodes as $node) {
            $current_admin_bar[$node->id] = strip_tags($node->title);
        }
    }

    // Merge current items with stored items
    $merged_admin_bar = array_merge($stored_admin_bar, $current_admin_bar);

    // Update option only if there are changes
    if ($stored_admin_bar !== $merged_admin_bar) {
        $options['available_admin_bar'] = $merged_admin_bar;
        update_option(AMT_OPTION, $options);
    }
}
add_action('admin_init', 'amt_store_available_admin_bar_items', 9); // Run before other admin_init actions

// Get plugin options with default values.
function amt_get_options()
{
    $defaults = array(
        'admin_menus'    => array(
            'main' => array(),
            'sub'  => array(),
        ),
        'admin_bar'      => array(),
        'available_menus' => array(),
        'available_admin_bar' => array()
    );
    return get_option(AMT_OPTION, $defaults);
}

// Sanitize and validate settings input.
function amt_sanitize_options($input)
{
    // Get existing options to preserve previously set values and available items
    $existing_options = amt_get_options();

    $output = $existing_options; // Start with existing options

    // Ensure admin_menus and admin_bar arrays exist in output, if not already present in existing options
    if (!isset($output['admin_menus']) || !is_array($output['admin_menus'])) {
        $output['admin_menus'] = array('main' => array(), 'sub' => array());
    }
    if (!isset($output['admin_bar']) || !is_array($output['admin_bar'])) {
        $output['admin_bar'] = array();
    }


    // Process main menu toggles.
    if (isset($input['admin_menus']['main']) && is_array($input['admin_menus']['main'])) {
        foreach ($input['admin_menus']['main'] as $key => $value) {
            $output['admin_menus']['main'][$key] = (intval($value) === 1) ? 1 : 0;
        }
    } else {
        $output['admin_menus']['main'] = isset($existing_options['admin_menus']['main']) ? $existing_options['admin_menus']['main'] : array(); // Retain existing if input is not set
    }

    // Process submenu toggles.
    if (isset($input['admin_menus']['sub']) && is_array($input['admin_menus']['sub'])) {
        foreach ($input['admin_menus']['sub'] as $key => $value) {
            $output['admin_menus']['sub'][$key] = (intval($value) === 1) ? 1 : 0;
        }
    } else {
        $output['admin_menus']['sub'] = isset($existing_options['admin_menus']['sub']) ? $existing_options['admin_menus']['sub'] : array(); // Retain existing if input is not set
    }

    // Process admin bar toggles.
    if (isset($input['admin_bar']) && is_array($input['admin_bar'])) {
        foreach ($input['admin_bar'] as $key => $value) {
            $output['admin_bar'][$key] = (intval($value) === 1) ? 1 : 0;
        }
    } else {
        $output['admin_bar'] = isset($existing_options['admin_bar']) ? $existing_options['admin_bar'] : array(); // Retain existing if input is not set
    }

    return $output;
}


/* ==========================================================================
   SETTINGS REGISTRATION & PAGE
   ========================================================================== */

// Register settings, sections, and fields.
function amt_register_settings()
{
    register_setting('amt_options_group', AMT_OPTION, 'amt_sanitize_options');

    add_settings_section(
        'amt_settings_section',
        'Menu Toggle Settings',
        'amt_settings_section_callback',
        'amt-settings'
    );

    add_settings_field(
        'amt_admin_menu',
        'Admin Menu Items',
        'amt_admin_menu_callback',
        'amt-settings',
        'amt_settings_section'
    );

    add_settings_field(
        'amt_admin_bar',
        'Admin Bar Items',
        'amt_admin_bar_callback',
        'amt-settings',
        'amt_settings_section'
    );
}
add_action('admin_init', 'amt_register_settings');

// Settings section callback.
function amt_settings_section_callback()
{
    echo '<p>Select which admin menu and admin bar items to show. (Unchecked items will be hidden.)</p>';
}

// Render the admin menus field with dropdown toggles.
function amt_admin_menu_callback()
{
    $options = amt_get_options();
    $available_menus = isset($options['available_menus']) ? $options['available_menus'] : array();

    echo '<div class="amt-menu-container">';

    foreach ($available_menus as $menu_slug => $menu_data) {
        // Skip our own settings page.
        if ('amt-settings' === $menu_slug) {
            continue;
        }

        // Menu is visible by default unless explicitly set to hide.
        $current = ! isset($options['admin_menus']['main'][$menu_slug]) || $options['admin_menus']['main'][$menu_slug] === 1;
        $checked = $current ? 'checked' : '';

        echo '<div class="amt-menu-item' . (isset($menu_data['is_separator']) ? ' amt-separator-item' : '') . '">';
        echo '<div class="amt-menu-header">';
        // Hidden input to ensure unchecked boxes send a value.
        echo '<input type="hidden" name="amt_options[admin_menus][main][' . esc_attr($menu_slug) . ']" value="0">';
        echo '<label class="amt-toggle">';
        echo '<input type="checkbox" name="amt_options[admin_menus][main][' . esc_attr($menu_slug) . ']" ' . $checked . ' value="1">';
        echo '<span class="amt-slider"></span>';
        echo '</label>';
        echo '<span class="amt-menu-title' . (isset($menu_data['is_separator']) ? ' amt-separator-title' : '') . '">' .
            esc_html($menu_data['title']) .
            (isset($menu_data['is_separator']) ? ' <em>(Separator)</em>' : '') .
            '</span>';

        if (! empty($menu_data['submenu']) && !isset($menu_data['is_separator'])) {
            echo '<button type="button" class="amt-dropdown-arrow"></button>';
        }
        echo '</div>';

        // Submenu items.
        if (! empty($menu_data['submenu'])) {
            echo '<div class="amt-submenu">';
            foreach ($menu_data['submenu'] as $sub_slug => $sub_title) {
                $combined = $menu_slug . '_' . $sub_slug;

                // Submenu is visible by default unless explicitly set to hide.
                $sub_current = ! isset($options['admin_menus']['sub'][$combined]) || $options['admin_menus']['sub'][$combined] === 1;
                $sub_checked = $sub_current ? 'checked' : '';

                echo '<div class="amt-submenu-item">';
                // Hidden input for the unchecked state.
                echo '<input type="hidden" name="amt_options[admin_menus][sub][' . esc_attr($combined) . ']" value="0">';
                echo '<label class="amt-toggle">';
                echo '<input type="checkbox" name="amt_options[admin_menus][sub][' . esc_attr($combined) . ']" ' . $sub_checked . ' value="1">';
                echo '<span class="amt-slider"></span>';
                echo '</label>';
                echo '<span class="amt-menu-title">' . esc_html($sub_title) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

// Render the admin bar items field.
function amt_admin_bar_callback()
{
    $options = amt_get_options();
    $available_admin_bar = isset($options['available_admin_bar']) ? $options['available_admin_bar'] : array();

    echo '<ul class="wlcms-menu-list admin-bar-list">';
    foreach ($available_admin_bar as $node_id => $node_title) {
        $current    = !isset($options['admin_bar'][$node_id]) || $options['admin_bar'][$node_id] === 1;
        $checked    = $current ? 'checked="checked"' : '';
        $input_id   = 'admin_bar_' . sanitize_title($node_id);

        echo '<li>';
        echo '<input type="hidden" name="amt_options[admin_bar][' . esc_attr($node_id) . ']" value="0">';
        echo '<input class="wlcms-toggle wlcms-toggle-light" id="' . esc_attr($input_id) . '" name="amt_options[admin_bar][' . esc_attr($node_id) . ']" value="1" type="checkbox" ' . $checked . '>';
        echo '<label class="wlcms-toggle-btn" for="' . esc_attr($input_id) . '"></label>';
        echo '<label class="toggle-label" for="' . esc_attr($input_id) . '">' . esc_html($node_title) . ' (' . esc_html($node_id) . ')</label>';
        echo '</li>';
    }
    echo '</ul>';
}

// Add the plugin settings page under Settings.
function amt_add_settings_page()
{
    add_options_page(
        'Admin Menu Toggler Settings',
        'Admin Menu Toggler',
        'manage_options',
        'amt-settings',
        'amt_render_settings_page'
    );
}
add_action('admin_menu', 'amt_add_settings_page');

// Render the settings page with modern styling.
function amt_render_settings_page()
{
?>
    <div class="wrap amt-settings-wrap">
        <h1><?php esc_html_e('Admin Menu Toggler Settings', 'amt'); ?></h1>

        <style>
            /* Container styling */
            .amt-menu-container {
                max-width: 600px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                padding: 20px;
            }

            /* Menu item styling */
            .amt-menu-item {
                margin-bottom: 10px;
            }

            /* Separator styling */
            .amt-separator-item {
                opacity: 0.8;
                background: #f8f9fa;
                border-radius: 4px;
            }

            .amt-separator-title {
                color: #666;
            }

            .amt-separator-title em {
                font-style: italic;
                font-size: 0.9em;
                color: #888;
            }

            /* Menu header styling */
            .amt-menu-header {
                display: flex;
                align-items: center;
                padding: 8px;
                cursor: pointer;
            }

            /* Menu title styling */
            .amt-menu-title {
                margin-left: 10px;
                font-size: 14px;
                color: #1d2327;
            }

            /* Submenu container styling */
            .amt-submenu {
                display: none;
                margin-left: 30px;
                padding: 5px 0;
            }

            /* Submenu item styling */
            .amt-submenu-item {
                display: flex;
                align-items: center;
                padding: 8px;
            }

            /* Toggle switch styling */
            .amt-toggle {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 20px;
            }

            .amt-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .amt-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 20px;
            }

            .amt-slider:before {
                position: absolute;
                content: "";
                height: 16px;
                width: 16px;
                left: 2px;
                bottom: 2px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            .amt-toggle input:checked+.amt-slider {
                background-color: #2196F3;
            }

            .amt-toggle input:checked+.amt-slider:before {
                transform: translateX(20px);
            }

            /* Dropdown arrow styling */
            .amt-dropdown-arrow {
                margin-left: auto;
                width: 24px;
                height: 24px;
                background: transparent;
                border: none;
                cursor: pointer;
                position: relative;
            }

            .amt-dropdown-arrow:before {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                border-left: 5px solid transparent;
                border-right: 5px solid transparent;
                border-top: 5px solid #50575e;
                transition: transform 0.3s;
            }

            .amt-dropdown-arrow.active:before {
                transform: translate(-50%, -50%) rotate(180deg);
            }
        </style>

        <form method="post" action="options.php">
            <?php
            settings_fields('amt_options_group');
            do_settings_sections('amt-settings');
            submit_button();
            ?>
        </form>

        <script>
            jQuery(document).ready(function($) {
                $('.amt-menu-header').on('click', function(e) {
                    if (!$(e.target).is('input')) {
                        var $submenu = $(this).next('.amt-submenu');
                        var $arrow = $(this).find('.amt-dropdown-arrow');

                        if ($submenu.length) {
                            $submenu.slideToggle();
                            $arrow.toggleClass('active');
                        }
                    }
                });
            });
        </script>
    </div>
<?php
}

/* ==========================================================================
   REMOVAL LOGIC
   ========================================================================== */

// Remove admin menu items based on saved settings.
function amt_remove_admin_menus()
{
    $options = amt_get_options();
    global $menu, $submenu;

    // Remove top-level menus if explicitly set to hide (value = 0).
    if (is_array($menu)) {
        foreach ($menu as $item) {
            $menu_slug = isset($item[2]) ? $item[2] : '';
            if (empty($menu_slug) || 'amt-settings' === $menu_slug) {
                continue;
            }
            if (isset($options['admin_menus']['main'][$menu_slug]) && $options['admin_menus']['main'][$menu_slug] === 0) {
                remove_menu_page($menu_slug);
            }
        }
    }

    // Remove submenus if explicitly set to hide.
    if (is_array($submenu)) {
        foreach ($submenu as $parent_slug => $sub_items) {
            foreach ($sub_items as $sub_item) {
                $sub_slug = isset($sub_item[2]) ? $sub_item[2] : '';
                $combined = $parent_slug . '_' . $sub_slug;
                if (isset($options['admin_menus']['sub'][$combined]) && $options['admin_menus']['sub'][$combined] === 0) {
                    remove_submenu_page($parent_slug, $sub_slug);
                }
            }
        }
    }
}
add_action('admin_menu', 'amt_remove_admin_menus', 999);

// Remove admin bar items based on saved settings.
function amt_remove_admin_bar_items($wp_admin_bar)
{
    $options = amt_get_options();
    if (! isset($options['admin_bar']) || ! is_array($options['admin_bar'])) {
        $options['admin_bar'] = array();
    }
    foreach ($options['admin_bar'] as $node_id => $visible) {
        if ($visible != 1) {
            $wp_admin_bar->remove_node($node_id);
        }
    }
}
add_action('admin_bar_menu', 'amt_remove_admin_bar_items', 999);

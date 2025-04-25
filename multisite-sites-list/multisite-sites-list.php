<?php
/*
Plugin Name: Multisite Sites List
Description: Displays a list of sites in a WordPress Multisite network using a shortcode and widget.
Version: 1.0
Author: Andrew Arutunyan & Grok
Network: true
*/

// Проверка, чтобы плагин работал только в Multisite
if (!is_multisite()) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('This plugin requires WordPress Multisite.');
}

// Регистрация шорткода
add_shortcode('multisite_list', 'msl_display_sites_list');

function msl_display_sites_list($atts) {
    $atts = shortcode_atts(array(
        'order' => 'name', // Порядок сортировки: name, id, registered
        'limit' => 100,    // Лимит сайтов
    ), $atts, 'multisite_list');

    // Получение списка сайтов
    $sites = get_sites(array(
        'number' => absint($atts['limit']),
        'orderby' => sanitize_key($atts['order']),
        'order' => 'ASC',
        'public' => 1,
    ));

    if (empty($sites)) {
        return '<p>' . __('No sites found.', 'multisite-sites-list') . '</p>';
    }

    // Формирование списка
    $output = '<ul class="multisite-list">';
    foreach ($sites as $site) {
        $site_details = get_blog_details($site->blog_id);
        $site_name = $site_details->blogname;
        $site_url = $site_details->siteurl;
        $output .= '<li><a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></li>';
    }
    $output .= '</ul>';

    return $output;
}

// Добавление стилей для списка
add_action('wp_enqueue_scripts', 'msl_enqueue_styles');

function msl_enqueue_styles() {
    wp_enqueue_style(
        'multisite-list',
        plugins_url('multisite-list.css', __FILE__),
        array(),
        '1.0'
    );
}

// Создание файла стилей при активации плагина
register_activation_hook(__FILE__, 'msl_create_styles_file');

function msl_create_styles_file() {
    $css_file = plugin_dir_path(__FILE__) . 'multisite-list.css';
    if (!file_exists($css_file)) {
        $default_css = "
.multisite-list {
    list-style: none;
    padding: 0;
}
.multisite-list li {
    margin-bottom: 10px;
}
.multisite-list a {
    text-decoration: none;
    color: #0073aa;
}
.multisite-list a:hover {
    text-decoration: underline;
}
";
        file_put_contents($css_file, $default_css);
    }
}

// Регистрация виджета
add_action('widgets_init', 'msl_register_widget');

function msl_register_widget() {
    register_widget('MSL_Sites_List_Widget');
}

class MSL_Sites_List_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'msl_sites_list_widget',
            __('Multisite Sites List', 'multisite-sites-list'),
            array('description' => __('Displays a list of Multisite network sites.', 'multisite-sites-list'))
        );
    }

    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? apply_filters('widget_title', $instance['title']) : '';
        $limit = !empty($instance['limit']) ? absint($instance['limit']) : 10;

        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        echo msl_display_sites_list(array('limit' => $limit));
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $limit = !empty($instance['limit']) ? $instance['limit'] : 10;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Title:', 'multisite-sites-list'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">
                <?php _e('Number of sites to show:', 'multisite-sites-list'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('limit')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" min="1"
                   value="<?php echo esc_attr($limit); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = !empty($new_instance['limit']) ? absint($new_instance['limit']) : 10;
        return $instance;
    }
}

// Поддержка переводов
add_action('plugins_loaded', 'msl_load_textdomain');

function msl_load_textdomain() {
    load_plugin_textdomain('multisite-sites-list', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
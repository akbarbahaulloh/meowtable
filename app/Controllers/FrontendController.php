<?php
namespace Meowtable\Controllers;

class FrontendController {

    public static function init() {
        add_shortcode('meowtable', [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('wp_ajax_meowtable_get_data', [__CLASS__, 'ajax_get_data']);
        add_action('wp_ajax_nopriv_meowtable_get_data', [__CLASS__, 'ajax_get_data']);
    }

    public static function enqueue_frontend_assets() {
        wp_enqueue_style('meowtable-css', MEOWTABLE_PLUGIN_URL . 'assets/css/meowtable.css', [], MEOWTABLE_VERSION);
        wp_enqueue_script('meowtable-js', MEOWTABLE_PLUGIN_URL . 'assets/js/meowtable.js', ['jquery'], MEOWTABLE_VERSION, true);
        
        wp_localize_script('meowtable-js', 'meowtable_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('meowtable_nonce')
        ]);
    }

    public static function render_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'meowtable');
        $id = intval($atts['id']);

        if (!$id) {
            return '<p>Meowtable: No ID specified.</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'meowtables';
        $table = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

        if (!$table) {
            return '<p>Meowtable: Table not found.</p>';
        }

        $settings = json_decode($table->settings, true);
        $defaults = [
            'columns' => [],
            'data_source' => 'wp_posts',
            'post_types' => ['post'],
            'categories' => '',
            'tags' => '',
            'items_per_page' => 10,
            'enable_lazy_load' => true,
            'enable_search' => true,
            'enable_cat_filter' => false,
            'enable_tag_filter' => false
        ];
        $settings = wp_parse_args($settings, $defaults);

        if (empty($settings['columns'])) {
            return '<p>Meowtable: Table is not configured yet.</p>';
        }

        // Prepare Query
        $args = [
            'post_type' => !empty($settings['post_types']) ? $settings['post_types'] : 'post',
            'posts_per_page' => $settings['enable_lazy_load'] ? intval($settings['items_per_page']) : -1,
            'post_status' => 'publish',
            'paged' => 1
        ];

        // Tax Queries (Categories and Tags)
        $tax_query = [];
        if (!empty($settings['categories'])) {
            $cats = $settings['categories'];
            if (!is_array($cats)) {
                $cats = array_map('trim', explode(',', $cats));
            }
            $tax_query[] = [
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $cats,
            ];
        }
        if (!empty($settings['tags'])) {
            $tags = array_map('trim', explode(',', $settings['tags']));
            $tax_query[] = [
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => $tags,
            ];
        }
        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);

        $all_categories = [];
        $all_tags = [];
        $row_data = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Collect Categories
                $cats = get_the_category($post_id);
                $allowed_cats = !empty($settings['categories']) ? (array)$settings['categories'] : [];
                foreach($cats as $cat) {
                    if (empty($allowed_cats) || in_array($cat->slug, $allowed_cats)) {
                        $all_categories[$cat->slug] = $cat->name;
                    }
                }

                // Collect Tags
                $tags = get_the_tags($post_id);
                if ($tags) {
                    $allowed_tags = !empty($settings['tags']) ? array_map('trim', explode(',', $settings['tags'])) : [];
                    foreach($tags as $tag) {
                        if (empty($allowed_tags) || in_array($tag->slug, $allowed_tags)) {
                            $all_tags[$tag->slug] = $tag->name;
                        }
                    }
                }

                $columns_html = '';
                foreach ($settings['columns'] as $col) {
                    $columns_html .= '<td>' . self::get_post_field_value($col['key'], $col['type']) . '</td>';
                }

                $row_data[] = [
                    'cats' => implode(',', array_keys($all_categories)), // Wait, this is wrong, I need specific post cats
                    'post_cats' => implode(',', array_keys(array_flip(wp_list_pluck($cats, 'slug')))),
                    'post_tags' => $tags ? implode(',', array_keys(array_flip(wp_list_pluck($tags, 'slug')))) : '',
                    'html' => $columns_html
                ];
            }
            wp_reset_postdata();
        }

        asort($all_categories);
        asort($all_tags);

        ob_start();
        ?>
        <div class="meowtable-container meowtable-id-<?php echo esc_attr($id); ?>" 
             data-table_id="<?php echo esc_attr($id); ?>" 
             data-lazy="<?php echo $settings['enable_lazy_load'] ? '1' : '0'; ?>"
             data-per_page="<?php echo esc_attr($settings['items_per_page']); ?>">
            <div class="meowtable-header">
                <div class="meowtable-filters">
                    <?php if (!empty($settings['enable_cat_filter']) && !empty($all_categories)): ?>
                        <select class="meowtable-filter-select meowtable-filter-cat">
                            <option value="">All Categories</option>
                            <?php foreach($all_categories as $slug => $name): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <?php if (!empty($settings['enable_tag_filter']) && !empty($all_tags)): ?>
                        <select class="meowtable-filter-select meowtable-filter-tag">
                            <option value="">All Tags</option>
                            <?php foreach($all_tags as $slug => $name): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <?php if (!empty($settings['enable_search'])): ?>
                <div class="meowtable-search-wrapper">
                    <input type="text" class="meowtable-search" placeholder="Search data...">
                </div>
                <?php endif; ?>
            </div>
            <table class="meowtable">
                <thead>
                    <tr>
                        <?php foreach($settings['columns'] as $col): ?>
                            <th><?php echo esc_html($col['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="meowtable-body">
                    <?php if (!empty($row_data)): foreach ($row_data as $row): ?>
                        <tr data-categories="<?php echo esc_attr($row['post_cats']); ?>" data-tags="<?php echo esc_attr($row['post_tags']); ?>">
                            <?php echo $row['html']; ?>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="<?php echo count($settings['columns']); ?>">No matching data found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($settings['enable_lazy_load']): ?>
                <div class="meowtable-footer">
                    <div class="meowtable-pagination" data-total_pages="<?php echo esc_attr($query->max_num_pages); ?>">
                        <!-- Pagination will be rendered by JS -->
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="meowtable-loader" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function ajax_get_data() {
        check_ajax_referer('meowtable_nonce', 'nonce');
        
        $table_id = intval($_POST['table_id']);
        $page = intval($_POST['paged']);
        $search = sanitize_text_field($_POST['search']);
        $cat_filter = sanitize_text_field($_POST['cat']);
        $tag_filter = sanitize_text_field($_POST['tag']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'meowtables';
        $table = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $table_id));

        if (!$table) {
            wp_send_json_error('Table not found');
        }

        $settings = json_decode($table->settings, true);
        
        $args = [
            'post_type' => !empty($settings['post_types']) ? $settings['post_types'] : 'post',
            'posts_per_page' => intval($settings['items_per_page']),
            'paged' => $page,
            'post_status' => 'publish',
            's' => $search
        ];

        $tax_query = [];
        // Apply Query Filters (categories/tags set in table config)
        if (!empty($settings['categories'])) {
            $cats = $settings['categories'];
            if (!is_array($cats)) $cats = array_map('trim', explode(',', $cats));
            $tax_query[] = ['taxonomy' => 'category', 'field' => 'slug', 'terms' => $cats];
        }
        if (!empty($settings['tags'])) {
            $tags = array_map('trim', explode(',', $settings['tags']));
            $tax_query[] = ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags];
        }

        // Apply Frontend Dynamic Filters
        if (!empty($cat_filter)) {
            $tax_query[] = ['taxonomy' => 'category', 'field' => 'slug', 'terms' => $cat_filter];
        }
        if (!empty($tag_filter)) {
            $tax_query[] = ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tag_filter];
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);
        $html = '';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $cats = get_the_category($post_id);
                $tags = get_the_tags($post_id);
                
                $post_cats = implode(',', array_keys(array_flip(wp_list_pluck($cats, 'slug'))));
                $post_tags = $tags ? implode(',', array_keys(array_flip(wp_list_pluck($tags, 'slug')))) : '';

                $html .= '<tr data-categories="' . esc_attr($post_cats) . '" data-tags="' . esc_attr($post_tags) . '">';
                foreach ($settings['columns'] as $col) {
                    $html .= '<td>' . self::get_post_field_value($col['key'], $col['type']) . '</td>';
                }
                $html .= '</tr>';
            }
            wp_reset_postdata();
        } else {
            $html = '<tr><td colspan="' . count($settings['columns']) . '">No matching data found.</td></tr>';
        }

        wp_send_json_success([
            'html' => $html,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page
        ]);
    }

    private static function get_post_field_value($key, $type) {
        $post = get_post();
        $val = '';

        if ($type === 'html') {
            // Replace placeholders inside the HTML string with actual post data
            $html = $key;
            $html = str_replace('{{post_title}}', get_the_title(), $html);
            $html = str_replace('{{post_date}}', get_the_date(), $html);
            $html = str_replace('{{post_author}}', get_the_author(), $html);
            $html = str_replace('{{post_content}}', get_the_content(), $html);
            $html = str_replace('{{post_excerpt}}', get_the_excerpt(), $html);
            $html = str_replace('{{thumbnail}}', get_the_post_thumbnail(null, 'thumbnail'), $html);
            $html = str_replace('{{permalink}}', get_permalink(), $html);
            $html = str_replace('{{view_button}}', '<a href="'.get_permalink().'" class="btn">View Post</a>', $html);
            $html = str_replace('{{categories}}', get_the_category_list(', '), $html);
            $html = str_replace('{{tags}}', get_the_tag_list('', ', ', ''), $html);
            $html = str_replace('{{id}}', $post->ID, $html);
            
            $val = do_shortcode($html);
        } else {
            // standard post data
            switch ($key) {
                case 'post_title':
                    $val = get_the_title();
                    break;
                case 'post_date':
                    $val = get_the_date();
                    break;
                case 'post_author':
                    $val = get_the_author();
                    break;
                case 'post_content':
                    $val = get_the_content();
                    break;
                case 'post_excerpt':
                    $val = get_the_excerpt();
                    break;
                case 'thumbnail':
                    $val = get_the_post_thumbnail(null, 'thumbnail');
                    break;
                case 'permalink':
                    $val = '<a href="'.get_permalink().'">View</a>';
                    break;
                case 'view_button':
                    $val = '<a href="'.get_permalink().'" class="btn">View Post</a>';
                    break;
                case 'categories':
                    $val = get_the_category_list(', ');
                    break;
                case 'tags':
                    $val = get_the_tag_list('', ', ', '');
                    break;
                default:
                    // Maybe it's a meta key
                    $meta = get_post_meta($post->ID, $key, true);
                    $val = is_scalar($meta) ? $meta : '';
                    break;
            }
            $val = esc_html($val); 
            // If it's thumbnail, permalink, categories, tags, or view_button we actually want the HTML, so we unescape those specific ones
            if (in_array($key, ['thumbnail', 'permalink', 'categories', 'tags', 'view_button'])) {
                $val = html_entity_decode($val);
            }
        }

        return $val;
    }
}

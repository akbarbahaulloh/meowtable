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

        // Migrate legacy settings to new taxonomy structure
        if (empty($settings['taxonomy_filters'])) {
            if (!empty($settings['categories'])) {
                $categories = $settings['categories'];
                if (!is_array($categories)) $categories = array_map('trim', explode(',', $categories));
                $settings['taxonomy_filters']['category'] = [
                    'terms' => $categories,
                    'enable_frontend' => $settings['enable_cat_filter']
                ];
            }
            if (!empty($settings['tags'])) {
                $tags = array_map('trim', explode(',', $settings['tags']));
                if (!empty($tags[0])) {
                    $settings['taxonomy_filters']['post_tag'] = [
                        'terms' => $tags,
                        'enable_frontend' => $settings['enable_tag_filter']
                    ];
                }
            }
        }

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

        // Process Taxonomy Filters
        $tax_query = [];
        $active_filters = [];

        if (!empty($settings['taxonomy_filters'])) {
            foreach ($settings['taxonomy_filters'] as $tax_slug => $tax_cfg) {
                if (!empty($tax_cfg['terms'])) {
                    $tax_query[] = [
                        'taxonomy' => $tax_slug,
                        'field'    => 'slug',
                        'terms'    => (array)$tax_cfg['terms'],
                    ];
                }

                if (!empty($tax_cfg['enable_frontend'])) {
                    $all_terms = [];
                    if (!empty($tax_cfg['terms'])) {
                        foreach((array)$tax_cfg['terms'] as $slug) {
                            $term = get_term_by('slug', $slug, $tax_slug);
                            if ($term) $all_terms[$slug] = $term->name;
                        }
                    } else {
                        $terms = get_terms(['taxonomy' => $tax_slug, 'hide_empty' => true]);
                        foreach($terms as $t) $all_terms[$t->slug] = $t->name;
                    }

                    if (!empty($all_terms)) {
                        asort($all_terms, SORT_NATURAL | SORT_FLAG_CASE);
                        $tax_obj = get_taxonomy($tax_slug);
                        $active_filters[$tax_slug] = [
                            'label' => $tax_obj->label,
                            'terms' => $all_terms
                        ];
                    }
                }
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);
        $row_data = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Collect row info for JS filtering (even with lazy load, we might need these)
                $post_tax_data = [];
                $post_taxonomies = get_post_taxonomies($post_id);
                foreach($post_taxonomies as $ptax) {
                    $pterms = wp_get_post_terms($post_id, $ptax, ['fields' => 'slugs']);
                    $post_tax_data[$ptax] = implode(',', $pterms);
                }

                $columns_html = '';
                foreach ($settings['columns'] as $col) {
                    $columns_html .= '<td>' . self::get_post_field_value($col['key'], $col['type']) . '</td>';
                }

                $row_data[] = [
                    'tax_data' => $post_tax_data,
                    'html' => $columns_html
                ];
            }
            wp_reset_postdata();
        }

        ob_start();
        ?>
        <div class="meowtable-container meowtable-id-<?php echo esc_attr($id); ?>" 
             data-table_id="<?php echo esc_attr($id); ?>" 
             data-lazy="<?php echo $settings['enable_lazy_load'] ? '1' : '0'; ?>"
             data-per_page="<?php echo esc_attr($settings['items_per_page']); ?>">
            <div class="meowtable-header">
                <div class="meowtable-filters">
                    <?php foreach($active_filters as $tax_slug => $f_data): ?>
                        <select class="meowtable-filter-select" data-taxonomy="<?php echo esc_attr($tax_slug); ?>">
                            <option value="">All <?php echo esc_html($f_data['label']); ?></option>
                            <?php foreach($f_data['terms'] as $slug => $name): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
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
                    <?php if (!empty($row_data)): foreach ($row_data as $row): 
                        $attrs = '';
                        foreach($row['tax_data'] as $tax => $slugs) {
                            $attrs .= ' data-' . esc_attr($tax) . '="' . esc_attr($slugs) . '"';
                        }
                    ?>
                        <tr <?php echo $attrs; ?>>
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
                    <div class="meowtable-info">
                        Showing <span class="meowtable-count-current"><?php echo count($row_data); ?></span> of <span class="meowtable-count-total"><?php echo esc_html($query->found_posts); ?></span> records.
                    </div>
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
        $frontend_tax_filters = isset($_POST['taxonomies']) ? (array)$_POST['taxonomies'] : [];

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
        
        // Base Query Filters from Admin Settings
        if (!empty($settings['taxonomy_filters'])) {
            foreach ($settings['taxonomy_filters'] as $tax_slug => $tax_cfg) {
                if (!empty($tax_cfg['terms'])) {
                    $tax_query[] = [
                        'taxonomy' => $tax_slug,
                        'field'    => 'slug',
                        'terms'    => (array)$tax_cfg['terms'],
                    ];
                }
            }
        }

        // Dynamic Filters from Frontend Selects
        foreach ($frontend_tax_filters as $tax_slug => $selected_slug) {
            if (!empty($selected_slug)) {
                $tax_query[] = [
                    'taxonomy' => $tax_slug,
                    'field'    => 'slug',
                    'terms'    => $selected_slug
                ];
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);
        $html = '';
        $found_count = 0;

        if ($query->have_posts()) {
            $found_count = $query->post_count;
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $post_tax_data = [];
                $post_taxonomies = get_post_taxonomies($post_id);
                foreach($post_taxonomies as $ptax) {
                    $pterms = wp_get_post_terms($post_id, $ptax, ['fields' => 'slugs']);
                    $post_tax_data['data-' . $ptax] = implode(',', $pterms);
                }

                $tax_attr = '';
                foreach($post_tax_data as $attr => $val) {
                    $tax_attr .= ' ' . esc_attr($attr) . '="' . esc_attr($val) . '"';
                }

                $html .= '<tr' . $tax_attr . '>';
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
            'total_records' => $query->found_posts,
            'current_page' => $page,
            'count' => $found_count
        ]);
    }

    private static function get_post_field_value($key, $type) {
        $post = get_post();
        if (!$post) return '';

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
                case 'id':
                    $val = $post->ID;
                    break;
                default:
                    // Check if the key itself is a shortcode (e.g. [acf field="alamat"])
                    if (strpos($key, '[') !== false && strpos($key, ']') !== false) {
                        $val = do_shortcode($key);
                    } else {
                        // Maybe it's a meta key
                        $meta = get_post_meta($post->ID, $key, true);
                        $val = is_scalar($meta) ? $meta : '';
                        
                        // Process shortcodes inside meta values
                        if (is_string($val) && strpos($val, '[') !== false) {
                            $val = do_shortcode($val);
                        }
                    }
                    break;
            }

            // Only escape if it's not a known HTML field and doesn't contain HTML tags
            $is_html_field = in_array($key, ['thumbnail', 'permalink', 'categories', 'tags', 'view_button']);
            $has_html = is_string($val) && (strpos($val, '<') !== false || strpos($val, '>') !== false);

            if (!$is_html_field && !$has_html) {
                $val = esc_html($val);
            }
        }

        return apply_filters('meowtable_field_value', $val, $key, $post->ID);
    }
}

<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_id = intval($_GET['id']);
$table_name = $wpdb->prefix . 'meowtables';
$meowtable = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $table_id));

if (!$meowtable) {
    echo '<h2>Table not found.</h2>';
    return;
}

$settings = json_decode($meowtable->settings, true);
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

// Fetch all registered post types
$post_types = get_post_types(['public' => true], 'objects');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Edit Table: <?php echo esc_html($meowtable->title); ?></h1>
    <hr class="wp-header-end">

    <div style="background:#fff; padding:20px; margin-top:20px; border:1px solid #ccc;">
        <h3>Shortcode</h3>
        <p>Copy and paste this shortcode into your posts or pages:</p>
        <code><?php echo esc_html($meowtable->shortcode); ?></code>
    </div>

    <form id="meowtable-edit-form" style="margin-top:20px;">
        <input type="hidden" id="table_id" value="<?php echo esc_attr($table_id); ?>">
        
        <h2>Configuration</h2>
        <table class="form-table" style="background:#fff; padding:20px; border:1px solid #ccc;">
            <tr>
                <th scope="row"><label for="table_title">Title</label></th>
                <td><input type="text" id="table_title" class="regular-text" value="<?php echo esc_attr($meowtable->title); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="post_types">Select Post Types</label></th>
                <td>
                    <select id="post_types" multiple style="width:300px; height:100px;">
                        <?php foreach($post_types as $slug => $pt): ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php echo in_array($slug, (array)$settings['post_types']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Hold CTRL/CMD to select multiple.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Filter by Categories</label></th>
                <td>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa; border-radius: 4px;">
                        <?php 
                        $all_cats = get_categories(['hide_empty' => false]);
                        $selected_cats = (array)$settings['categories'];
                        // Handle comma string for backward compatibility
                        if (!is_array($settings['categories']) && !empty($settings['categories'])) {
                            $selected_cats = array_map('trim', explode(',', $settings['categories']));
                        }
                        
                        foreach($all_cats as $cat): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" class="cat-checkbox" value="<?php echo esc_attr($cat->slug); ?>" <?php checked(in_array($cat->slug, $selected_cats), true); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">Select categories to include in this table.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tags">Filter by Tags (Slugs)</label></th>
                <td>
                    <input type="text" id="tags" class="regular-text" value="<?php echo esc_attr(is_array($settings['tags']) ? implode(',', $settings['tags']) : $settings['tags']); ?>">
                    <p class="description">Comma-separated tag slugs.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Front-end Options</th>
                <td>
                    <label><input type="checkbox" id="enable_search" <?php checked($settings['enable_search'], true); ?>> Enable Search Bar</label><br>
                    <label><input type="checkbox" id="enable_cat_filter" <?php checked($settings['enable_cat_filter'], true); ?>> Enable Category Filter</label><br>
                    <label><input type="checkbox" id="enable_tag_filter" <?php checked($settings['enable_tag_filter'], true); ?>> Enable Tag Filter</label><br>
                    <label><input type="checkbox" id="enable_lazy_load" <?php checked($settings['enable_lazy_load'], true); ?>> Enable AJAX Lazy Load (Recommended)</label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="items_per_page">Items per Page</label></th>
                <td>
                    <input type="number" id="items_per_page" class="small-text" value="<?php echo esc_attr($settings['items_per_page']); ?>" min="1" max="100">
                </td>
            </tr>
        </table>

        <h2>Columns Configuration</h2>
        <div style="background:#fff; padding:20px; border:1px solid #ccc;">
            <p>Define which data maps to which column. Supported Data Keys for WP Posts: <code>post_title</code>, <code>post_date</code>, <code>post_author</code>, <code>post_content</code>, <code>post_excerpt</code>, <code>thumbnail</code>, <code>permalink</code>, <code>categories</code>, <code>tags</code>, <code>view_button</code>.</p>
            <p><strong>Pro Tip:</strong> Use the "HTML" type to mix content. Example: <code>&lt;a href="{{permalink}}" class="btn"&gt;View: {{post_title}}&lt;/a&gt;</code></p>

            <table id="columns-builder" class="widefat striped">
                <thead>
                    <tr>
                        <th>Column Label</th>
                        <th>Data Key (Field)</th>
                        <th>Type (Text/HTML)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($settings['columns'])): ?>
                        <?php foreach($settings['columns'] as $col): ?>
                        <tr class="column-row">
                            <td><input type="text" class="col-label" value="<?php echo esc_attr($col['label']); ?>"></td>
                            <td><input type="text" class="col-key" value="<?php echo esc_attr($col['key']); ?>"></td>
                            <td>
                                <select class="col-type">
                                    <option value="text" <?php selected($col['type'], 'text'); ?>>Text/Shortcode</option>
                                    <option value="html" <?php selected($col['type'], 'html'); ?>>HTML/Image/Button</option>
                                </select>
                            </td>
                            <td><button type="button" class="button remove-col">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" id="add-column" class="button" style="margin-top:10px;">+ Add Column</button>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">Save Settings</button>
            <span id="save-status" style="margin-left:10px; color:green; display:none;">Saved!</span>
        </p>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#add-column').on('click', function() {
            var html = '<tr class="column-row">';
            html += '<td><input type="text" class="col-label" value="New Column"></td>';
            html += '<td><input type="text" class="col-key" value="post_title"></td>';
            html += '<td><select class="col-type"><option value="text">Text/Shortcode</option><option value="html">HTML/Image/Button</option></select></td>';
            html += '<td><button type="button" class="button remove-col">Remove</button></td>';
            html += '</tr>';
            $('#columns-builder tbody').append(html);
        });

        $(document).on('click', '.remove-col', function() {
            $(this).closest('tr').remove();
        });

        $('#meowtable-edit-form').on('submit', function(e) {
            e.preventDefault();

            var columns = [];
            $('.column-row').each(function() {
                columns.push({
                    label: $(this).find('.col-label').val(),
                    key: $(this).find('.col-key').val(),
                    type: $(this).find('.col-type').val()
                });
            });

            var cats = [];
            $('.cat-checkbox:checked').each(function() {
                cats.push($(this).val());
            });

            var settings = {
                data_source: 'wp_posts',
                post_types: $('#post_types').val() || ['post'],
                categories: cats,
                tags: $('#tags').val(),
                items_per_page: $('#items_per_page').val(),
                enable_lazy_load: $('#enable_lazy_load').is(':checked'),
                enable_search: $('#enable_search').is(':checked'),
                enable_cat_filter: $('#enable_cat_filter').is(':checked'),
                enable_tag_filter: $('#enable_tag_filter').is(':checked'),
                columns: columns
            };

            var btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).text('Saving...');

            $.post(meowtable_ajax.ajax_url, {
                action: 'meowtable_save_settings',
                nonce: meowtable_ajax.nonce,
                id: $('#table_id').val(),
                title: $('#table_title').val(),
                settings: JSON.stringify(settings)
            }, function(res) {
                btn.prop('disabled', false).text('Save Settings');
                if (res.success) {
                    $('#save-status').fadeIn().delay(2000).fadeOut();
                } else {
                    alert('Error saving table!');
                }
            });
        });
    });
</script>

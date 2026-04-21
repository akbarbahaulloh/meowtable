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
if (!$settings) {
    $settings = [
        'columns' => [],
        'data_source' => 'wp_posts',
        'post_types' => ['post'],
        'categories' => '',
        'tags' => ''
    ];
}

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
                <th scope="row"><label for="categories">Filter by Categories (Slugs)</label></th>
                <td>
                    <input type="text" id="categories" class="regular-text" value="<?php echo esc_attr(is_array($settings['categories']) ? implode(',', $settings['categories']) : $settings['categories']); ?>">
                    <p class="description">Comma-separated category slugs.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tags">Filter by Tags (Slugs)</label></th>
                <td>
                    <input type="text" id="tags" class="regular-text" value="<?php echo esc_attr(is_array($settings['tags']) ? implode(',', $settings['tags']) : $settings['tags']); ?>">
                    <p class="description">Comma-separated tag slugs.</p>
                </td>
            </tr>
        </table>

        <h2>Columns Configuration</h2>
        <div style="background:#fff; padding:20px; border:1px solid #ccc;">
            <p>Define which data maps to which column. Supported Data Keys for WP Posts: <code>post_title</code>, <code>post_date</code>, <code>post_author</code>, <code>post_content</code>, <code>post_excerpt</code>, <code>thumbnail</code>, <code>permalink</code>, <code>categories</code>, <code>tags</code>. Custom columns can support standard HTML, Buttons, Shortcodes by placing data inside them with placeholders like <code>{{post_title}}</code> or <code>{{categories}}</code>.</p>

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

            var settings = {
                data_source: 'wp_posts',
                post_types: $('#post_types').val() || ['post'],
                categories: $('#categories').val(),
                tags: $('#tags').val(),
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

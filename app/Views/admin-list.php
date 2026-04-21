<div class="wrap">
    <h1 class="wp-heading-inline">Meowtable</h1>
    <a href="<?php echo admin_url('admin.php?page=meowtable-add'); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-title column-primary">Title</th>
                <th scope="col" class="manage-column column-shortcode">Shortcode</th>
                <th scope="col" class="manage-column column-date">Date</th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'meowtables';
            $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

            if ($results) {
                foreach ($results as $row) {
                    $edit_url = admin_url('admin.php?page=meowtable&action=edit&id=' . $row->id);
                    ?>
                    <tr>
                        <td class="title column-title has-row-actions column-primary page-title">
                            <strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($row->title); ?></a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo esc_url($edit_url); ?>">Edit</a> | </span>
                                <span class="trash"><a href="#" class="meowtable-delete" data-id="<?php echo esc_attr($row->id); ?>" style="color: #a00;">Delete</a></span>
                            </div>
                        </td>
                        <td class="shortcode column-shortcode">
                            <code><?php echo esc_html($row->shortcode); ?></code>
                        </td>
                        <td class="date column-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row->created_at))); ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo '<tr><td colspan="3">No tables found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<script>
    jQuery(document).ready(function($) {
        $('.meowtable-delete').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this table?')) {
                var btn = $(this);
                var id = btn.data('id');
                $.post(meowtable_ajax.ajax_url, {
                    action: 'meowtable_delete_table',
                    id: id,
                    nonce: meowtable_ajax.nonce
                }, function(res) {
                    if (res.success) {
                        btn.closest('tr').fadeOut(function() { $(this).remove(); });
                    } else {
                        alert('Error deleting table');
                    }
                });
            }
        });
    });
</script>

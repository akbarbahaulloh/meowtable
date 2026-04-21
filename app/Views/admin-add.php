<div class="wrap">
    <h1 class="wp-heading-inline">Add New Meowtable</h1>
    <hr class="wp-header-end">

    <div id="meowtable-app">
        <form id="meowtable-form">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="table_title">Table Title</label></th>
                        <td>
                            <input name="table_title" type="text" id="table_title" value="" class="regular-text" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save and Configure</button>
            </p>
        </form>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#meowtable-form').on('submit', function(e) {
            e.preventDefault();
            var title = $('#table_title').val();
            // Default basic settings structure
            var settings = {
                columns: [], // Manual columns
                data_source: 'post', // wp posts
                post_types: ['post'],
                categories: [],
                tags: []
            };

            var btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).text('Saving...');

            $.post(meowtable_ajax.ajax_url, {
                action: 'meowtable_save_settings',
                nonce: meowtable_ajax.nonce,
                title: title,
                settings: JSON.stringify(settings)
            }, function(res) {
                if (res.success && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    alert('Error creating table');
                    btn.prop('disabled', false).text('Save and Configure');
                }
            });
        });
    });
</script>

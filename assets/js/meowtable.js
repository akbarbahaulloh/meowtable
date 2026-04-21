jQuery(document).ready(function($) {
    /**
     * Frontend Search Logic for Meowtable
     */
    $(document).on('keyup', '.meowtable-search', function() {
        var query = $(this).val().toLowerCase();
        var $container = $(this).closest('.meowtable-container');
        var $rows = $container.find('tbody tr');

        $rows.each(function() {
            var rowText = $(this).text().toLowerCase();
            if (rowText.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Show 'No data found' if all rows are hidden
        var visibleRows = $rows.filter(':visible').length;
        var $noDataRow = $container.find('.meowtable-no-data');

        if (visibleRows === 0) {
            if ($noDataRow.length === 0) {
                var colCount = $container.find('thead th').length;
                $container.find('tbody').append('<tr class="meowtable-no-data"><td colspan="' + colCount + '">No matching records found.</td></tr>');
            } else {
                $noDataRow.show();
            }
        } else {
            $noDataRow.hide();
        }
    });

    /**
     * Optional: Multi-value filtering could be added here
     * by listening to select changes and combining logic.
     */
});

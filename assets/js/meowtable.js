jQuery(document).ready(function($) {
    /**
     * Frontend Combined Filtering Logic for Meowtable
     * Supports: Keyword Search, Category Dropdown, and Tag Dropdown
     */
    function filterMeowtable($container) {
        var query = $container.find('.meowtable-search').val() || '';
        query = query.toLowerCase();

        var selectedCat = $container.find('.meowtable-filter-cat').val() || '';
        var selectedTag = $container.find('.meowtable-filter-tag').val() || '';

        var $rows = $container.find('tbody tr').not('.meowtable-no-data');

        $rows.each(function() {
            var $row = $(this);
            var rowText = $row.text().toLowerCase();
            var rowCats = ($row.data('categories') || '').toString().split(',');
            var rowTags = ($row.data('tags') || '').toString().split(',');

            var matchSearch = rowText.indexOf(query) > -1;
            var matchCat = selectedCat === '' || rowCats.includes(selectedCat);
            var matchTag = selectedTag === '' || rowTags.includes(selectedTag);

            if (matchSearch && matchCat && matchTag) {
                $row.show();
            } else {
                $row.hide();
            }
        });

        // Handle 'No data found' row
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
    }

    // Keyword Search Event
    $(document).on('keyup', '.meowtable-search', function() {
        filterMeowtable($(this).closest('.meowtable-container'));
    });

    // Dropdown Filter Events
    $(document).on('change', '.meowtable-filter-select', function() {
        filterMeowtable($(this).closest('.meowtable-container'));
    });
});

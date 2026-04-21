jQuery(document).ready(function($) {
    var searchTimer;

    function renderPagination($container, totalPages, currentPage) {
        totalPages = parseInt(totalPages);
        currentPage = parseInt(currentPage);
        var $pager = $container.find('.meowtable-pagination');
        $pager.empty();

        if (isNaN(totalPages) || totalPages < 1) return;

        var html = '<ul class="meowtable-pagination-list">';
        
        // Prev
        html += '<li><button class="meowtable-page-btn" data-page="' + (currentPage - 1) + '" ' + (currentPage <= 1 ? 'disabled' : '') + '>&laquo;</button></li>';

        // Pages
        if (totalPages > 1) {
            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += '<li><button class="meowtable-page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button></li>';
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += '<li class="meowtable-pagination-dots">...</li>';
                }
            }
        } else {
            html += '<li><button class="meowtable-page-btn active" data-page="1">1</button></li>';
        }

        // Next
        html += '<li><button class="meowtable-page-btn" data-page="' + (currentPage + 1) + '" ' + (currentPage >= totalPages ? 'disabled' : '') + '>&raquo;</button></li>';
        html += '</ul>';

        $pager.html(html);
    }

    function fetchTableData($container, page) {
        var tableId = $container.data('table_id');
        var search = $container.find('.meowtable-search').val() || '';
        
        // Collect Dynamic Taxonomy Filters
        var taxonomies = {};
        $container.find('.meowtable-filter-select').each(function() {
            var tax = $(this).data('taxonomy');
            var val = $(this).val();
            if (val) taxonomies[tax] = val;
        });

        $container.addClass('meowtable-loading');
        $container.find('.meowtable-loader').show();

        $.post(meowtable_ajax.ajax_url, {
            action: 'meowtable_get_data',
            nonce: meowtable_ajax.nonce,
            table_id: tableId,
            paged: page,
            search: search,
            taxonomies: taxonomies
        }, function(res) {
            $container.removeClass('meowtable-loading');
            $container.find('.meowtable-loader').hide();

            if (res.success) {
                $container.find('.meowtable-body').html(res.data.html);
                renderPagination($container, res.data.total_pages, res.data.current_page);
                $container.find('.meowtable-pagination').attr('data-total_pages', res.data.total_pages);
                
                // Update Info
                $container.find('.meowtable-count-current').text(res.data.count);
                $container.find('.meowtable-count-total').text(res.data.total_records);
            }
        });
    }

    function filterMeowtable($container) {
        var isLazy = $container.data('lazy') == '1';
        
        if (isLazy) {
            fetchTableData($container, 1);
        } else {
            // Client-side filtering (Dynamic Support)
            var query = ($container.find('.meowtable-search').val() || '').toLowerCase();
            
            var activeFilters = {};
            $container.find('.meowtable-filter-select').each(function() {
                var tax = $(this).data('taxonomy');
                var val = $(this).val();
                if (val) activeFilters[tax] = val;
            });

            var $rows = $container.find('tbody tr').not('.meowtable-no-data');

            $rows.each(function() {
                var $row = $(this);
                var rowText = $row.text().toLowerCase();
                
                var matchSearch = rowText.indexOf(query) > -1;
                var matchTax = true;

                $.each(activeFilters, function(tax, val) {
                    var rowTerms = ($row.data(tax) || '').toString().split(',');
                    if (!rowTerms.includes(val)) {
                        matchTax = false;
                        return false; // break
                    }
                });

                if (matchSearch && matchTax) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        }
    }

    // Initial Pagination Rendering
    $('.meowtable-container').each(function() {
        var $con = $(this);
        if ($con.data('lazy') == '1') {
            var total = $con.find('.meowtable-pagination').data('total_pages');
            renderPagination($con, total, 1);
        }
    });

    // Pagination Click
    $(document).on('click', '.meowtable-page-btn', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var $container = $(this).closest('.meowtable-container');
        if (page > 0) {
            fetchTableData($container, page);
            $('html, body').animate({
                scrollTop: $container.offset().top - 100
            }, 300);
        }
    });

    // Keyword Search Event
    $(document).on('keyup', '.meowtable-search', function() {
        var $container = $(this).closest('.meowtable-container');
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            filterMeowtable($container);
        }, 400); 
    });

    // Dropdown Filter Events
    $(document).on('change', '.meowtable-filter-select', function() {
        filterMeowtable($(this).closest('.meowtable-container'));
    });
});

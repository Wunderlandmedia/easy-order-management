(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize tooltips if any
        if (typeof $.fn.tooltip === 'function') {
            $('.wb-tooltip').tooltip();
        }

        // Column Management
        const $columnsList = $('.columns-list');
        const $availableColumns = $('#available-columns');
        const columnTemplate = $('#column-template').html();

        // Make columns sortable
        if (typeof $.fn.sortable === 'function') {
            $columnsList.sortable({
                handle: '.handle',
                placeholder: 'sortable-placeholder',
                update: function() {
                    updateColumnOrder();
                }
            });
        }

        // Add new column
        $('.add-column').on('click', function() {
            const $selected = $availableColumns.find('option:selected');
            const key = $selected.val();
            const label = $selected.text();

            if (!key) {
                return;
            }

            // Add column to list
            const newColumn = columnTemplate
                .replace(/{{key}}/g, key)
                .replace(/{{label}}/g, label);
            $columnsList.append(newColumn);

            // Remove from available columns
            $selected.remove();
            $availableColumns.val('');

            updateColumnOrder();
        });

        // Remove column
        $columnsList.on('click', '.remove-column', function() {
            const $column = $(this).closest('.column-item');
            const key = $column.data('key');
            const label = $column.find('.column-label').text();

            // Add back to available columns
            $availableColumns.append(
                $('<option>', {
                    value: key,
                    text: label
                })
            );

            // Remove from list
            $column.remove();
            updateColumnOrder();
        });

        // Update column order in hidden inputs
        function updateColumnOrder() {
            const columns = {};
            $columnsList.find('.column-item').each(function() {
                columns[$(this).data('key')] = true;
            });

            // Remove any existing column inputs
            $('input[name^="wb_order_management_settings[order_columns]"]').remove();

            // Add new inputs in correct order
            Object.keys(columns).forEach(function(key) {
                $columnsList.append(
                    $('<input>', {
                        type: 'hidden',
                        name: `wb_order_management_settings[order_columns][${key}]`,
                        value: '1'
                    })
                );
            });
        }

        // Form validation
        $('form').on('submit', function(e) {
            // Validate at least one column is selected
            if ($columnsList.find('.column-item').length === 0) {
                e.preventDefault();
                alert(wb_settings.i18n.min_columns_required);
                return false;
            }
        });
    });
})(jQuery); 
(function($) {

    $(document).on('rex:ready', function (event, container) {
        initCleanup();
    });

    function initCleanup()
    {
        if ($('.select-all').length) {
            $('.select-all').click(function () {
                $(this).parents('.panel-body').find('input[type=checkbox]').prop('checked', 'checked');
                return false;
            });
        }
        if ($('.unselect-all').length) {
            $('.unselect-all').click(function () {
                $(this).parents('.panel-body').find('input[type=checkbox]').prop('checked', false);
                return false;
            });
        }
    }

})(jQuery);
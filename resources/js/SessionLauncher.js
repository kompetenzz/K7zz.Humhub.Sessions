humhub.module('SessionLauncher', function (module, require, $) {

    var WINDOW_WIDTH = 1280;
    var WINDOW_HEIGHT = 800;

    function launchSessionWindow(url) {
        var left = (screen.width - WINDOW_WIDTH) / 2;
        var top = (screen.height - WINDOW_HEIGHT) / 2;
        window.open(
            url,
            '_blank',
            'width=' + WINDOW_WIDTH + ',height=' + WINDOW_HEIGHT + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes,toolbar=no,location=no,status=no,menubar=no'
        );
    }

    module.initOnPjaxLoad = true;

    var init = function () {
        $(document).off('click.sessionLauncher').on('click.sessionLauncher', '.session-launch-window', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            if (url) {
                launchSessionWindow(url);
            }
        });
    };

    module.export({
        init: init,
        launchSessionWindow: launchSessionWindow
    });

});

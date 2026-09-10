<?php

/**
 * elFinder File Manager Page
 * Standalone page for browsing/uploading files
 */
define("IN_SITE", true);
require_once(__DIR__ . '/../../libs/db.php');
require_once(__DIR__ . '/../../libs/lang.php');
require_once(__DIR__ . '/../../libs/helper.php');
require_once(__DIR__ . '/../../config.php');

$CMSNT = new DB();

// Use standard admin auth check (includes session, user validation, etc.)
require_once(__DIR__ . '/../../models/is_admin.php');

// Check permission for media library
if (checkPermission($getUser['admin'], 'view_media_library') != true) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied - No permission');
}

// Check if this is a popup/callback mode
$callback = isset($_GET['callback']) ? htmlspecialchars($_GET['callback']) : '';
$inputId = isset($_GET['input']) ? htmlspecialchars($_GET['input']) : '';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Quản lý tệp'); ?> | <?= $CMSNT->site('title'); ?></title>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css">

    <!-- elFinder CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.65/css/elfinder.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.65/css/theme.css">

    <!-- elFinder JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.65/js/elfinder.min.js"></script>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }

        #elfinder {
            height: 100%;
            border: none;
        }
    </style>
</head>

<body>
    <div id="elfinder"></div>

    <script>
        $(document).ready(function() {
            var callback = '<?= $callback ?>';
            var inputId = '<?= $inputId ?>';

            var elfinderOpts = {
                url: '<?= BASE_URL("ajaxs/admin/elfinder-connector.php"); ?>',
                lang: 'vi',
                height: '100%',
                resizable: false,
                commandsOptions: {
                    getfile: {
                        oncomplete: 'close'
                    }
                }
            };

            // If callback mode (selecting file for input)
            if (callback || inputId) {
                elfinderOpts.getFileCallback = function(file) {
                    // Get the URL of selected file
                    var fileUrl = file.url;

                    // If parent window exists and has the callback or input
                    if (window.opener) {
                        if (callback && typeof window.opener[callback] === 'function') {
                            window.opener[callback](fileUrl);
                        }
                        if (inputId && window.opener.document.getElementById(inputId)) {
                            window.opener.document.getElementById(inputId).value = fileUrl;
                            // Trigger change event
                            var event = new Event('change', {
                                bubbles: true
                            });
                            window.opener.document.getElementById(inputId).dispatchEvent(event);
                        }
                        window.close();
                    } else if (window.parent !== window) {
                        // Inside iframe
                        if (callback) {
                            window.parent.postMessage({
                                type: 'elfinder-select',
                                callback: callback,
                                url: fileUrl
                            }, '*');
                        }
                        if (inputId) {
                            window.parent.postMessage({
                                type: 'elfinder-select',
                                inputId: inputId,
                                url: fileUrl
                            }, '*');
                        }
                    }
                };
            }

            $('#elfinder').elfinder(elfinderOpts);
        });
    </script>
</body>

</html>
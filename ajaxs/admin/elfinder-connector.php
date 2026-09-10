<?php

/**
 * elFinder Connector
 * Handles file operations for the file manager
 * 
 * NOTE: Cannot use is_admin.php here because it uses redirect() on auth failure.
 * API/AJAX endpoints need to return proper HTTP error codes (403), not HTML redirects.
 */
define("IN_SITE", true);
require_once(__DIR__ . '/../../libs/db.php');
require_once(__DIR__ . '/../../libs/lang.php');
require_once(__DIR__ . '/../../libs/helper.php');
require_once(__DIR__ . '/../../libs/session.php');
require_once(__DIR__ . '/../../libs/database/users.php');
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../models/is_admin.php');


// Check permission for media library
if (checkPermission($getUser['admin'], 'view_media_library') != true) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Access denied - No permission']));
}

// Check if user can edit (upload, delete, rename)
// Demo mode disables all write operations
$isDemo = $CMSNT->site('status_demo') == 1;
$canEdit = !$isDemo && checkPermission($getUser['admin'], 'edit_media_library') == true;


/**
 * Access control callback
 */
function access($attr, $path, $data, $volume, $isDir, $relpath)
{
    $basename = basename($path);

    // Hide hidden files/folders
    if (strpos($basename, '.') === 0) {
        return !($attr == 'read' || $attr == 'write');
    }

    return null; // Standard permission check
}

// elFinder options
$opts = array(
    'roots' => array(
        array(
            'driver'        => 'LocalFileSystem',
            'path'          => dirname(__DIR__, 2) . '/assets/storage/images/library/',
            'URL'           => base_url('assets/storage/images/library/'),
            'tmbPath'       => '.tmb',
            'tmbURL'        => base_url('assets/storage/images/library/.tmb/'),

            // Security: File type restrictions (no SVG to prevent XSS)
            'uploadDeny'    => array('all'),
            'uploadAllow'   => array('image/png', 'image/jpeg', 'image/gif', 'image/webp'),
            'uploadOrder'   => array('deny', 'allow'),

            // Security: Max upload size (10MB)
            'uploadMaxSize' => '10M',

            // Security: Disable commands based on permission
            // If no edit permission, disable all write operations
            'disabled'      => $canEdit
                ? array('mkfile', 'archive')
                : array('mkfile', 'archive', 'upload', 'mkdir', 'rm', 'rename', 'duplicate', 'paste', 'cut', 'copy', 'edit', 'put', 'download', 'resize'),

            // Security: Access control
            'accessControl' => 'access',

            // Security: Hide and lock dangerous files
            'attributes' => array(
                // Block all PHP files
                array(
                    'pattern' => '/\.php$/i',
                    'hidden' => true,
                    'locked' => true,
                    'read'   => false,
                    'write'  => false,
                ),
                // Block .htaccess
                array(
                    'pattern' => '/\.htaccess$/i',
                    'hidden' => true,
                    'locked' => true,
                ),
                // Block .gitignore
                array(
                    'pattern' => '/\.gitignore$/i',
                    'hidden' => true,
                    'locked' => true,
                ),
                // Block hidden files (starting with .)
                array(
                    'pattern' => '/^\./',
                    'hidden' => true,
                    'locked' => true,
                ),
                // Block SVG files (XSS risk)
                array(
                    'pattern' => '/\.svg$/i',
                    'hidden' => true,
                    'locked' => true,
                    'read'   => false,
                    'write'  => false,
                ),
            ),
        ),
    ),
    'bind' => array(
        'upload.pre mkdir.pre mkfile.pre rename.pre' => array(
            'Plugin.Normalizer.cmdPreprocess',
            'Plugin.Sanitizer.cmdPreprocess'
        ),
        'upload.presave' => array(
            'Plugin.Normalizer.onUploadPreSave',
            'Plugin.Sanitizer.onUploadPreSave'
        )
    ),
    'plugin' => array(
        'Normalizer' => array(
            'enable' => true,
            'nfc' => true,
            'nfkc' => true,
        ),
        'Sanitizer' => array(
            'enable' => true,
            'targets' => array('\\', '/', ':', '*', '?', '"', '<', '>', '|', '..', "\0"),
            'replace' => '_'
        )
    )
);

// Run elFinder
$connector = new \elFinderConnector(new \elFinder($opts));
$connector->run();

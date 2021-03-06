<?php
/**
 * FileUploader
 *
 * PHP Server-Side Example for Fine Uploader (traditional endpoint handler).
 * Maintained by Widen Enterprises.
 *
 * This example:
 *  - handles chunked and non-chunked requests
 *  - supports the concurrent chunking feature
 *  - assumes all upload requests are multipart encoded
 *  - supports the delete file feature
 *
 * Follow these steps to get up and running with Fine Uploader in a PHP environment:
 *
 * 1. Setup your client-side code, as documented on http://docs.fineuploader.com.
 *
 * 2. Copy this file and handler.php to your server.
 *
 * 3. Ensure your php.ini file contains appropriate values for
 *    max_input_time, upload_max_filesize and post_max_size.
 *
 * 4. Ensure your "chunks" and "files" folders exist and are writable.
 *    "chunks" is only needed if you have enabled the chunking feature client-side.
 *
 * 5. If you have chunking enabled in Fine Uploader, you MUST set a value for the `chunking.success.endpoint` option.
 *    This will be called by Fine Uploader when all chunks for a file have been successfully uploaded, triggering the
 *    PHP server to combine all parts into one file. This is particularly useful for the concurrent chunking feature,
 *    but is now required in all cases if you are making use of this PHP example.
 *
 * Modified for use in HRM.
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

use hrm\FileserverV2;
use hrm\UtilV2;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';
require_once dirname(__FILE__) . '/../vendor/aarpon/php-traditional-server_old/handler.php';

global $httpUploadTempChunksDir, $httpUploadTempFilesDir;

// Required folders. Make sure they exist and have proper permissions.
// The check is done by the Queue Manager on startup.
$chunksDir = $httpUploadTempChunksDir;
$filesDir = $httpUploadTempFilesDir;

$uploader = new UploadHandler();

// Specify the list (array) of valid extensions (all files types allowed by default)
$uploader->allowedExtensions = FileserverV2::getAllValidExtensions();

// We do not specify a max file size here since we do it in the client
$uploader->sizeLimit = null;

// Specify the input name set in the javascript.
$uploader->inputName = "qqfile"; // matches Fine Uploader's default inputName value by default

// If you want to use the chunking/resume feature, specify the folder to temporarily save parts.
$uploader->chunksFolder = $chunksDir;

$method = $_SERVER["REQUEST_METHOD"];
if ($method == "POST") {
    header("Content-Type: text/plain");

    // Assumes you have a chunking.success.endpoint set to point here with a query parameter of "done".
    // For example: /myserver/handlers/endpoint.php?done
    if (isset($_GET["done"])) {

        $result["success"] = true;

        // Make sure that we actually had chunks
        $totalParts = isset($_REQUEST['qqtotalparts']) ? (int)$_REQUEST['qqtotalparts'] : 1;
        if ($totalParts > 1) {
            // Combine chunks
            $result = $uploader->combineChunks($filesDir);
        }

        if ($result["success"] == true) {

            // Retrieve the final destination for the file
            $finalDir = $_SERVER["HTTP_DESTINATIONFOLDER"];

            // Retrieve the full file name of the uploaded file
            $fileToMove = $filesDir . "/" . $_POST["qquuid"] . "/" . $_POST["qqfilename"];

            // Move the files from $filesDir to $finalDir after all required validations
            $errorMessage = "";
            $b = FileserverV2::moveUploadedFile($fileToMove, $finalDir, $errorMessage);

            if (! $b) {

                // If moving failed, inform the client.
                $result["success"] = false;

                // Return failure
                header("HTTP/1.0 500 Internal Server Error");
            }

            // Clean up
            FileserverV2::removeDirAndContent($filesDir . "/" . $_POST["qquuid"]);

        }

    }
    // Handles upload requests
    else {
        // Call handleUpload() with the name of the folder, relative to PHP's getcwd()
        $result = $uploader->handleUpload($filesDir);

        // To return a name used for uploaded file you can use the following line.
        $result["uploadName"] = $uploader->getUploadName();
    }

    echo json_encode($result);
}
// for delete file requests
else if ($method == "DELETE") {
    $result = $uploader->handleDelete($filesDir);
    echo json_encode($result);
}
else {
    header("HTTP/1.0 405 Method Not Allowed");
}

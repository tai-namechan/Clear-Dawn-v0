<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nutrition label OCR temp image storage (PR-F2)
    |--------------------------------------------------------------------------
    |
    | Temp images live only until the OCR job reaches a terminal state
    | (found / failed) or the lookup expires. On Laravel Cloud the web and
    | queue containers do not share a filesystem, so production must point
    | this at an Object Storage disk (e.g. MEALS_LABEL_OCR_DISK=kioku-audio);
    | files are isolated under the food-label-ocr/ prefix.
    |
    */

    'label_ocr' => [
        'disk' => env('MEALS_LABEL_OCR_DISK', 'local'),
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nutrition label OCR temp image storage (PR-F2)
    |--------------------------------------------------------------------------
    |
    | Temp images live until the lookup is confirmed into a meal entry,
    | the job fails, or the lookup expires. Confirmed photos move to
    | meal-photos/{userId}/{entryId}. On Laravel Cloud the web and
    | queue containers do not share a filesystem, so production must point
    | this at an Object Storage disk (e.g. MEALS_LABEL_OCR_DISK=kioku-audio);
    | files are isolated under food-label-ocr/ / food-photo-estimate/ /
    | meal-photos/ prefixes.
    |
    */

    'label_ocr' => [
        'disk' => env('MEALS_LABEL_OCR_DISK', 'local'),
    ],

];

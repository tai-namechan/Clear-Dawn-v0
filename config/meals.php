<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nutrition label OCR temp image storage (PR-F2)
    |--------------------------------------------------------------------------
    |
    | Label OCR temps are deleted when the job finishes (found/failed)
    | or the lookup expires. Photo-estimate temps live until the lookup
    | is confirmed into a meal entry, then move to
    | meal-photos/{userId}/{entryId}. Display persistence follows the
    | upload prefix food-photo-estimate/, not the nutrition source
    | (ai_photo_estimate or nutrition_db). On Laravel Cloud the web and queue
    | containers do not share a filesystem, so production must point
    | this at an Object Storage disk (e.g. MEALS_LABEL_OCR_DISK=kioku-audio);
    | files are isolated under food-label-ocr/ / food-photo-estimate/ /
    | meal-photos/ prefixes.
    |
    */

    'label_ocr' => [
        'disk' => env('MEALS_LABEL_OCR_DISK', 'local'),
    ],

];

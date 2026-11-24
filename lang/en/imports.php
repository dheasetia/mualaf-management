<?php

declare(strict_types=1);

return [
    'model' => 'import',
    'plural' => 'Convert imports',
    'navigation_group' => 'Converts',
    'fields' => [
        'file' => 'CSV file',
        'original_filename' => 'File',
        'status' => 'Status',
        'total_rows' => 'Rows',
        'success_rows' => 'Imported',
        'failed_rows' => 'With errors',
        'duplicate_rows' => 'Duplicate review',
        'uploaded_by' => 'Uploaded by',
        'created_at' => 'Uploaded at',
        'completed_at' => 'Completed at',
        'error_summary' => 'Error',
        'row_number' => 'Line',
        'name' => 'Name',
        'error_message' => 'Result',
        'registration' => 'Registration',
    ],
    'help' => [
        'file' => 'Save the spreadsheet as "CSV UTF-8" in the template layout. Dates are YYYY-MM-DD, nationality and issuing country are two-letter codes (PH, SA), the primary da\'i is their employee number. Every row is checked against all centers before it is registered; imported registrations wait for verification.',
    ],
    'actions' => [
        'upload' => 'Upload CSV',
        'template' => 'Download template',
        'start' => 'Import valid rows',
        'retry' => 'Import reviewed duplicates',
        'cancel' => 'Cancel import',
    ],
    'confirm' => [
        'start' => 'Each valid row is registered as a historical shahadah, after the national duplicate check. Rows that match an existing convert go to duplicate review instead.',
        'retry' => 'Rows whose duplicate review concluded "different person" are registered; the others stay in review.',
    ],
    'notifications' => [
        'uploaded' => 'File uploaded; the rows are being checked.',
        'started' => 'Import started; you will be notified when it finishes.',
        'cancelled' => 'Import cancelled.',
    ],
    'batch_statuses' => [
        'uploaded' => 'Uploaded', 'validating' => 'Checking', 'validated' => 'Checked', 'importing' => 'Importing',
        'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled',
    ],
    'row_statuses' => [
        'pending' => 'Pending', 'valid' => 'Valid', 'invalid' => 'Error', 'imported' => 'Imported',
        'duplicate' => 'Duplicate review', 'skipped' => 'Skipped',
    ],
    'errors' => [
        'missing_cell' => 'Column ":column" is empty.',
        'invalid_cell' => 'Column ":column" has an invalid value.',
        'unreadable_file' => 'The file could not be read.',
        'missing_columns' => 'The file is missing the column(s): :columns. Use the template.',
        'no_rows' => 'The file has no rows.',
        'too_many_rows' => 'A file may have at most :count rows; split it.',
        'repeated_identity' => 'The same identity is already on line :row of this file.',
        'wrong_status' => 'This import cannot do that in its current status.',
        'nothing_to_import' => 'There are no rows to import.',
        'identity_registered' => 'This identity is already registered; a duplicate review case was opened.',
    ],
];

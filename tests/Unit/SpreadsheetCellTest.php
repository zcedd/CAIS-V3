<?php

use App\Support\SpreadsheetCell;

test('spreadsheet cells with formula prefixes are quoted', function (string $value) {
    expect(SpreadsheetCell::sanitize($value))->toBe("'".$value);
})->with(['=HYPERLINK("http://evil")', '+1+1', '-1+1', '@SUM(A1)']);

test('ordinary spreadsheet values are left unchanged', function () {
    expect(SpreadsheetCell::sanitize('Juan Dela Cruz'))->toBe('Juan Dela Cruz')
        ->and(SpreadsheetCell::sanitize(''))->toBe('')
        ->and(SpreadsheetCell::sanitize(null))->toBe('');
});

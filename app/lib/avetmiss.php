<?php
/**
 * AVETMISS completeness for a student record.
 *
 * The enrolments table carries an `avetmiss_complete` flag, but nothing in the
 * system has ever set it - so the pipeline dot was red for every student for
 * ever, no matter how carefully they filled the form in. Rather than tick a
 * flag by hand (which then goes stale the moment a field is cleared), we work
 * it out from the record itself, every time it is drawn.
 *
 * USI is deliberately NOT included here - it has its own column in the
 * pipeline and we do not want one missing field lighting up two dots.
 */
declare(strict_types=1);

/**
 * Mandatory AVETMISS learner fields, as column => label for the office.
 * Order matters: this is the order they are chased up in.
 */
function avetmiss_required_fields(): array {
    return [
        'date_of_birth'        => 'Date of birth',
        'gender'               => 'Gender',
        'street_name'          => 'Street name',
        'suburb'               => 'Suburb',
        'state'                => 'State',
        'postcode'             => 'Postcode',
        'country_of_birth'     => 'Country of birth',
        'main_language'        => 'Language at home',
        'highest_school_level' => 'Highest school level',
        'indigenous_status'    => 'Indigenous status',
        'labour_force_status'  => 'Employment status',
        'disability_flag'      => 'Disability',
    ];
}

/**
 * Which mandatory AVETMISS fields are still blank on this student.
 * Returns a list of human labels, empty when the record is reportable.
 */
function avetmiss_missing(array $s): array {
    $missing = [];
    foreach (avetmiss_required_fields() as $col => $label) {
        $v = $s[$col] ?? null;
        if ($v === null || trim((string)$v) === '') $missing[] = $label;
    }
    return $missing;
}

/** True when every mandatory AVETMISS field is present. */
function avetmiss_is_complete(array $s): bool {
    return !avetmiss_missing($s);
}

/** The student columns the pipeline needs in order to work this out. */
function avetmiss_select_columns(string $alias = 's'): string {
    $cols = array_keys(avetmiss_required_fields());
    return implode(', ', array_map(static fn($c) => "$alias.$c", $cols));
}

/**
 * Country of birth / language options offered on the enrolment forms.
 *
 * The old forms asked "Born in Australia? Yes / No" and only ever saved a code
 * when the answer was Yes - so anyone born overseas ended up with a blank
 * country for ever and could never be reported. These use the same SACC/ASCL
 * codes already in anb_demo_label() so the student screen keeps reading right.
 */
function avetmiss_country_options(): array {
    return [
        '1101' => 'Australia',
        '1201' => 'New Zealand',
        '7103' => 'India',
        '5204' => 'Philippines',
        '7105' => 'Nepal',
        '7106' => 'Sri Lanka',
        '1502' => 'Fiji',
        '9124' => 'Another country',
    ];
}

function avetmiss_language_options(): array {
    return [
        '1201' => 'English',
        '4202' => 'Arabic',
        '6511' => 'Hindi',
        '6512' => 'Nepali',
        '5203' => 'Tagalog / Filipino',
        '5212' => 'Cebuano',
        '7101' => 'Tongan',
        '9799' => 'Another language',
    ];
}

/**
 * The same test as avetmiss_is_complete(), expressed in SQL, for the sign-off
 * query. Kept beside the PHP version on purpose - if one list changes the
 * other must too, so they live in the same file.
 */
function avetmiss_sql_complete(string $alias = 's'): string {
    $parts = [];
    foreach (array_keys(avetmiss_required_fields()) as $c) {
        $parts[] = "($alias.$c IS NOT NULL AND TRIM($alias.$c) <> '')";
    }
    return '(' . implode(' AND ', $parts) . ')';
}

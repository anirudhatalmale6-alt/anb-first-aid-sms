<?php
/**
 * A&B First Aid Training - Quality Indicator surveys.
 *
 * Implements the two NCVER / AQTF Quality Indicator instruments RTOs must collect
 * and report annually:
 *   - Learner Questionnaire  (Learner Engagement indicator)
 *   - Employer Questionnaire (Employer Satisfaction indicator)
 *
 * A survey link is issued alongside each certificate. The learner (and, where
 * relevant, the funding employer) complete it via a public tokenised link; the
 * responses are stored and rolled up into the annual Quality Indicator summary.
 */
declare(strict_types=1);

/** 4-point scale used by both instruments. */
const SURVEY_SCALE = [
    1 => 'Strongly Disagree',
    2 => 'Disagree',
    3 => 'Agree',
    4 => 'Strongly Agree',
];

/** Learner Engagement question set (representative AQTF QI items). */
function survey_learner_questions(): array {
    return [
        'LE1'  => 'I approached my trainers with questions when I needed to.',
        'LE2'  => 'The training focused on relevant skills.',
        'LE3'  => 'The training prepared me well for work.',
        'LE4'  => 'The trainers had an excellent knowledge of the subject.',
        'LE5'  => 'The training was at the right level of difficulty for me.',
        'LE6'  => 'The assessment was a fair test of my skills and knowledge.',
        'LE7'  => 'I received useful feedback on my assessment.',
        'LE8'  => 'The training resources were relevant and helpful.',
        'LE9'  => 'Overall, I am satisfied with the training.',
        'LE10' => 'I would recommend A&B First Aid Training to others.',
    ];
}

/** Employer Satisfaction question set (representative AQTF QI items). */
function survey_employer_questions(): array {
    return [
        'ES1' => 'The training was relevant to our workplace needs.',
        'ES2' => 'The training reflected current industry practice.',
        'ES3' => 'The trainers had the skills and knowledge to deliver the training.',
        'ES4' => 'The assessment was appropriate to the skills being taught.',
        'ES5' => 'Our employee is more competent at work after the training.',
        'ES6' => 'The RTO communicated with us effectively.',
        'ES7' => 'The training was delivered at a time and place that suited us.',
        'ES8' => 'Overall, we are satisfied with the training provided.',
    ];
}

function survey_questions(string $type): array {
    return $type === 'employer' ? survey_employer_questions() : survey_learner_questions();
}

function survey_title(string $type): string {
    return $type === 'employer' ? 'Employer Satisfaction Questionnaire' : 'Learner Questionnaire';
}

/** Generate a short unique survey token. */
function survey_token(string $type): string {
    $prefix = $type === 'employer' ? 'SVE' : 'SVL';
    return $prefix . strtoupper(bin2hex(random_bytes(5)));
}

/**
 * Ensure every issued certificate has a learner (and employer) survey row.
 * Idempotent - safe to call on each page load so surveys always track certs.
 */
function survey_backfill(PDO $pdo): void {
    $certs = $pdo->query("
        SELECT c.id cert_id, c.student_id, c.enrolment_id, c.issue_date,
               s.first_name, s.last_name
        FROM certificates c JOIN students s ON s.id=c.student_id")->fetchAll();
    $ins = $pdo->prepare("INSERT INTO surveys (token,type,student_id,enrolment_id,certificate_id,respondent_name,sent_at) VALUES (?,?,?,?,?,?,?)");
    foreach ($certs as $c) {
        $has = $pdo->prepare("SELECT COUNT(*) FROM surveys WHERE certificate_id=? AND type='learner'");
        $has->execute([$c['cert_id']]);
        if (!$has->fetchColumn()) {
            $ins->execute([
                survey_token('learner'), 'learner', $c['student_id'], $c['enrolment_id'], $c['cert_id'],
                $c['first_name'].' '.$c['last_name'], $c['issue_date'].' 10:00:00'
            ]);
        }
    }
}

/** Aggregate stats for the reporting screen. */
function survey_stats(PDO $pdo): array {
    $out = [];
    foreach (['learner','employer'] as $t) {
        $sent = (int)$pdo->query("SELECT COUNT(*) FROM surveys WHERE type='$t'")->fetchColumn();
        $done = (int)$pdo->query("SELECT COUNT(*) FROM surveys WHERE type='$t' AND completed_at IS NOT NULL")->fetchColumn();
        // average score across all answered items
        $rows = $pdo->query("SELECT answers FROM surveys WHERE type='$t' AND answers IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $sum=0;$n=0;
        foreach ($rows as $j) { foreach ((array)json_decode($j,true) as $v){ $sum+=(int)$v;$n++; } }
        $out[$t] = [
            'sent'=>$sent, 'done'=>$done,
            'rate'=> $sent? round($done/$sent*100) : 0,
            'avg' => $n? round($sum/$n,2) : null,
        ];
    }
    return $out;
}

<?php
declare(strict_types=1);

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $st = db()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}

function require_login(): void {
    if (!current_user()) { header('Location: ?r=login'); exit; }
}

function redirect(string $to): void { header("Location: $to"); exit; }

/** AVETMISS national outcome code -> label */
function outcome_label(string $code): string {
    return [
        '20' => 'Competency achieved',
        '30' => 'Competency not achieved',
        '40' => 'Withdrawn',
        '51' => 'RPL granted',
        '60' => 'Credit transfer',
        '70' => 'Continuing enrolment',
        '85' => 'Not started',
    ][$code] ?? $code;
}

function status_badge(string $s): string {
    $map = ['enrolled'=>'secondary','complete'=>'info','issued'=>'success','incomplete'=>'warning','withdrawn'=>'dark'];
    $c = $map[$s] ?? 'secondary';
    return '<span class="badge text-bg-'.$c.'">'.ucfirst($s).'</span>';
}

/** days until date (negative if past) */
function days_until(?string $date): ?int {
    if (!$date) return null;
    $d = new DateTime($date); $now = new DateTime('2026-08-01');
    return (int)$now->diff($d)->format('%r%a');
}

function render(string $view, array $data = [], string $title = ''): void {
    extract($data);
    ob_start();
    require __DIR__ . '/../views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layout.php';
}

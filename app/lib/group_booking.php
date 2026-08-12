<?php
// Corporate / onsite group booking requests.
declare(strict_types=1);

function gb_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company TEXT, contact_name TEXT, email TEXT, phone TEXT,
        course_label TEXT, preferred_date TEXT, location TEXT,
        participants INTEGER DEFAULT 0, attendees TEXT, notes TEXT,
        status TEXT DEFAULT 'New', staff_notes TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    )");
}

function gb_courses(PDO $pdo): array {
    return $pdo->query("SELECT code, title FROM courses ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

// Email the RTO that a new group booking request came in (best-effort; never blocks the save).
function gb_notify(PDO $pdo, array $f): void {
    $mailer = __DIR__ . '/mailer.php';
    if (!is_file($mailer)) return;
    require_once $mailer;
    if (!function_exists('anb_send_mail')) return;
    $body = "New group / corporate booking request:\n\n"
          . "Company: {$f['company']}\nContact: {$f['contact_name']}\nEmail: {$f['email']}\nPhone: {$f['phone']}\n"
          . "Course: {$f['course_label']}\nPreferred date: {$f['preferred_date']}\nLocation: {$f['location']}\n"
          . "Participants: {$f['participants']}\n\nAttendees:\n{$f['attendees']}\n\nNotes:\n{$f['notes']}\n\n"
          . "Open the SMS > Group Bookings to action this.";
    $html = '<h3>New group / corporate booking request</h3><pre style="font-family:inherit;font-size:14px;">'
          . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre>';
    try { @anb_send_mail($pdo, 'admin@anbfirstaidtraining.com.au', 'New group booking request — ' . ($f['company'] ?: 'Company'), $html); } catch (Throwable $e) {}
}

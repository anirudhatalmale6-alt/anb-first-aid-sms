<?php
/**
 * Tax invoice / receipt for one enrolment.
 *
 * The website's booking plugin already emails a receipt at the moment a card
 * payment goes through, but nothing was kept on this side - so a student
 * ringing up a year later about their invoice could not be helped from the
 * student record. This renders one from what the enrolment holds.
 *
 * It is deliberately headed "Tax Invoice" until the money is in, and only
 * becomes a "Receipt" once the enrolment is marked paid. Calling an unpaid
 * charge a receipt would be wrong, and it is the kind of wrong that ends up
 * in somebody's bookkeeping.
 */
declare(strict_types=1);

require_once __DIR__ . '/fpdf.php';

const ANB_ABN     = '51 660 446 908';
const ANB_ADDRESS = 'Unit 2, 156 Queen Street, St Marys, NSW, 2760';
const ANB_PHONE   = '0423 427 765';
const ANB_EMAIL   = 'admin@anbfirstaidtraining.com.au';
const ANB_WEB     = 'www.anbfirstaidtraining.com.au';

/**
 * @return array{pdf:string,filename:string,title:string}
 * @throws RuntimeException when the enrolment does not exist
 */
function anb_render_receipt(PDO $pdo, int $enrolmentId): array {
    $q = $pdo->prepare("SELECT e.*, s.first_name, s.last_name, s.email,
               s.unit_flat, s.street_number, s.street_name, s.suburb, s.state, s.postcode,
               co.code course_code, co.title course_title, p.title plan_title,
               sc.start_date sched_date
        FROM enrolments e
        JOIN students s ON s.id = e.student_id
        JOIN courses  co ON co.id = e.course_id
        JOIN plans    p  ON p.id  = e.plan_id
        LEFT JOIN schedules sc ON sc.id = e.schedule_id
        WHERE e.id = ?");
    $q->execute([$enrolmentId]);
    $e = $q->fetch(PDO::FETCH_ASSOC);
    if (!$e) throw new RuntimeException('Enrolment not found.');

    $due  = (float)$e['amount_due'];
    $paid = (float)$e['amount_paid'];
    $isReceipt = ($e['payment_status'] === 'paid');
    $title = $isReceipt ? 'Receipt' : 'Tax Invoice';

    $name = trim($e['first_name'].' '.$e['last_name']);
    $addr = trim(implode(' ', array_filter([
        trim((string)$e['unit_flat']), trim((string)$e['street_number']), trim((string)$e['street_name']),
    ])));
    $addr = trim($addr . (($e['suburb'] || $e['state'] || $e['postcode'])
        ? ', '.trim(implode(', ', array_filter([$e['suburb'], $e['state'], $e['postcode']])))
        : ''), ', ');

    $purple = [47, 29, 58];
    $grey   = [110, 110, 110];
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $set = function (FPDF $p, array $c) { $p->SetTextColor($c[0], $c[1], $c[2]); };

    // header
    $logo = __DIR__ . '/../assets/anb_logo.png';
    if (is_file($logo)) $pdf->Image($logo, 15, 12, 38);
    $pdf->SetXY(60, 14);
    $set($pdf, $purple); $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, 'A & B First Aid Training Pty Ltd', 0, 2, 'R');
    $set($pdf, $grey); $pdf->SetFont('Arial', '', 9);
    foreach ([ANB_ADDRESS, 'Tel '.ANB_PHONE, ANB_EMAIL, ANB_WEB] as $line) {
        $pdf->Cell(0, 4.6, $line, 0, 2, 'R');
    }

    $pdf->SetXY(15, 44);
    $set($pdf, $purple); $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, $title, 0, 1, 'L');

    // who it is for
    $pdf->SetXY(15, 58);
    $set($pdf, [40, 40, 40]); $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, $name, 0, 2, 'L');
    $set($pdf, $grey); $pdf->SetFont('Arial', '', 9.5);
    if ($addr !== '') $pdf->Cell(0, 5, $addr, 0, 2, 'L');
    if ($e['email'])  $pdf->Cell(0, 5, (string)$e['email'], 0, 2, 'L');

    $when = $e['created_at'] ?: ($e['start_date'] ?: date('Y-m-d'));
    $ts = strtotime((string)$when) ?: time();
    $pdf->SetXY(120, 58);
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Cell(75, 5, date('D j M Y', $ts), 0, 2, 'R');
    $pdf->Cell(75, 5, 'Enrolment reference: #'.(int)$e['id'], 0, 2, 'R');

    // lines
    $y = 88;
    $pdf->SetXY(15, $y);
    $set($pdf, $purple); $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(140, 8, 'Details', 0, 0, 'L');
    $pdf->Cell(40, 8, 'Amount', 0, 1, 'R');
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Line(15, $y + 8, 195, $y + 8);

    $pdf->SetXY(15, $y + 11);
    $set($pdf, [40, 40, 40]); $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(140, 6, $name, 0, 1, 'L');
    $pdf->SetX(15);
    $set($pdf, $grey); $pdf->SetFont('Arial', '', 9.5);
    $pdf->Cell(140, 5.5, $e['course_title'].' ('.$e['course_code'].')', 0, 0, 'L');
    $set($pdf, [40, 40, 40]); $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 5.5, '$'.number_format($due, 2), 0, 1, 'R');
    if (!empty($e['sched_date'])) {
        $pdf->SetX(15); $set($pdf, $grey); $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(140, 5, 'Class '.date('D j M Y', strtotime((string)$e['sched_date'])), 0, 1, 'L');
    }

    // totals
    $ty = $pdf->GetY() + 8;
    $pdf->Line(15, $ty - 3, 195, $ty - 3);
    $totals = [
        ['Enrolment Total',      $due],
        [$isReceipt ? 'This Payment' : 'Paid to date', $paid],
        ['Balance Outstanding',  max(0, $due - $paid)],
    ];
    foreach ($totals as $i => [$label, $amount]) {
        $pdf->SetXY(15, $ty + ($i * 6.5));
        $bold = ($i === count($totals) - 1);
        $set($pdf, $bold ? $purple : $grey);
        $pdf->SetFont('Arial', $bold ? 'B' : '', 10);
        $pdf->Cell(140, 6, $label, 0, 0, 'L');
        $pdf->Cell(40, 6, '$'.number_format((float)$amount, 2), 0, 1, 'R');
    }

    if (!$isReceipt) {
        $pdf->SetXY(15, $ty + 26);
        $set($pdf, [180, 60, 60]); $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 5, 'This enrolment is not marked as paid. This is an invoice, not a receipt.', 0, 1, 'L');
    }

    $pdf->SetY(272);
    $set($pdf, $grey); $pdf->SetFont('Arial', '', 8.5);
    $pdf->Cell(0, 5, 'A & B First Aid Training Pty Ltd  ·  RTO 46055  ·  ABN '.ANB_ABN, 0, 1, 'C');

    $safe = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($name)) ?: 'student';
    return [
        'pdf'      => $pdf->Output('S'),
        'filename' => strtolower($title === 'Receipt' ? 'receipt' : 'tax-invoice').'-'.$safe.'-'.(int)$e['id'].'.pdf',
        'title'    => $title,
    ];
}

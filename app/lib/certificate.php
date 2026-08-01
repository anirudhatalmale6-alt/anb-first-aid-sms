<?php
/**
 * Certificate generation - Statement of Attainment (matches A&B sample).
 * Uses FPDF (PDF) + TCPDF QRcode class (pure-PHP QR) rendered via GD.
 */
declare(strict_types=1);
require_once __DIR__ . '/fpdf.php';

const ANB_RTO   = '46055';
const ANB_ABN   = '51 660 446 908';
const ANB_NAME  = 'A&B First Aid Training Pty Ltd';
const ANB_ADDR  = '156 Queen Street, St Marys NSW 2760';
const ANB_PHONE = '0423 427 765';
const ANB_EMAIL = 'admin@anbfirstaidtraining.com.au';
const ANB_VERIFY_BASE = 'https://portal.anbfirstaidtraining.com.au';

/** Render a QR code PNG for $text. Returns the file path. */
function anb_qr_png(string $text, string $file, int $scale = 4, int $margin = 2): string {
    require_once __DIR__ . '/qrcode.php';
    $qr = new QRcode($text, 'M');
    $a  = $qr->getBarcodeArray();
    $cols = (int)$a['num_cols']; $rows = (int)$a['num_rows'];
    $size = ($cols + 2 * $margin) * $scale;
    $img = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, $size, $size, $white);
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            if (!empty($a['bcode'][$r][$c])) {
                $x = ($c + $margin) * $scale; $y = ($r + $margin) * $scale;
                imagefilledrectangle($img, $x, $y, $x + $scale - 1, $y + $scale - 1, $black);
            }
        }
    }
    imagepng($img, $file);
    imagedestroy($img);
    return $file;
}

/** Next certificate number, e.g. SA46055-22194 */
function anb_next_cert_number(PDO $pdo, string $type = 'statement_of_attainment'): string {
    $prefix = ['statement_of_attainment'=>'SA','qualification'=>'QC','certificate_of_achievement'=>'CA','record_of_results'=>'RR'][$type] ?? 'SA';
    $base   = $prefix . ANB_RTO . '-';
    $rows   = $pdo->prepare("SELECT certificate_number FROM certificates WHERE certificate_number LIKE ?");
    $rows->execute([$base . '%']);
    $max = 22000;
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $suf = (int)substr((string)$n, strlen($base));
        if ($suf > $max) $max = $suf;
    }
    return $base . ($max + 1);
}

/**
 * Generate a Statement of Attainment for an enrolment.
 * Creates the certificate record + PDF file. Returns the certificate row.
 */
function anb_generate_certificate(PDO $pdo, int $enrolmentId): array {
    // load enrolment context
    $st = $pdo->prepare("
        SELECT e.*, s.first_name, s.middle_name, s.last_name, s.salutation, s.email,
               co.code course_code, co.title course_title, co.validity_months
        FROM enrolments e JOIN students s ON s.id=e.student_id JOIN courses co ON co.id=e.course_id
        WHERE e.id=?");
    $st->execute([$enrolmentId]);
    $e = $st->fetch();
    if (!$e) throw new RuntimeException('Enrolment not found');

    // Signing off / issuing a certificate means the trainer has confirmed the
    // student is competent - mark any still-open units as Competency achieved (20).
    $today = date('Y-m-d');
    $pdo->prepare("UPDATE enrolment_units SET outcome_national='20', date_achieved=COALESCE(date_achieved,?)
                   WHERE enrolment_id=? AND outcome_national IN ('70','85','')")
        ->execute([$today, $enrolmentId]);

    // achieved units
    $u = $pdo->prepare("
        SELECT un.code, un.title, eu.date_achieved
        FROM enrolment_units eu JOIN units un ON un.id=eu.unit_id
        WHERE eu.enrolment_id=? AND eu.outcome_national='20'");
    $u->execute([$enrolmentId]);
    $units = $u->fetchAll();
    if (!$units) throw new RuntimeException('No competent units to certify');

    $issue  = $units[0]['date_achieved'] ?: date('Y-m-d');
    $months = (int)($e['validity_months'] ?: 12);
    $expiry = (new DateTime($issue))->modify("+{$months} months")->format('Y-m-d');
    $number = anb_next_cert_number($pdo, 'statement_of_attainment');
    $name   = trim($e['first_name'].' '.$e['middle_name'].' '.$e['last_name']);

    // build PDF
    $dir = __DIR__ . '/../data/certs';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdf_path = $dir . '/' . $number . '.pdf';
    $qr_path  = $dir . '/' . $number . '.png';
    anb_qr_png(ANB_VERIFY_BASE . '/?r=verify&cert=' . $number, $qr_path);
    anb_render_soa($pdf_path, $qr_path, [
        'name'=>$name, 'number'=>$number, 'issue'=>$issue, 'expiry'=>$expiry,
        'student_no'=>$e['student_id'], 'units'=>$units,
    ]);
    @unlink($qr_path);

    // record it
    $ins = $pdo->prepare("INSERT INTO certificates (enrolment_id,student_id,type,certificate_number,issue_date,expiry_date,file_path) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$enrolmentId, $e['student_id'], 'statement_of_attainment', $number, $issue, $expiry, 'certs/'.$number.'.pdf']);
    $pdo->prepare("UPDATE enrolments SET status='issued' WHERE id=?")->execute([$enrolmentId]);

    return $pdo->query("SELECT * FROM certificates WHERE id=".(int)$pdo->lastInsertId())->fetch();
}

/** Render the Statement of Attainment PDF (matches A&B layout). */
function anb_render_soa(string $out, string $qrFile, array $d): void {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $W = 210;

    // --- branded header (drawn badge + name) ---
    $pdf->SetFillColor(229,57,53);
    $pdf->Rect(($W/2)-42, 12, 18, 14, 'F'); // badge bg
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('Arial','B',15);
    $pdf->SetXY(($W/2)-42, 14); $pdf->Cell(18,10,'A&B',0,0,'C');
    $pdf->SetTextColor(47,29,58);
    $pdf->SetFont('Arial','B',18);
    $pdf->SetXY(($W/2)-22, 13); $pdf->Cell(64,8,'A & B',0,2,'L');
    $pdf->SetFont('Arial','B',11);
    $pdf->SetX(($W/2)-22); $pdf->Cell(64,6,'First Aid Training',0,0,'L');

    // --- title ---
    $pdf->SetTextColor(30,30,30);
    $pdf->SetFont('Arial','B',26);
    $pdf->SetXY(0,40); $pdf->Cell($W,12,'STATEMENT OF ATTAINMENT',0,1,'C');
    // decorative rule
    $pdf->SetDrawColor(229,57,53); $pdf->SetLineWidth(0.6);
    $pdf->Line(30,55,180,55);

    // watermark (light)
    $pdf->SetTextColor(238,232,240);
    $pdf->SetFont('Arial','B',44);
    $pdf->SetXY(0,150); $pdf->Cell($W,20,'A & B First Aid Training',0,0,'C');

    // --- body ---
    $pdf->SetTextColor(70,70,70);
    $pdf->SetFont('Arial','I',10);
    $pdf->SetXY(25,64);
    $pdf->MultiCell($W-50,5,'A statement of attainment is issued by a Registered Training Organisation when an individual has completed one or more accredited units or modules.',0,'C');

    $pdf->SetFont('Arial','',11);
    $pdf->SetXY(0,84); $pdf->Cell($W,6,'This is a statement that',0,1,'C');
    $pdf->SetTextColor(30,30,30);
    $pdf->SetFont('Arial','B',20);
    $pdf->SetXY(0,92); $pdf->Cell($W,10,$d['name'],0,1,'C');
    $pdf->SetTextColor(70,70,70);
    $pdf->SetFont('Arial','',11);
    $pdf->SetXY(0,104); $pdf->Cell($W,6,'has attained:',0,1,'C');

    // --- units table ---
    $y = 116;
    $pdf->SetFont('Arial','B',10); $pdf->SetTextColor(47,29,58);
    $pdf->SetXY(30,$y); $pdf->Cell(30,7,'Code',0,0,'L');
    $pdf->Cell(90,7,'Title',0,0,'L');
    $pdf->Cell(0,7,'Expiry Date',0,1,'R');
    $pdf->SetDrawColor(220,220,220); $pdf->SetLineWidth(0.2); $pdf->Line(30,$y+7,180,$y+7);
    $pdf->SetFont('Arial','',10); $pdf->SetTextColor(40,40,40);
    $y += 9;
    foreach ($d['units'] as $un) {
        $pdf->SetXY(30,$y); $pdf->Cell(30,7,$un['code'],0,0,'L');
        $pdf->Cell(90,7,$un['title'],0,0,'L');
        $pdf->Cell(0,7,$d['expiry'],0,1,'R');
        $y += 8;
    }

    // --- date issued ---
    $pdf->SetFont('Arial','',11); $pdf->SetTextColor(70,70,70);
    $pdf->SetXY(0,$y+8); $pdf->Cell($W,6,'Date Issued: '.date('jS F Y', strtotime($d['issue'])),0,1,'C');

    // --- signatory ---
    $pdf->SetTextColor(30,30,30);
    $pdf->SetFont('Arial','B',13);
    $pdf->SetXY(115,225); $pdf->Cell(70,6,'Gloria Omoregie',0,2,'L');
    $pdf->SetDrawColor(120,120,120); $pdf->SetLineWidth(0.3); $pdf->Line(115,233,175,233);
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(80,80,80);
    $pdf->SetXY(115,234); $pdf->Cell(70,5,'Authorised Signatory',0,2,'L');
    $pdf->Cell(70,5,ANB_NAME,0,0,'L');
    // nationally recognised (left)
    $pdf->SetTextColor(0,120,60); $pdf->SetFont('Arial','B',9);
    $pdf->SetXY(30,226); $pdf->Cell(70,5,'NATIONALLY',0,2,'L');
    $pdf->Cell(70,5,'RECOGNISED TRAINING',0,0,'L');

    // --- footer ---
    $pdf->SetDrawColor(229,57,53); $pdf->SetLineWidth(0.4); $pdf->Line(15,262,195,262);
    $pdf->SetFont('Arial','',8); $pdf->SetTextColor(90,90,90);
    // left company
    $pdf->SetXY(15,266);
    $pdf->MultiCell(70,4, ANB_NAME."\n".ANB_ADDR."\n".ANB_PHONE."\n".ANB_EMAIL,0,'L');
    // center QR
    if (is_file($qrFile)) $pdf->Image($qrFile, ($W/2)-11, 266, 22, 22, 'PNG');
    // right details
    $pdf->SetXY(125,266);
    $pdf->MultiCell(70,4,
        "Certificate No: ".$d['number']."\n".
        "Student No: ".$d['student_no']."\n".
        "RTO: ".ANB_RTO."\n".
        "ABN: ".ANB_ABN, 0,'R');

    $pdf->Output('F', $out);
}

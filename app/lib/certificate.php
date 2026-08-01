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

/** Render the Statement of Attainment PDF - professional design. */
function anb_render_soa(string $out, string $qrFile, array $d): void {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $W = 210; $H = 297;
    $assets = __DIR__ . '/../assets';
    $purple = [47,29,58]; $red = [229,57,53]; $grey = [90,90,90];
    $sig = static function($p,$c){ $p->SetTextColor($c[0],$c[1],$c[2]); };

    // ---- ornamental double border ----
    $pdf->SetDrawColor(...$purple); $pdf->SetLineWidth(1.1);
    $pdf->Rect(8, 8, $W-16, $H-16);
    $pdf->SetDrawColor(...$red); $pdf->SetLineWidth(0.4);
    $pdf->Rect(11, 11, $W-22, $H-22);
    // corner accents (small filled squares as beads)
    $pdf->SetFillColor(...$red);
    foreach ([[9.4,9.4],[$W-11.4,9.4],[9.4,$H-11.4],[$W-11.4,$H-11.4]] as $c)
        $pdf->Rect($c[0],$c[1],2,2,'F');

    // ---- faint logo watermark (behind text) ----
    if (is_file("$assets/anb_logo_wm.png"))
        $pdf->Image("$assets/anb_logo_wm.png", ($W/2)-70, 118, 140, 0, 'PNG');

    // ---- logo ----
    if (is_file("$assets/anb_logo.png"))
        $pdf->Image("$assets/anb_logo.png", ($W/2)-28, 20, 56, 0, 'PNG');

    // ---- title ----
    $sig($pdf,$purple); $pdf->SetFont('Arial','B',27);
    $pdf->SetXY(0,50); $pdf->Cell($W,13,'STATEMENT OF ATTAINMENT',0,1,'C');
    // rule with centre bead
    $pdf->SetDrawColor(...$red); $pdf->SetLineWidth(0.5);
    $pdf->Line(45,66,$W-45,66);
    $pdf->SetFillColor(...$red); $pdf->Rect(($W/2)-1.4,64.6,2.8,2.8,'F');

    // ---- blurb ----
    $sig($pdf,$grey); $pdf->SetFont('Arial','I',10);
    $pdf->SetXY(30,72);
    $pdf->MultiCell($W-60,5,'A statement of attainment is issued by a Registered Training Organisation when an individual has completed one or more accredited units or modules.',0,'C');

    // ---- recipient ----
    $pdf->SetFont('Arial','',11.5); $sig($pdf,$grey);
    $pdf->SetXY(0,90); $pdf->Cell($W,6,'This is hereby awarded to',0,1,'C');
    $sig($pdf,$purple); $pdf->SetFont('Arial','B',24);
    $pdf->SetXY(0,98); $pdf->Cell($W,12,$d['name'],0,1,'C');
    // underline flourish under name
    $nameW = $pdf->GetStringWidth($d['name']);
    $pdf->SetDrawColor(200,180,150); $pdf->SetLineWidth(0.3);
    $pdf->Line(($W/2)-($nameW/2)-6,113,($W/2)+($nameW/2)+6,113);
    $sig($pdf,$grey); $pdf->SetFont('Arial','',11);
    $pdf->SetXY(0,116); $pdf->Cell($W,6,'for attaining the following nationally recognised unit(s) of competency:',0,1,'C');

    // ---- units table ----
    $y = 130; $lx = 32; $rx = $W-32;
    $pdf->SetFont('Arial','B',9.5); $sig($pdf,$purple);
    $pdf->SetXY($lx,$y); $pdf->Cell(28,7,'CODE',0,0,'L');
    $pdf->Cell(96,7,'TITLE',0,0,'L');
    $pdf->Cell($rx-$lx-124,7,'EXPIRY',0,1,'R');
    $pdf->SetDrawColor(...$red); $pdf->SetLineWidth(0.3); $pdf->Line($lx,$y+7,$rx,$y+7);
    $pdf->SetFont('Arial','',10.5); $sig($pdf,[40,40,40]);
    $y += 9;
    foreach ($d['units'] as $un) {
        $pdf->SetXY($lx,$y); $pdf->SetFont('Arial','B',10.5); $pdf->Cell(28,7,$un['code'],0,0,'L');
        $pdf->SetFont('Arial','',10.5); $pdf->Cell(96,7,$un['title'],0,0,'L');
        $pdf->Cell($rx-$lx-124,7,$d['expiry'],0,1,'R');
        $pdf->SetDrawColor(230,230,230); $pdf->SetLineWidth(0.1); $pdf->Line($lx,$y+7.5,$rx,$y+7.5);
        $y += 8.5;
    }

    // ---- date issued ----
    $pdf->SetFont('Arial','',11); $sig($pdf,$grey);
    $pdf->SetXY(0,$y+7); $pdf->Cell($W,6,'Date of Issue: '.date('jS F Y', strtotime($d['issue'])),0,1,'C');

    // ---- Nationally Recognised Training mark (drawn) ----
    $nrtX = 30; $nrtY = 224;
    $pdf->SetFillColor(0,132,80);   $pdf->Rect($nrtX,$nrtY,4,9,'F');
    $pdf->SetFillColor(220,40,40);  $pdf->Rect($nrtX+5,$nrtY,4,9,'F');
    $pdf->SetFillColor(60,60,60);   $pdf->Rect($nrtX+10,$nrtY,4,9,'F');
    $sig($pdf,[60,60,60]); $pdf->SetFont('Arial','B',8);
    $pdf->SetXY($nrtX,$nrtY+10); $pdf->Cell(60,4,'NATIONALLY RECOGNISED',0,2,'L');
    $pdf->Cell(60,4,'TRAINING',0,0,'L');

    // ---- signatory ----
    if (!empty($d['signature']) && is_file($d['signature']))
        $pdf->Image($d['signature'], 120, 218, 45, 0, 'PNG');
    $pdf->SetDrawColor(120,120,120); $pdf->SetLineWidth(0.3); $pdf->Line(118,233,178,233);
    $sig($pdf,$purple); $pdf->SetFont('Arial','B',12);
    $pdf->SetXY(118,234); $pdf->Cell(60,5,'Gloria Omoregie',0,2,'L');
    $sig($pdf,[90,90,90]); $pdf->SetFont('Arial','',8.5);
    $pdf->Cell(60,4,'Authorised Signatory',0,2,'L');
    $pdf->Cell(60,4,ANB_NAME,0,0,'L');

    // ---- footer ----
    $pdf->SetDrawColor(...$red); $pdf->SetLineWidth(0.4); $pdf->Line(16,260,$W-16,260);
    $pdf->SetFont('Arial','',7.8); $sig($pdf,[90,90,90]);
    $pdf->SetXY(16,264);
    $pdf->MultiCell(72,4, ANB_NAME."\n".ANB_ADDR."\n".ANB_PHONE."  |  ".ANB_EMAIL,0,'L');
    if (is_file($qrFile)) $pdf->Image($qrFile, ($W/2)-10, 263, 20, 20, 'PNG');
    $pdf->SetXY($W-88,264);
    $pdf->MultiCell(72,4,
        "Certificate No: ".$d['number']."\n".
        "Student No: ".$d['student_no']."\n".
        "RTO: ".ANB_RTO."      ABN: ".ANB_ABN."\n".
        "Verify at ".str_replace('https://','',ANB_VERIFY_BASE), 0,'R');

    $pdf->Output('F', $out);
}

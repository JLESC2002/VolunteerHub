<?php
/**
 * generate_qr_pdf.php
 * Generates a printable A4 PDF with Check-In / Check-Out QR codes.
 *
 * GET params:
 *   event_id  (required)
 *
 * Output: inline PDF (opens in browser / triggers print dialog)
 */

require '../vendor/fpdf/fpdf.php';
require '../vendor/phpqrcode/qrlib.php';

include '../conn.php';
include './check_session.php';

/* ── Validate input ────────────────────────────────────── */
if (empty($_GET['event_id'])) {
    http_response_code(400);
    exit('Missing event_id.');
}

$event_id = intval($_GET['event_id']);
$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT e.title, e.date, e.latitude, e.longitude, o.name AS org_name
    FROM events e
    LEFT JOIN organizations o ON o.admin_id = e.created_by
    WHERE e.id = ? AND e.created_by = ?
");
$stmt->bind_param("ii", $event_id, $admin_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    http_response_code(403);
    exit('Event not found or access denied.');
}

/* ── Prepare QR content ────────────────────────────────── */
$eventName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event['title']);

$checkinDir  = "../Generator/QRcode Checkin/";
$checkoutDir = "../Generator/QRcode Checkout/";

if (!is_dir($checkinDir))  mkdir($checkinDir,  0777, true);
if (!is_dir($checkoutDir)) mkdir($checkoutDir, 0777, true);

$checkinFile  = $checkinDir  . "{$eventName}_CheckIn.png";
$checkoutFile = $checkoutDir . "{$eventName}_CheckOut.png";

$checkinData  = json_encode([
    "event_id" => $event_id,
    "type"     => "checkin",
    "lat"      => $event['latitude'],
    "lon"      => $event['longitude'],
]);
$checkoutData = json_encode([
    "event_id" => $event_id,
    "type"     => "checkout",
    "lat"      => $event['latitude'],
    "lon"      => $event['longitude'],
]);

// Generate (or regenerate) QR PNG files — size 8 = ~200 px
QRcode::png($checkinData,  $checkinFile,  QR_ECLEVEL_M, 8);
QRcode::png($checkoutData, $checkoutFile, QR_ECLEVEL_M, 8);

/* ── Custom FPDF class ─────────────────────────────────── */
class QrPDF extends FPDF
{
    public string $orgName    = '';
    public string $eventTitle = '';
    public string $eventDate  = '';

    /* ── Shared: dashed cut line ─────────────────────── */
    public function CutLine(float $y): void
    {
        $this->SetDrawColor(150, 150, 150);
        $this->SetLineWidth(0.3);
        $this->SetDash(3, 2);          // dashed pattern
        $this->Line(15, $y, 195, $y);
        $this->SetDash();              // reset to solid

        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(160, 160, 160);
        $this->SetXY(0, $y - 3.5);
        $this->Cell(210, 7, chr(9988) . '  CUT HERE  ' . chr(9988), 0, 0, 'C');
    }

    /* ── Helper: set dashed line ─────────────────────── */
    private function SetDash(float $black = 0, float $white = 0): void
    {
        if ($black != 0) {
            $s = sprintf('[%.3F %.3F] 0 d', $black * $this->k, $white * $this->k);
        } else {
            $s = '[] 0 d';
        }
        $this->_out($s);
    }

    /* ── Draw one QR panel ───────────────────────────── */
    public function QrPanel(
        float  $panelTop,
        float  $panelHeight,
        string $label,
        string $subLabel,
        string $qrFile,
        string $accentHex,
        int    $accentR,
        int    $accentG,
        int    $accentB
    ): void {
        $pageW = 210;  // A4 mm width
        $margin = 15;
        $innerW = $pageW - $margin * 2;

        /* Panel background */
        $this->SetFillColor(252, 253, 254);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->RoundedRect($margin, $panelTop, $innerW, $panelHeight, 4, 'DF');

        /* Accent stripe on left */
        $this->SetFillColor($accentR, $accentG, $accentB);
        $this->Rect($margin, $panelTop, 4, $panelHeight, 'F');

        /* Header background row */
        $headerH = 14;
        $this->SetFillColor($accentR, $accentG, $accentB);
        $this->Rect($margin + 4, $panelTop, $innerW - 4, $headerH, 'F');

        /* Org name top-left */
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(220, 240, 228);
        $this->SetXY($margin + 7, $panelTop + 3);
        $this->Cell($innerW - 14, 5, mb_strtoupper($this->orgName), 0, 0, 'L');

        /* Action label (CHECK-IN / CHECK-OUT) */
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($margin + 4, $panelTop + 1.5);
        $this->Cell($innerW - 8, $headerH, $label, 0, 0, 'C');

        /* Event name */
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(30, 40, 50);
        $this->SetXY($margin + 4, $panelTop + $headerH + 4);
        $this->Cell($innerW - 8, 6, mb_strtoupper($this->eventTitle), 0, 0, 'C');

        /* Event date */
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->SetXY($margin + 4, $panelTop + $headerH + 10);
        $formattedDate = date('F j, Y', strtotime($this->eventDate));
        $this->Cell($innerW - 8, 5, $formattedDate, 0, 0, 'C');

        /* QR image — centered */
        $qrSize  = 62;
        $qrX     = ($pageW - $qrSize) / 2;
        $qrY     = $panelTop + $headerH + 18;

        /* White QR background card */
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.2);
        $pad = 4;
        $this->RoundedRect($qrX - $pad, $qrY - $pad, $qrSize + $pad * 2, $qrSize + $pad * 2, 3, 'DF');

        if (file_exists($qrFile)) {
            $this->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
        }

        /* Sub-label below QR */
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->SetXY($margin + 4, $qrY + $qrSize + $pad + 3);
        $this->Cell($innerW - 8, 5, $subLabel, 0, 0, 'C');

        /* Scan instruction */
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor($accentR, $accentG, $accentB);
        $this->SetXY($margin + 4, $qrY + $qrSize + $pad + 9);
        $this->Cell($innerW - 8, 5, 'Scan with the VolunteerHub app to record attendance', 0, 0, 'C');
    }

    /* ── Rounded rectangle helper ────────────────────── */
    public function RoundedRect(
        float $x, float $y, float $w, float $h,
        float $r, string $style = ''
    ): void {
        $k  = $this->k;
        $hp = $this->h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc, $yc, $r, 90,  0);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc, $yc, $r,  0, -90);
        $xc = $x + $r;       $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc, $yc, $r, -90, -180);
        $xc = $x + $r;       $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc, $yc, $r, 180,  90);
        $this->_out($op);
    }

    private function _Arc(
        float $x1, float $y1, float $r,
        float $a1, float $a2
    ): void {
        $a1 = deg2rad($a1); $a2 = deg2rad($a2);
        $d  = 4 / 3 * tan(($a2 - $a1) / 4);
        $k  = $this->k;
        $hp = $this->h;
        $x2 = $x1 + $r * cos($a2); $y2 = $y1 - $r * sin($a2);
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x1 + $r * cos($a1) - $d * $r * sin($a1)) * $k,
            ($hp - ($y1 - $r * sin($a1) - $d * $r * cos($a1))) * $k,
            ($x2 + $d * $r * sin($a2)) * $k,
            ($hp - ($y2 + $d * $r * cos($a2))) * $k,
            $x2 * $k,
            ($hp - $y2) * $k
        ));
    }

    /* ── Page footer ─────────────────────────────────── */
    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5,
            'Generated by VolunteerHub  |  Event: ' . $this->eventTitle .
            '  |  ' . date('M d, Y  H:i'),
            0, 0, 'C'
        );
    }
}

/* ── Build PDF ─────────────────────────────────────────── */
ob_clean();

$pdf = new QrPDF('P', 'mm', 'A4');
$pdf->orgName    = $event['org_name'] ?? 'VolunteerHub';
$pdf->eventTitle = $event['title'];
$pdf->eventDate  = $event['date'];
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$pageH      = 297;   // A4 height mm
$topPad     = 10;
$cutLineY   = $pageH / 2;          // 148.5 mm
$panelGap   = 8;
$panelH     = $cutLineY - $topPad - $panelGap;   // ~126.5 mm each

// ── Check-In panel (top half) ──────────────────────────
$pdf->QrPanel(
    panelTop:    $topPad,
    panelHeight: $panelH,
    label:       'CHECK-IN',
    subLabel:    'Scan upon arrival at the event venue',
    qrFile:      $checkinFile,
    accentHex:   '#1a5c3a',
    accentR:     26,
    accentG:     92,
    accentB:     58
);

// ── Cut line ───────────────────────────────────────────
$pdf->CutLine($cutLineY);

// ── Check-Out panel (bottom half) ─────────────────────
$pdf->QrPanel(
    panelTop:    $cutLineY + $panelGap,
    panelHeight: $panelH,
    label:       'CHECK-OUT',
    subLabel:    'Scan when leaving the event venue',
    qrFile:      $checkoutFile,
    accentHex:   '#1d4ed8',
    accentR:     29,
    accentG:     78,
    accentB:     216
);

/* ── Stream PDF ────────────────────────────────────────── */
$safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event['title']);
$pdf->Output('D', "QR_Attendance_{$safeName}.pdf");
exit;
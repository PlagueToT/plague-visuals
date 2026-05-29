<?php
/**
 * Plague Visuals — Contact Form Mailer
 * Handles POST requests from the contact form.
 * Sends email to raulvictorrodriguez@gmail.com
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Rate limiting (simple file-based) ──────────────────────────────────────
$ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip       = preg_replace('/[^0-9a-fA-F.:,]/', '', explode(',', $ip)[0]);
$rlFile   = sys_get_temp_dir() . '/pv_rl_' . md5($ip) . '.json';
$rlWindow = 3600; // 1 hour
$rlMax    = 5;    // max submissions per window per IP

$rlData = ['count' => 0, 'reset' => time() + $rlWindow];
if (file_exists($rlFile)) {
    $stored = json_decode(file_get_contents($rlFile), true);
    if ($stored && $stored['reset'] > time()) {
        $rlData = $stored;
    }
}
if ($rlData['count'] >= $rlMax) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please wait an hour before trying again.', 'code' => 'RATE_LIMIT']);
    exit;
}

// ── Only allow POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.', 'code' => 'METHOD']);
    exit;
}

// ── Parse JSON or form-data body ──────────────────────────────────────────
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
} else {
    $data = $_POST;
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body.', 'code' => 'BODY']);
    exit;
}

// ── Honeypot check ─────────────────────────────────────────────────────────
if (!empty($data['website'])) {
    // Silent success for bots
    echo json_encode(['success' => true, 'message' => 'Message sent!']);
    exit;
}

// ── Sanitise helper ───────────────────────────────────────────────────────
function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Collect & validate fields ─────────────────────────────────────────────
$errors = [];

$fname   = clean($data['fname']   ?? '');
$lname   = clean($data['lname']   ?? '');
$email   = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$channel = clean($data['channel'] ?? '');
$service = clean($data['service'] ?? '');
$budget  = clean($data['budget']  ?? '');
$message = clean($data['message'] ?? '');

if (strlen($fname) < 2)  $errors[] = 'First name is required (min 2 chars).';
if (strlen($lname) < 2)  $errors[] = 'Last name is required (min 2 chars).';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (empty($service))     $errors[] = 'Please select a service.';
if (strlen($message) < 20) $errors[] = 'Project details are too short (min 20 chars).';

// Extra: block obvious spam content
$spamWords = ['casino', 'viagra', 'crypto invest', 'click here', 'earn money fast'];
foreach ($spamWords as $w) {
    if (stripos($message, $w) !== false || stripos($fname, $w) !== false) {
        $errors[] = 'Message flagged as spam.';
        break;
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'code' => 'VALIDATION', 'errors' => $errors]);
    exit;
}

// ── Build email ───────────────────────────────────────────────────────────
$to      = 'raulvictorrodriguez@gmail.com';
$subject = "[Plague Visuals] New Inquiry — {$service} — {$fname} {$lname}";

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
  body{margin:0;padding:0;background:#030303;font-family:'Segoe UI',Arial,sans-serif;color:#f0ede8}
  .wrap{max-width:600px;margin:0 auto;background:#0d0d10;border:1px solid #1c1c22}
  .hdr{background:#e8272a;padding:2rem 2.5rem;text-align:center}
  .hdr h1{margin:0;font-size:2rem;letter-spacing:.15em;color:#fff;font-weight:900;text-transform:uppercase}
  .hdr p{margin:.35rem 0 0;font-size:.75rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.75)}
  .body{padding:2rem 2.5rem}
  .badge{display:inline-block;background:rgba(232,39,42,.12);border:1px solid rgba(232,39,42,.35);color:#e8272a;font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;padding:.3rem .7rem;margin-bottom:1.5rem}
  .row{display:flex;gap:0;margin-bottom:1rem;border:1px solid #1c1c22}
  .lbl{background:#131316;padding:.7rem 1rem;font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:#6e6e7a;min-width:140px;display:flex;align-items:center}
  .val{padding:.7rem 1rem;font-size:.88rem;color:#f0ede8;flex:1;word-break:break-word}
  .msg-block{background:#060608;border:1px solid #1c1c22;padding:1.2rem;margin-top:.5rem;font-size:.92rem;line-height:1.75;color:#b8b8c4;white-space:pre-wrap;word-break:break-word}
  .ftr{background:#060608;padding:1.2rem 2.5rem;text-align:center;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#3a3a44;border-top:1px solid #1c1c22}
  .acct{margin-top:1.5rem;background:#060608;border:1px solid rgba(34,197,94,.22);padding:1rem 1.2rem}
  .acct p{margin:0;font-size:.75rem;color:#22c55e;letter-spacing:.1em}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>PLAGUE<span style="opacity:.7">.</span>VISUALS</h1>
    <p>New Project Inquiry</p>
  </div>
  <div class="body">
    <div class="badge">📥 Received · {$service}</div>
    <div class="row"><div class="lbl">Full Name</div><div class="val"><strong>{$fname} {$lname}</strong></div></div>
    <div class="row"><div class="lbl">Email</div><div class="val"><a href="mailto:{$email}" style="color:#e8272a;text-decoration:none">{$email}</a></div></div>
    <div class="row"><div class="lbl">Service</div><div class="val">{$service}</div></div>
    <div class="row"><div class="lbl">Budget</div><div class="val">{$budget}</div></div>
    <div class="row"><div class="lbl">Channel</div><div class="val">{$channel}</div></div>
    <p style="font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:#6e6e7a;margin-top:1.5rem">Project Details</p>
    <div class="msg-block">{$message}</div>
    <div class="acct"><p>✓ Auto-reply sent to client · {$email}</p></div>
  </div>
  <div class="ftr">© Plague Visuals · Sent from plaguevisuals.com · {$ip}</div>
</div>
</body>
</html>
HTML;

$plainBody = "PLAGUE VISUALS — New Inquiry\n"
           . str_repeat('─', 40) . "\n"
           . "Name:    {$fname} {$lname}\n"
           . "Email:   {$email}\n"
           . "Service: {$service}\n"
           . "Budget:  {$budget}\n"
           . "Channel: {$channel}\n\n"
           . "Project Details:\n{$message}\n"
           . str_repeat('─', 40) . "\n"
           . "Sent via plaguevisuals.com";

// ── Auto-reply to client ───────────────────────────────────────────────────
$replySubject = "Got your message, {$fname} — Plague Visuals";
$replyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8">
<style>
  body{margin:0;padding:0;background:#030303;font-family:'Segoe UI',Arial,sans-serif;color:#f0ede8}
  .wrap{max-width:600px;margin:0 auto;background:#0d0d10;border:1px solid #1c1c22}
  .hdr{background:#e8272a;padding:2rem 2.5rem;text-align:center}
  .hdr h1{margin:0;font-size:2rem;letter-spacing:.15em;color:#fff;font-weight:900;text-transform:uppercase}
  .body{padding:2rem 2.5rem}
  .timeline{margin:1.5rem 0;display:flex;flex-direction:column;gap:.5rem}
  .step{display:flex;gap:.9rem;align-items:flex-start;padding:.6rem;border:1px solid #1c1c22}
  .step-num{background:#e8272a;color:#fff;font-size:.6rem;font-weight:bold;width:20px;height:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
  .step-txt{font-size:.82rem;color:#b8b8c4;line-height:1.5}
  .step-txt strong{display:block;color:#f0ede8;font-size:.78rem;letter-spacing:.08em;margin-bottom:.15rem}
  .ftr{background:#060608;padding:1.2rem 2.5rem;text-align:center;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#3a3a44;border-top:1px solid #1c1c22}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>PLAGUE<span style="opacity:.7">.</span>VISUALS</h1>
    <p style="margin:.35rem 0 0;font-size:.75rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.75)">Message Received</p>
  </div>
  <div class="body">
    <p style="font-size:1rem;line-height:1.75;color:#b8b8c4">Hey <strong style="color:#f0ede8">{$fname}</strong>,</p>
    <p style="font-size:.92rem;line-height:1.75;color:#b8b8c4">Your inquiry for <strong style="color:#e8272a">{$service}</strong> landed in my inbox. Here's what happens next:</p>
    <div class="timeline">
      <div class="step"><div class="step-num">1</div><div class="step-txt"><strong>Review (Now)</strong>I'm reading through your project details.</div></div>
      <div class="step"><div class="step-num">2</div><div class="step-txt"><strong>Response (Within 24h)</strong>I'll reply with questions, timelines, and next steps.</div></div>
      <div class="step"><div class="step-num">3</div><div class="step-txt"><strong>We Align</strong>Quick call or chat to finalize scope, deliverables, and pricing.</div></div>
      <div class="step"><div class="step-num">4</div><div class="step-txt"><strong>I Start Crafting</strong>First cut delivered, then revision rounds until it's right.</div></div>
    </div>
    <p style="font-size:.82rem;color:#6e6e7a;line-height:1.6;margin-top:1.5rem">Your inquiry summary: <strong style="color:#b8b8c4">{$service}</strong> · Budget: <strong style="color:#b8b8c4">{$budget}</strong></p>
    <p style="font-size:.82rem;color:#6e6e7a;line-height:1.6">— Plague · Plague Visuals</p>
  </div>
  <div class="ftr">This is an automated confirmation · Reply to this email won't be monitored · Contact raulvictorrodriguez@gmail.com directly</div>
</div>
</body>
</html>
HTML;

// ── Send emails ───────────────────────────────────────────────────────────
$boundary = '----PlagueVisuals_' . md5(uniqid());

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
$headers .= "From: Plague Visuals <noreply@plaguevisuals.com>\r\n";
$headers .= "Reply-To: {$fname} {$lname} <{$email}>\r\n";
$headers .= "X-Mailer: PlagueVisuals-PHP/1.0\r\n";
$headers .= "X-Priority: 1\r\n";

$mailBody  = "--{$boundary}\r\n";
$mailBody .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n";
$mailBody .= $plainBody . "\r\n\r\n";
$mailBody .= "--{$boundary}\r\n";
$mailBody .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
$mailBody .= quoted_printable_encode($htmlBody) . "\r\n\r\n";
$mailBody .= "--{$boundary}--";

$sent = mail($to, $subject, $mailBody, $headers);

// Auto-reply
$rb2  = "----PV2_" . md5(uniqid());
$rh   = "MIME-Version: 1.0\r\n";
$rh  .= "Content-Type: multipart/alternative; boundary=\"{$rb2}\"\r\n";
$rh  .= "From: Plague Visuals <noreply@plaguevisuals.com>\r\n";
$rh  .= "Reply-To: raulvictorrodriguez@gmail.com\r\n";
$rh  .= "X-Mailer: PlagueVisuals-PHP/1.0\r\n";
$rm   = "--{$rb2}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
$rm  .= "Hey {$fname}, your inquiry was received. I'll respond within 24h. — Plague\r\n\r\n";
$rm  .= "--{$rb2}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
$rm  .= $replyHtml . "\r\n\r\n--{$rb2}--";
@mail($email, $replySubject, $rm, $rh);

// ── Update rate limit ─────────────────────────────────────────────────────
$rlData['count']++;
file_put_contents($rlFile, json_encode($rlData), LOCK_EX);

// ── Respond ───────────────────────────────────────────────────────────────
if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => "Message sent! Check your inbox for a confirmation, {$fname}. I'll respond within 24 hours.",
        'name'    => $fname
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Mail server error. Please email raulvictorrodriguez@gmail.com directly.',
        'code'    => 'MAIL_FAIL'
    ]);
}
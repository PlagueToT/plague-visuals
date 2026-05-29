/**
 * Plague Visuals — Contact Form API
 * /api/send.js — Vercel Serverless Function (Node.js)
 *
 * Uses Nodemailer + Gmail SMTP (App Password)
 * Set these in Vercel Dashboard → Project → Settings → Environment Variables:
 *   GMAIL_USER   = vfx.plaguevisuals@gmail.com
 *   GMAIL_PASS   = jzyk lmte zijv rycd   ← your app password (spaces are fine, nodemailer strips them)
 *   RECIPIENT    = vfx.plaguevisuals@gmail.com  (where you want to receive emails)
 */

import nodemailer from 'nodemailer';

// ─── Rate limiting (in-memory, resets on cold start — good enough for serverless) ───
const rateLimitMap = new Map();
const RATE_LIMIT_MAX    = 5;    // max submissions
const RATE_LIMIT_WINDOW = 3600; // seconds (1 hour)

function checkRateLimit(ip) {
  const now  = Math.floor(Date.now() / 1000);
  const data = rateLimitMap.get(ip);

  if (!data || now > data.reset) {
    rateLimitMap.set(ip, { count: 1, reset: now + RATE_LIMIT_WINDOW });
    return true; // allowed
  }
  if (data.count >= RATE_LIMIT_MAX) return false; // blocked
  data.count++;
  return true; // allowed
}

// ─── Email HTML template (sent to YOU) ───────────────────────────────────────
function buildAdminEmail({ fname, lname, email, service, channel, message, ip }) {
  return `<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{background:#030303;font-family:'Segoe UI',Arial,sans-serif;color:#f0ede8;padding:20px}
  .wrap{max-width:620px;margin:0 auto;background:#0d0d10;border:1px solid #1c1c22;border-radius:2px}
  .hdr{background:#DC1F1F;padding:2rem 2.5rem;text-align:center}
  .hdr h1{font-size:2rem;letter-spacing:.15em;color:#fff;font-weight:900;text-transform:uppercase;margin:0}
  .hdr p{margin:.35rem 0 0;font-size:.75rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.75)}
  .body{padding:2rem 2.5rem}
  .badge{display:inline-block;background:rgba(220,31,31,.12);border:1px solid rgba(220,31,31,.35);color:#DC1F1F;font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;padding:.3rem .7rem;margin-bottom:1.5rem}
  table{width:100%;border-collapse:collapse;margin-bottom:1rem}
  td{padding:.7rem 1rem;font-size:.88rem;border:1px solid #1c1c22;vertical-align:top;word-break:break-word}
  td:first-child{background:#131316;font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:#6e6e7a;width:130px;white-space:nowrap}
  .msg-block{background:#060608;border:1px solid #1c1c22;padding:1.2rem;margin-top:.5rem;font-size:.92rem;line-height:1.75;color:#b8b8c4;white-space:pre-wrap;word-break:break-word}
  .confirm{margin-top:1.5rem;background:#060608;border:1px solid rgba(34,197,94,.22);padding:1rem 1.2rem}
  .confirm p{font-size:.75rem;color:#22c55e;letter-spacing:.08em;margin:0}
  .ftr{background:#060608;padding:1rem 2.5rem;text-align:center;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#3a3a44;border-top:1px solid #1c1c22}
  a{color:#DC1F1F;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>PLAGUE<span style="opacity:.7">.</span>VISUALS</h1>
    <p>📥 New Project Inquiry</p>
  </div>
  <div class="body">
    <div class="badge">Service: ${service}</div>
    <table>
      <tr><td>Full Name</td><td><strong>${fname} ${lname}</strong></td></tr>
      <tr><td>Email</td><td><a href="mailto:${email}">${email}</a></td></tr>
      <tr><td>Service</td><td>${service}</td></tr>
      <tr><td>Channel</td><td>${channel || '—'}</td></tr>
    </table>
    <p style="font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:#6e6e7a;margin-bottom:.5rem">Project Details</p>
    <div class="msg-block">${message}</div>
    <div class="confirm"><p>✓ Auto-reply sent to ${email}</p></div>
  </div>
  <div class="ftr">© Plague Visuals · plaguevisuals.com · IP: ${ip}</div>
</div>
</body>
</html>`;
}

// ─── Auto-reply HTML (sent to the CLIENT) ────────────────────────────────────
function buildClientEmail({ fname, service }) {
  return `<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{background:#030303;font-family:'Segoe UI',Arial,sans-serif;color:#f0ede8;padding:20px}
  .wrap{max-width:620px;margin:0 auto;background:#0d0d10;border:1px solid #1c1c22;border-radius:2px}
  .hdr{background:#DC1F1F;padding:2rem 2.5rem;text-align:center}
  .hdr h1{font-size:2rem;letter-spacing:.15em;color:#fff;font-weight:900;text-transform:uppercase;margin:0}
  .hdr p{margin:.35rem 0 0;font-size:.75rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.75)}
  .body{padding:2rem 2.5rem}
  .intro{font-size:1rem;line-height:1.8;color:#b8b8c4;margin-bottom:1.5rem}
  .timeline{display:flex;flex-direction:column;gap:.5rem;margin:1.5rem 0}
  .step{display:flex;gap:.9rem;align-items:flex-start;padding:.75rem;border:1px solid #1c1c22}
  .step-n{background:#DC1F1F;color:#fff;font-size:.65rem;font-weight:bold;min-width:22px;height:22px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
  .step-t{font-size:.82rem;color:#b8b8c4;line-height:1.55}
  .step-t strong{display:block;color:#f0ede8;font-size:.78rem;letter-spacing:.06em;margin-bottom:.2rem}
  .sig{margin-top:2rem;padding-top:1.5rem;border-top:1px solid #1c1c22;font-size:.85rem;color:#6e6e7a;line-height:1.7}
  .sig strong{color:#f0ede8}
  .ftr{background:#060608;padding:1rem 2.5rem;text-align:center;font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#3a3a44;border-top:1px solid #1c1c22}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>PLAGUE<span style="opacity:.7">.</span>VISUALS</h1>
    <p>Message Received ✓</p>
  </div>
  <div class="body">
    <p class="intro">Hey <strong style="color:#f0ede8">${fname}</strong>, your inquiry for <strong style="color:#DC1F1F">${service}</strong> just landed in my inbox. Here's what happens next:</p>
    <div class="timeline">
      <div class="step"><div class="step-n">1</div><div class="step-t"><strong>Right Now</strong>I'm reading through your project details carefully.</div></div>
      <div class="step"><div class="step-n">2</div><div class="step-t"><strong>Within 24 Hours</strong>I'll reply with questions, timelines, and pricing.</div></div>
      <div class="step"><div class="step-n">3</div><div class="step-t"><strong>We Align</strong>Quick call or chat to finalize scope and deliverables.</div></div>
      <div class="step"><div class="step-n">4</div><div class="step-t"><strong>I Start Crafting</strong>First cut delivered, then revision rounds until it's perfect.</div></div>
    </div>
    <div class="sig">
      — <strong>Plague</strong><br>
      Video Editor · Motion Artist · VA<br>
      <span style="color:#DC1F1F">vfx.plaguevisuals@gmail.com</span>
    </div>
  </div>
  <div class="ftr">This is an automated confirmation · Direct replies go to vfx.plaguevisuals@gmail.com</div>
</div>
</body>
</html>`;
}

// ─── CORS helper ──────────────────────────────────────────────────────────────
function setCors(res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
}

// ─── MAIN HANDLER ─────────────────────────────────────────────────────────────
export default async function handler(req, res) {
  setCors(res);

  // Handle preflight
  if (req.method === 'OPTIONS') return res.status(200).end();

  // Only allow POST
  if (req.method !== 'POST') {
    return res.status(405).json({ ok: false, error: 'Method not allowed.' });
  }

  // Check env vars — fail fast with helpful error
  const GMAIL_USER  = process.env.GMAIL_USER;
  const GMAIL_PASS  = process.env.GMAIL_PASS;
  const RECIPIENT   = process.env.RECIPIENT || GMAIL_USER;

  if (!GMAIL_USER || !GMAIL_PASS) {
    console.error('[send.js] Missing GMAIL_USER or GMAIL_PASS env vars.');
    return res.status(500).json({
      ok: false,
      error: 'Email not configured. Set GMAIL_USER and GMAIL_PASS in Vercel.',
      code: 'NO_KEY'
    });
  }

  // Rate limiting
  const ip = (req.headers['x-forwarded-for'] || req.socket?.remoteAddress || 'unknown')
    .split(',')[0].trim().replace(/[^0-9a-fA-F.:]/g, '');
  if (!checkRateLimit(ip)) {
    return res.status(429).json({ ok: false, error: 'Too many submissions. Try again in an hour.', code: 'RATE_LIMIT' });
  }

  // Parse body (Vercel auto-parses JSON)
  const body = req.body || {};
  const { fname, lname, email, service, channel, message, website } = body;

  // Honeypot
  if (website) return res.status(200).json({ ok: true }); // silent bot discard

  // ── Validation ──────────────────────────────────────────────────────────────
  const errors = [];
  if (!fname  || fname.trim().length  < 2) errors.push('First name required (min 2 chars).');
  if (!lname  || lname.trim().length  < 2) errors.push('Last name required (min 2 chars).');
  if (!email  || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) errors.push('Valid email required.');
  if (!service || !service.trim()) errors.push('Service selection required.');
  if (!message || message.trim().length < 20) errors.push('Project details too short (min 20 chars).');

  if (errors.length) {
    return res.status(422).json({ ok: false, error: errors.join(' '), code: 'VALIDATION', errors });
  }

  // ── Sanitise ────────────────────────────────────────────────────────────────
  const clean = (v = '') => String(v).replace(/</g, '&lt;').replace(/>/g, '&gt;').trim();
  const F = clean(fname), L = clean(lname), E = email.trim().toLowerCase();
  const SVC = clean(service), CH = clean(channel), MSG = clean(message);

  // Spam keywords
  const SPAM = ['casino','viagra','crypto invest','click here','earn money fast','seo services'];
  if (SPAM.some(w => MSG.toLowerCase().includes(w) || F.toLowerCase().includes(w))) {
    return res.status(200).json({ ok: true }); // silent discard
  }

  // ── Create transporter ──────────────────────────────────────────────────────
  const transporter = nodemailer.createTransport({
    service: 'gmail',
    auth: {
      user: GMAIL_USER,
      pass: GMAIL_PASS.replace(/\s/g, ''), // strip spaces from app password
    },
  });

  // ── Verify connection ───────────────────────────────────────────────────────
  try {
    await transporter.verify();
  } catch (err) {
    console.error('[send.js] SMTP verify failed:', err.message);
    return res.status(500).json({
      ok: false,
      error: 'SMTP connection failed. Check GMAIL_USER / GMAIL_PASS in Vercel env vars.',
      code: 'SMTP_FAIL',
      detail: err.message,
    });
  }

  // ── Send admin email (to you) ───────────────────────────────────────────────
  const adminMailOptions = {
    from   : `"Plague Visuals Form" <${GMAIL_USER}>`,
    to     : RECIPIENT,
    replyTo: `"${F} ${L}" <${E}>`,
    subject: `[Plague Visuals] New Inquiry — ${SVC} — ${F} ${L}`,
    html   : buildAdminEmail({ fname: F, lname: L, email: E, service: SVC, channel: CH, message: MSG, ip }),
    text   : `New Inquiry\nName: ${F} ${L}\nEmail: ${E}\nService: ${SVC}\nChannel: ${CH}\n\n${MSG}`,
  };

  // ── Send auto-reply (to client) ─────────────────────────────────────────────
  const clientMailOptions = {
    from   : `"Plague Visuals" <${GMAIL_USER}>`,
    to     : E,
    subject: `Got your message, ${F} — Plague Visuals`,
    html   : buildClientEmail({ fname: F, service: SVC }),
    text   : `Hey ${F}, your inquiry for ${SVC} was received. I'll respond within 24 hours. — Plague`,
  };

  try {
    await transporter.sendMail(adminMailOptions);
    await transporter.sendMail(clientMailOptions).catch(err => {
      // Don't fail the whole request if auto-reply fails
      console.warn('[send.js] Auto-reply failed (non-fatal):', err.message);
    });

    return res.status(200).json({
      ok     : true,
      message: `Message sent! I'll respond within 24 hours, ${F}.`,
      name   : F,
    });
  } catch (err) {
    console.error('[send.js] sendMail error:', err.message);
    return res.status(500).json({
      ok    : false,
      error : 'Failed to send email. Please try again or email directly.',
      code  : 'SEND_FAIL',
      detail: err.message,
    });
  }
}
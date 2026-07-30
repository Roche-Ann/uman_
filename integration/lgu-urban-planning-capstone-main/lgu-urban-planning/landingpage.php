<?php
require_once __DIR__ . '/core/Database.php';

// ── Live stats for the landing page ─────────────────────────────────────────
// Defaults act as a graceful fallback if the DB is ever unreachable — this
// page is public-facing and must never fatal error for a visitor.
$lp_stats = [
    'total'    => 248,
    'pending'  => 17,
    'approved' => 201,
    'rejected' => 6,
];
$lp_totalProcessed   = 2400;
$lp_onTimeRate       = 96;
$lp_barangaysCovered = 18;
$lp_recentActivity   = [];

try {
    $db = Database::getInstance();

    // Hero "Dashboard Overview" mock card — live counts
    $statusCounts = $db->fetchOne("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'submitted' OR status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
        FROM applications");

    if ($statusCounts) {
        $lp_stats = [
            'total'    => (int)($statusCounts['total'] ?? 0),
            'pending'  => (int)($statusCounts['pending'] ?? 0),
            'approved' => (int)($statusCounts['approved'] ?? 0),
            'rejected' => (int)($statusCounts['rejected'] ?? 0),
        ];
    }

    // Trust strip — total applications ever processed
    $lp_totalProcessed = $lp_stats['total'];

    // Trust strip — on-time review rate (decided within 5 days of filing)
    $decided = $db->fetchOne("SELECT
            COUNT(*) AS decided,
            SUM(CASE WHEN DATEDIFF(updated_at, created_at) <= 5 THEN 1 ELSE 0 END) AS on_time
        FROM applications
        WHERE status IN ('approved', 'rejected')");

    if ($decided && (int)$decided['decided'] > 0) {
        $lp_onTimeRate = (int) round(((int)$decided['on_time'] / (int)$decided['decided']) * 100);
    }

    // Trust strip — distinct barangays covered
    $brgy = $db->fetchOne("SELECT COUNT(DISTINCT barangay) AS total FROM applications WHERE barangay IS NOT NULL AND barangay <> ''");
    if ($brgy && (int)$brgy['total'] > 0) {
        $lp_barangaysCovered = (int)$brgy['total'];
    }

    // Hero card — 3 most recent applications
    $lp_recentActivity = $db->fetchAll("SELECT application_number, project_name, barangay, status
        FROM applications ORDER BY created_at DESC LIMIT 3");

} catch (Throwable $e) {
    // DB unavailable — page still renders with the defaults set above.
}

// Maps an application status to the pill styling used in the hero mock card
function lp_status_badge(string $status): array {
    return match(strtolower($status)) {
        'approved'     => ['label' => 'Approved',    'bg' => '#d1fae5', 'fg' => '#047857', 'icon' => 'bi-clipboard-check',   'iconColor' => 'var(--success)'],
        'rejected'     => ['label' => 'Rejected',    'bg' => '#fee2e2', 'fg' => '#b91c1c', 'icon' => 'bi-x-circle',          'iconColor' => '#ef4444'],
        'under_review' => ['label' => 'In Review',   'bg' => '#fef3c7', 'fg' => '#b45309', 'icon' => 'bi-file-earmark-text', 'iconColor' => 'var(--blue-accent)'],
        'for_revision' => ['label' => 'For Revision','bg' => '#e0e7ff', 'fg' => '#4338ca', 'icon' => 'bi-pencil-square',     'iconColor' => 'var(--purple)'],
        default        => ['label' => 'Pending',     'bg' => '#fef3c7', 'fg' => '#b45309', 'icon' => 'bi-hourglass-split',   'iconColor' => 'var(--amber)'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quezon City UPAD — Development Permit Management System</title>
<link rel="icon" type="image/x-icon" href="/lgu-urban-planning/assets/upad-logo.png" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

  :root{
    --navy-1:#1e3a8a;
    --navy-2:#1e40af;
    --blue-accent:#2563eb;
    --blue-light:#3b82f6;
    --blue-pale:#eef2ff;
    --ink:#1e293b;
    --muted:#64748b;
    --panel:#f8f9fb;
    --success:#10b981;
    --amber:#f59e0b;
    --purple:#6366f1;
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth; overflow-x:hidden;}
  /* Page already hides overflow-x, so Bootstrap's scrollbar-compensation
     padding on modal open just shows up as a stray blank strip. Disable it. */
  body.modal-open{padding-right:0 !important;}
  body{
    font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color:var(--ink);
    background:#fff;
    margin:0;
    overflow-x:hidden;
    max-width:100vw;
  }
  a{text-decoration:none;}
  .container-xl{max-width:1240px;}

  /* ---------- PAGE LOAD FADE ---------- */
  body{opacity:0; animation:pageIn .5s ease forwards;}
  @keyframes pageIn{to{opacity:1;}}

  /* ---------- SCROLL REVEAL ---------- */
  .reveal{
    opacity:0; transform:translateY(28px);
    transition:opacity .7s cubic-bezier(.16,.8,.3,1), transform .7s cubic-bezier(.16,.8,.3,1);
  }
  .reveal.is-visible{opacity:1; transform:translateY(0);}

  /* ---------- NAVBAR ---------- */
  .top-navbar{
    background:linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 100%);
    padding:16px 0;
    position:sticky; top:0; z-index:1000;
    box-shadow:0 2px 14px rgba(0,0,0,0.12);
    transition:padding .25s ease, box-shadow .25s ease;
  }
  .top-navbar.is-scrolled{
    padding:10px 0;
    box-shadow:0 4px 24px rgba(0,0,0,0.22);
  }
  .top-navbar .brand{
    display:flex; align-items:center; gap:12px; color:#fff;
  }
  .top-navbar .brand img{height:36px; width:36px; border-radius:8px; background:#fff; padding:4px;}
  .top-navbar .brand-text{line-height:1.15;}
  .top-navbar .brand-text .name{font-weight:700; font-size:1.02rem;}
  .top-navbar .brand-text .sub{font-size:0.7rem; color:rgba(255,255,255,0.75);}
  .top-navbar nav a{
    color:rgba(255,255,255,0.85); font-size:0.9rem; font-weight:500; margin-left:28px;
    transition:color .15s ease;
  }
  .top-navbar nav a:hover{color:#fff;}
  .btn-nav-login{
    background:#fff; color:var(--navy-2) !important; padding:9px 20px; border-radius:8px;
    font-weight:600; font-size:0.88rem; margin-left:28px !important;
  }
  .btn-nav-login:hover{background:#eef2ff; color:var(--navy-2) !important;}
  .btn-nav-back{
    display:inline-flex; align-items:center; gap:7px;
    background:rgba(255,255,255,0.08); color:#fff !important;
    padding:9px 18px; border-radius:8px; border:1px solid rgba(255,255,255,0.35);
    font-weight:600; font-size:0.88rem; margin-left:28px !important;
    backdrop-filter:blur(4px);
    transition:background .2s ease, border-color .2s ease, transform .2s ease;
  }
  .btn-nav-back i{font-size:0.95rem;}
  .btn-nav-back:hover{
    background:rgba(255,255,255,0.18); border-color:rgba(255,255,255,0.6);
    color:#fff !important; transform:translateY(-1px);
  }
  .navbar-toggle-btn{color:#fff; background:none; border:none; font-size:1.5rem; display:none; transition:transform .25s ease;}
  .navbar-toggle-btn.is-open{transform:rotate(90deg);}

  .mobile-nav{
    max-height:0; overflow:hidden; opacity:0;
    transition:max-height .35s ease, opacity .3s ease;
  }
  .mobile-nav.is-open{max-height:280px; opacity:1;}
  .mobile-nav a{
    display:block; color:rgba(255,255,255,0.88); font-size:0.92rem; font-weight:500;
    padding:12px 4px; border-top:1px solid rgba(255,255,255,0.12);
  }
  .mobile-nav a:hover{color:#fff;}

  /* ---------- HERO ---------- */
  .hero{
    position:relative;
    background:
      linear-gradient(100deg, rgba(23,37,84,0.88) 0%, rgba(30,41,82,0.6) 42%, rgba(30,41,82,0.28) 70%, rgba(30,41,82,0.15) 100%),
      url('assets/img/hero-section.jpg') center/cover no-repeat;
    overflow:hidden;
    padding:96px 0 0;
  }
  .hero::before{
    content:'';
    position:absolute; inset:0;
    background-image:
      linear-gradient(rgba(255,255,255,0.07) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.07) 1px, transparent 1px);
    background-size:44px 44px;
    mask-image:linear-gradient(180deg, rgba(0,0,0,0.9), transparent 85%);
  }
  .hero-inner{position:relative; z-index:1; display:grid; grid-template-columns:1.05fr 0.95fr; gap:40px; align-items:center;}

  @keyframes heroRise{
    from{opacity:0; transform:translateY(22px);}
    to{opacity:1; transform:translateY(0);}
  }
  .hero-badge, .hero h1, .hero p.lede, .hero-ctas, .mock-card{
    opacity:0; animation:heroRise .7s cubic-bezier(.16,.8,.3,1) forwards;
  }
  .hero-badge{
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(255,255,255,0.16); color:#fff; border:1px solid rgba(255,255,255,0.3);
    padding:7px 14px; border-radius:999px; font-size:0.78rem; font-weight:600; margin-bottom:22px;
    animation-delay:.05s;
  }
  .hero h1{color:#fff; font-weight:800; font-size:clamp(2.1rem, 4vw, 3.1rem); line-height:1.12; margin-bottom:20px; animation-delay:.15s;}
  .hero p.lede{color:rgba(255,255,255,0.88); font-size:1.05rem; max-width:520px; margin-bottom:30px; animation-delay:.28s;}
  .hero-ctas{display:flex; gap:14px; flex-wrap:wrap; margin-bottom:56px; animation-delay:.4s;}
  .btn-hero-primary{
    background:#fff; color:var(--navy-2); padding:13px 26px; border-radius:10px; font-weight:700; font-size:0.92rem;
    display:inline-flex; align-items:center; gap:8px; box-shadow:0 8px 20px rgba(0,0,0,0.15);
    transition:background .2s ease, transform .2s ease, box-shadow .2s ease;
  }
  .btn-hero-primary:hover{background:var(--blue-pale); color:var(--navy-2); transform:translateY(-2px); box-shadow:0 12px 26px rgba(0,0,0,0.2);}
  .btn-hero-ghost{
    border:1.5px solid rgba(255,255,255,0.55); color:#fff; padding:13px 26px; border-radius:10px;
    font-weight:600; font-size:0.92rem; display:inline-flex; align-items:center; gap:8px;
    transition:background .2s ease, transform .2s ease;
  }
  .btn-hero-ghost:hover{background:rgba(255,255,255,0.1); color:#fff; transform:translateY(-2px);}

  /* mock dashboard card floating in hero */
  .mock-card{
    background:#fff; border-radius:16px; box-shadow:0 30px 60px rgba(30,27,75,0.35);
    padding:20px; transform:rotate(1deg);
    animation-name:heroRise, floatCard;
    animation-duration:.7s, 5s;
    animation-timing-function:cubic-bezier(.16,.8,.3,1), ease-in-out;
    animation-iteration-count:1, infinite;
    animation-fill-mode:forwards, none;
    animation-delay:.5s, 1.2s;
    transition:transform .3s ease;
    will-change:transform;
  }
  @keyframes floatCard{
    0%,100%{transform:rotate(1deg) translateY(0);}
    50%{transform:rotate(1deg) translateY(-10px);}
  }
  .mock-card .mock-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;}
  .mock-card .mock-head .dots span{width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:5px;}
  .mock-stats{display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:14px;}
  .mock-stat{border-radius:12px; padding:14px; color:#fff;}
  .mock-stat .n{font-size:1.4rem; font-weight:800;}
  .mock-stat .l{font-size:0.68rem; opacity:0.9; font-weight:600; text-transform:uppercase; letter-spacing:0.03em;}
  .mock-row{
    display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border-radius:10px;
    background:var(--panel); font-size:0.78rem; margin-bottom:8px;
  }
  .mock-row .badge-pill{padding:3px 10px; border-radius:999px; font-size:0.68rem; font-weight:700;}
  .hero-wave{display:block; width:100%; margin-top:70px; position:relative; z-index:1;}

  /* ---------- TRUST STRIP ---------- */
  .trust-strip{
    background:linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 100%);
    padding:44px 0;
    position:relative; overflow:hidden;
  }
  .trust-strip .row-stats{
    position:relative; z-index:1;
    display:grid; grid-template-columns:repeat(4,1fr); gap:20px; text-align:center;
  }
  .trust-strip .stat-item{padding:0 12px; position:relative;}
  .trust-strip .stat-item::before{
    content:''; position:absolute; left:0; top:6px; bottom:6px; width:1px;
    background:rgba(255,255,255,0.16);
  }
  .trust-strip .stat-item:first-child::before{display:none;}
  .trust-strip .stat-icon{
    width:38px; height:38px; border-radius:10px; margin:0 auto 12px;
    background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.18);
    display:flex; align-items:center; justify-content:center; color:#fff; font-size:1rem;
  }
  .trust-strip .num{font-weight:800; font-size:1.6rem; color:#fff;}
  .trust-strip .lab{font-size:0.78rem; color:rgba(255,255,255,0.72); font-weight:500;}

  /* ---------- SECTIONS ---------- */
  section{padding:88px 0;}
  .eyebrow{
    color:var(--blue-accent); font-weight:700; font-size:0.78rem; text-transform:uppercase;
    letter-spacing:0.08em; margin-bottom:12px;
  }
  .section-title{font-weight:800; font-size:clamp(1.6rem,2.6vw,2.3rem); margin-bottom:14px;}
  .section-sub{color:var(--muted); font-size:1rem; max-width:560px;}
  .section-head{margin-bottom:52px;}

  .feature-card{
    background:var(--card-bg, #fff); border:1px solid var(--card-border, #eef0f5);
    border-top:4px solid var(--card-accent, transparent);
    border-radius:16px; padding:30px 26px; height:100%;
    transition:transform .18s ease, box-shadow .18s ease;
  }
  .feature-card:hover{transform:translateY(-4px); box-shadow:0 16px 32px rgba(30,58,138,0.1);}
  .feature-icon{
    width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center;
    font-size:1.25rem; color:#fff; margin-bottom:18px;
    box-shadow:0 6px 14px -4px var(--card-accent, transparent);
  }
  .feature-card h3{font-size:1.08rem; font-weight:700; margin-bottom:10px;}
  .feature-card p{color:var(--muted); font-size:0.9rem; margin:0;}

  /* process */
  .process-section{
    background:linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 100%);
  }
  .process-section .eyebrow{color:#93c5fd;}
  .process-section .section-title{color:#fff;}
  .process-strip{display:grid; grid-template-columns:repeat(4,1fr); gap:0; position:relative;}
  .process-step{padding:0 22px; position:relative;}
  .process-step .step-num{
    width:40px; height:40px; border-radius:50%;
    background:linear-gradient(135deg, var(--blue-light), var(--blue-accent));
    border:2px solid rgba(255,255,255,0.4);
    color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800;
    font-size:0.95rem; margin-bottom:18px;
    box-shadow:0 6px 14px -4px rgba(0,0,0,0.35);
    transition:transform .25s ease, box-shadow .25s ease;
  }
  .process-step:hover .step-num{transform:scale(1.08); box-shadow:0 8px 18px -4px rgba(0,0,0,0.45);}
  .process-step h4{font-weight:700; font-size:1rem; margin-bottom:8px; color:#fff;}
  .process-step p{color:rgba(255,255,255,0.72); font-size:0.86rem;}
  /* Line runs from the center of circle 1 to the center of circle 4.
     Each circle's center sits at (column-left + 22px padding + 20px half-width) = column-left + 42px. */
  .process-strip::before{
    content:''; position:absolute; top:20px; left:42px; right:calc(25% - 42px); height:2px;
    background:repeating-linear-gradient(90deg, rgba(255,255,255,0.55) 0 8px, transparent 8px 16px);
    z-index:0;
  }
  .process-plane{
    position:absolute; top:20px; left:42px; z-index:2;
    width:26px; height:26px; border-radius:50%;
    background:linear-gradient(135deg, #fff, #dbeafe);
    color:var(--navy-2); display:flex; align-items:center; justify-content:center; font-size:0.8rem;
    box-shadow:0 4px 10px rgba(0,0,0,0.35);
    animation:planeFly 7s ease-in-out infinite;
  }
  @keyframes planeFly{
    0%{left:42px; transform:translate(-50%,-50%) rotate(90deg);}
    48%{left:calc(75% + 42px); transform:translate(-50%,-50%) rotate(90deg);}
    50%{left:calc(75% + 42px); transform:translate(-50%,-50%) rotate(270deg);}
    98%{left:42px; transform:translate(-50%,-50%) rotate(270deg);}
    100%{left:42px; transform:translate(-50%,-50%) rotate(90deg);}
  }

  /* roles */
  #roles{
    background:
      linear-gradient(100deg, rgba(23,37,84,0.88) 0%, rgba(30,41,82,0.6) 42%, rgba(30,41,82,0.28) 70%, rgba(30,41,82,0.15) 100%),
      url('assets/img/set.jpg') center/cover no-repeat;
  }
  #roles .eyebrow{color:#93c5fd;}
  #roles .section-title{color:#fff;}
  #roles .section-sub{color:rgba(255,255,255,0.78);}
  .role-card{
    border-radius:16px; padding:26px; height:100%;
    background:linear-gradient(160deg, rgba(30,41,82,0.55) 0%, rgba(23,37,84,0.65) 100%);
    border:1px solid rgba(255,255,255,0.16);
    backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px);
    box-shadow:0 8px 28px rgba(0,0,0,0.25);
    transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
  }
  .role-card:hover{
    transform:translateY(-4px); box-shadow:0 16px 36px rgba(0,0,0,0.35);
    border-color:rgba(255,255,255,0.32);
    background:linear-gradient(160deg, rgba(37,50,97,0.65) 0%, rgba(28,44,99,0.75) 100%);
  }
  .role-card .role-icon{
    width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; color:#fff; margin-bottom:14px; transition:transform .2s ease;
    background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.2);
  }
  .role-card:hover .role-icon{transform:scale(1.1); background:rgba(255,255,255,0.2);}
  .role-card h4{font-weight:700; font-size:1rem; margin-bottom:8px; color:#fff;}
  .role-card p{color:rgba(255,255,255,0.75); font-size:0.86rem; margin:0;}

  /* ---------- CONTACT / PRIVACY / TERMS MODAL ---------- */
  #contactModal .modal-content, #privacyModal .modal-content, #termsModal .modal-content{
    border:none; border-radius:18px; overflow:hidden;
    box-shadow:0 24px 60px rgba(15,23,42,0.28);
  }
  #contactModal .modal-header, #privacyModal .modal-header, #termsModal .modal-header{
    background:linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 100%);
    border:none; padding:22px 26px;
  }
  #contactModal .modal-header .modal-title, #privacyModal .modal-header .modal-title, #termsModal .modal-header .modal-title{
    color:#fff; font-weight:700; font-size:1.05rem; display:flex; align-items:center; gap:10px;
  }
  #contactModal .modal-header .btn-close, #privacyModal .modal-header .btn-close, #termsModal .modal-header .btn-close{filter:invert(1) grayscale(1) brightness(2);}
  #contactModal .modal-body, #privacyModal .modal-body, #termsModal .modal-body{padding:26px;}
  #contactModal .modal-body p.lede, #privacyModal .modal-body p.lede, #termsModal .modal-body p.lede{color:var(--muted); font-size:0.88rem; margin-bottom:20px;}
  #contactModal .contact-block h6, #privacyModal .contact-block h6, #termsModal .contact-block h6{font-weight:700; font-size:0.92rem; margin-bottom:10px; color:var(--ink);}
  #contactModal .contact-block p, #privacyModal .contact-block p, #termsModal .contact-block p{font-size:0.88rem; color:var(--ink); margin-bottom:14px; line-height:1.6;}
  #contactModal .contact-block i, #privacyModal .contact-block i, #termsModal .contact-block i{width:18px; color:var(--blue-accent); margin-right:8px;}
  #contactModal .modal-footer, #privacyModal .modal-footer, #termsModal .modal-footer{border-top:1px solid #eef0f5; padding:16px 26px;}
  #contactModal .btn-close-modal, #privacyModal .btn-close-modal, #termsModal .btn-close-modal{
    background:var(--navy-2); color:#fff; border:none; padding:9px 22px; border-radius:8px;
    font-weight:600; font-size:0.86rem;
  }
  #contactModal .btn-close-modal:hover, #privacyModal .btn-close-modal:hover, #termsModal .btn-close-modal:hover{background:var(--navy-1); color:#fff;}

  /* ---------- FAQ MODAL ---------- */
  #faqModal .modal-content{
    border:none; border-radius:18px; overflow:hidden;
    box-shadow:0 24px 60px rgba(15,23,42,0.28);
  }
  #faqModal .modal-header{
    background:linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 100%);
    border:none; padding:22px 26px;
  }
  #faqModal .modal-header .modal-title{
    color:#fff; font-weight:700; font-size:1.05rem; display:flex; align-items:center; gap:10px;
  }
  #faqModal .modal-header .btn-close{filter:invert(1) grayscale(1) brightness(2);}
  #faqModal .modal-body{padding:22px 26px; max-height:64vh; overflow-y:auto;}
  #faqModal .modal-body p.lede{color:var(--muted); font-size:0.88rem; margin-bottom:18px;}
  #faqModal .modal-footer{border-top:1px solid #eef0f5; padding:16px 26px;}
  #faqModal .btn-close-modal{
    background:var(--navy-2); color:#fff; border:none; padding:9px 22px; border-radius:8px;
    font-weight:600; font-size:0.86rem;
  }
  #faqModal .btn-close-modal:hover{background:var(--navy-1); color:#fff;}

  .faq-accordion .accordion-item{
    border:1px solid #eef0f5; border-radius:12px !important; overflow:hidden; margin-bottom:10px;
  }
  .faq-accordion .accordion-item:last-child{margin-bottom:0;}
  .faq-accordion .accordion-button{
    font-weight:600; font-size:0.9rem; color:var(--ink); padding:14px 18px;
    background:var(--panel);
  }
  .faq-accordion .accordion-button:not(.collapsed){
    color:var(--navy-2); background:var(--blue-pale); box-shadow:none;
  }
  .faq-accordion .accordion-button:focus{box-shadow:none; border-color:#eef0f5;}
  .faq-accordion .accordion-button::after{
    background-size:1.1rem;
  }
  .faq-accordion .accordion-body{
    font-size:0.86rem; color:var(--muted); padding:14px 18px; line-height:1.6; background:#fff;
  }

  /* ---------- BACK TO TOP ---------- */
  #backToTop{
    position:fixed; right:24px; bottom:24px; width:46px; height:46px; border-radius:50%;
    background:linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 100%); color:#fff; border:none;
    display:flex; align-items:center; justify-content:center; font-size:1.15rem;
    box-shadow:0 8px 22px rgba(30,58,138,0.35); cursor:pointer; z-index:900;
    opacity:0; transform:translateY(14px) scale(.9); pointer-events:none;
    transition:opacity .25s ease, transform .25s ease;
  }
  #backToTop.is-visible{opacity:1; transform:translateY(0) scale(1); pointer-events:auto;}
  #backToTop:hover{transform:translateY(-3px) scale(1.05);}

  footer{
    background:#101a33; color:rgba(255,255,255,0.7); padding:48px 0 24px; font-size:0.86rem;
  }
  footer h5{color:#fff; font-weight:700; margin-bottom:14px; font-size:0.95rem;}
  footer a{color:rgba(255,255,255,0.65);}
  footer a:hover{color:#fff;}
  footer .flink{display:block; margin-bottom:9px;}
  .footer-bottom{border-top:1px solid rgba(255,255,255,0.1); margin-top:34px; padding-top:20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; font-size:0.8rem; color:rgba(255,255,255,0.5);}

  /* =======================================================================
     RESPONSIVE BREAKPOINTS
     1024px → Laptop / small desktop
     768px  → Tablets
     480px  → Large mobile
     320px  → Small mobile
     Each tier is cumulative — mobile-first overrides stack on top of the
     wider tier's rules as the viewport shrinks.
     ======================================================================= */

  /* ---------- 1024px : LAPTOP ---------- */
  @media (max-width:1024px){
    .container-xl{max-width:960px;}
    .hero-inner{grid-template-columns:1.05fr 0.95fr; gap:28px;}
    .hero{padding-top:84px;}
    .hero h1{font-size:clamp(1.7rem, 3.6vw, 2.3rem);}
    .hero p.lede{font-size:0.96rem;}
    .mock-card{padding:16px;}
    .mock-stat .n{font-size:1.15rem;}

    .top-navbar nav{display:none !important;}
    .navbar-toggle-btn{display:block;}

    .trust-strip .row-stats{grid-template-columns:repeat(2,1fr); row-gap:28px;}

    section{padding:76px 0;}
    .section-head{margin-bottom:40px;}

    .process-strip{grid-template-columns:1fr 1fr; row-gap:36px;}
    .process-strip::before{display:none;}
    .process-plane{display:none;}

    .role-card{padding:22px;}
  }

  /* ---------- 768px : TABLETS ---------- */
  @media (max-width:768px){
    .hero{padding-top:16px;}
    .hero-inner{grid-template-columns:1fr; gap:44px; text-align:left;}
    .hero-badge{margin-bottom:18px;}
    .hero h1{font-size:clamp(1.7rem, 5.2vw, 2.2rem); margin-bottom:16px;}
    .hero p.lede{font-size:0.96rem; max-width:100%; margin-bottom:24px;}
    .hero-ctas{gap:12px; margin-bottom:40px;}
    .btn-hero-primary, .btn-hero-ghost{padding:12px 20px; font-size:0.88rem; flex:1 1 auto; justify-content:center;}
    .hero-wave{margin-top:40px;}

    .mock-card{max-width:100%; transform:none; padding:18px;}
    .mock-card, .mock-card:hover{transform:none !important;}
    .mock-stats{gap:10px;}
    .mock-stat{padding:12px;}
    .mock-stat .n{font-size:1.2rem;}

    .trust-strip{padding:32px 0;}
    .trust-strip .row-stats{gap:24px 16px;}
    .trust-strip .stat-icon{width:34px; height:34px; margin-bottom:10px;}
    .trust-strip .num{font-size:1.35rem;}
    .trust-strip .lab{font-size:0.72rem;}

    section{padding:56px 0;}
    .section-title{font-size:clamp(1.4rem,4.5vw,1.8rem);}
    .section-sub{font-size:0.92rem;}
    .section-head{margin-bottom:32px;}

    .feature-card{padding:24px 20px;}

    .process-strip{grid-template-columns:1fr; row-gap:26px;}
    .process-step{padding:0;}

    .role-card{padding:22px;}
    #roles .row.g-4 > div{margin-bottom:0;}

    footer{padding:38px 0 20px;}
    footer .col-md-4{margin-bottom:8px;}
    .footer-bottom{flex-direction:column; align-items:flex-start; gap:6px;}

    #contactModal .modal-body, #contactModal .modal-header,
    #privacyModal .modal-body, #privacyModal .modal-header,
    #termsModal .modal-body, #termsModal .modal-header,
    #faqModal .modal-body, #faqModal .modal-header{padding:18px 20px;}

    #backToTop{right:16px; bottom:16px; width:42px; height:42px; font-size:1rem;}
  }

  /* ---------- 480px : LARGE MOBILE ---------- */
  @media (max-width:480px){
    .top-navbar{padding:12px 0;}
    .top-navbar.is-scrolled{padding:8px 0;}
    .top-navbar .brand img{height:30px; width:30px;}
    .top-navbar .brand-text .name{font-size:0.82rem;}
    .top-navbar .brand-text .sub{font-size:0.6rem;}

    .hero-badge{font-size:0.7rem; padding:6px 12px;}
    .hero h1{font-size:1.55rem; line-height:1.2;}
    .hero p.lede{font-size:0.9rem;}
    .hero-ctas{flex-direction:column;}
    .btn-hero-primary, .btn-hero-ghost{width:100%;}

    .mock-stats{grid-template-columns:1fr 1fr; gap:8px;}
    .mock-row{font-size:0.72rem; padding:9px 10px;}

    .trust-strip .row-stats{grid-template-columns:1fr 1fr; gap:22px 14px;}
    .trust-strip .stat-item::before{display:none;}

    section{padding:44px 0;}
    .eyebrow{font-size:0.72rem;}
    .section-title{font-size:1.5rem;}

    .feature-card{padding:20px 18px;}
    .feature-icon{width:42px; height:42px; font-size:1.1rem; margin-bottom:14px;}
    .feature-card h3{font-size:1rem;}
    .feature-card p{font-size:0.86rem;}

    .process-step .step-num{width:36px; height:36px; font-size:0.86rem;}

    .role-card{padding:18px;}
    .role-card .role-icon{width:40px; height:40px; font-size:1.05rem;}

    footer{font-size:0.82rem;}
    footer .row.g-4 > div{margin-bottom:4px;}

    #contactModal .modal-dialog, #privacyModal .modal-dialog, #termsModal .modal-dialog, #faqModal .modal-dialog{margin:12px;}
    .faq-accordion .accordion-button{font-size:0.86rem; padding:12px 14px;}
    .faq-accordion .accordion-body{font-size:0.82rem; padding:12px 14px;}
  }

  /* ---------- 320px : SMALL MOBILE ---------- */
  @media (max-width:320px){
    .container-xl, .container, .container-fluid{padding-left:14px; padding-right:14px;}

    .top-navbar .brand{gap:8px;}
    .top-navbar .brand-text .name{font-size:0.72rem;}
    .top-navbar .brand-text .sub{font-size:0.55rem;}
    .btn-nav-login{padding:7px 14px; font-size:0.8rem;}
    .btn-nav-back{padding:7px 12px; font-size:0.8rem;}

    .hero h1{font-size:1.32rem;}
    .hero p.lede{font-size:0.85rem; margin-bottom:20px;}
    .btn-hero-primary, .btn-hero-ghost{padding:11px 16px; font-size:0.82rem;}

    .mock-card{padding:14px;}
    .mock-stats{grid-template-columns:1fr 1fr; gap:6px;}
    .mock-stat{padding:10px;}
    .mock-stat .n{font-size:1.05rem;}
    .mock-stat .l{font-size:0.6rem;}
    .mock-row{font-size:0.68rem; padding:8px;}

    .trust-strip .row-stats{grid-template-columns:1fr 1fr; gap:18px 10px;}
    .trust-strip .num{font-size:1.15rem;}
    .trust-strip .lab{font-size:0.66rem;}

    section{padding:36px 0;}
    .section-title{font-size:1.3rem;}
    .section-sub{font-size:0.86rem;}

    .feature-card{padding:16px 14px;}
    .feature-card h3{font-size:0.94rem;}
    .feature-card p{font-size:0.82rem;}

    .process-step h4{font-size:0.92rem;}
    .process-step p{font-size:0.8rem;}

    .role-card h4{font-size:0.92rem;}
    .role-card p{font-size:0.8rem;}

    footer{padding:30px 0 16px; font-size:0.78rem;}
    #backToTop{right:12px; bottom:12px; width:38px; height:38px; font-size:0.92rem;}
  }

  @media (prefers-reduced-motion: reduce){
    *, *::before, *::after{
      animation-duration:.01ms !important; animation-iteration-count:1 !important;
      transition-duration:.01ms !important; scroll-behavior:auto !important;
    }
    .reveal{opacity:1; transform:none;}
  }
</style>
</head>
<body>

<!-- NAVBAR -->
<div class="top-navbar" id="topNavbar">
  <div class="container-xl d-flex align-items-center justify-content-between">
    <a href="landingpage.php" class="brand">
      <img src="assets/upad-logo.png" alt="UPAD Logo" onerror="this.style.display='none'">
      <div class="brand-text">
        <div class="name">URBAN PLANNING AND DEVELOPMENT</div>
        <div class="sub">DEVELOPMENT PERMIT MANAGEMENT SYSTEM</div>
      </div>
    </a>
    <nav class="d-flex align-items-center">
      <a href="#features">Features</a>
      <a href="#process">How it Works</a>
      <a href="#roles">Who it's For</a>
      <a href="https://infragovservices.com/" class="btn-nav-back"><i class="bi bi-arrow-left-short"></i> Back to InfraGov</a>
      <a href="login.php" class="btn-nav-login">Log In</a>
    </nav>
    <button class="navbar-toggle-btn" id="navToggleBtn" aria-expanded="false" aria-controls="mobileNav"><i class="bi bi-list"></i></button>
  </div>
  <div class="container-xl mobile-nav" id="mobileNav">
    <a href="#features">Features</a>
    <a href="#process">How it Works</a>
    <a href="#roles">Who it's For</a>
    <a href="https://infragovservices.com/"><i class="bi bi-arrow-left-short"></i> Back to InfraGov</a>
    <a href="login.php">Log In</a>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="container-xl hero-inner">
    <div>
      <div class="hero-badge"><i class="bi bi-patch-check-fill"></i>Quezon City — Digital Permitting Platform</div>
      <h1>Permits, inspections, and zoning — in one system, not six binders.</h1>
      <p class="lede">Replace manual walk-in applications and paper trails with a single portal for development permits, GIS-based zoning checks, field inspections, and full audit trails.</p>
      <div class="hero-ctas">
        <a href="login.php" class="btn-hero-primary"><i class="bi bi-box-arrow-in-right"></i> Log In to Portal</a>
        <a href="register.php" class="btn-hero-ghost"><i class="bi bi-person-plus"></i> Create an Account</a>
      </div>
    </div>

    <div class="mock-card">
      <div class="mock-head">
        <div class="dots">
          <span style="background:#ef4444"></span><span style="background:#f59e0b"></span><span style="background:#10b981"></span>
        </div>
        <span style="font-size:0.72rem; color:var(--muted); font-weight:600;">Dashboard Overview</span>
      </div>
      <div class="mock-stats">
        <div class="mock-stat" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
          <div class="n"><?php echo number_format($lp_stats['total']); ?></div><div class="l">Total Applications</div>
        </div>
        <div class="mock-stat" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
          <div class="n"><?php echo number_format($lp_stats['pending']); ?></div><div class="l">Pending Review</div>
        </div>
        <div class="mock-stat" style="background:linear-gradient(135deg,#10b981,#059669);">
          <div class="n"><?php echo number_format($lp_stats['approved']); ?></div><div class="l">Approved</div>
        </div>
        <div class="mock-stat" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
          <div class="n"><?php echo number_format($lp_stats['rejected']); ?></div><div class="l">Rejected</div>
        </div>
      </div>
      <?php if (!empty($lp_recentActivity)): ?>
        <?php foreach ($lp_recentActivity as $i => $item):
          $badge = lp_status_badge($item['status']);
          $label = trim($item['project_name'] ?: $item['application_number']);
          if (!empty($item['barangay'])) { $label .= ' — ' . $item['barangay']; }
          $isLast = ($i === count($lp_recentActivity) - 1);
        ?>
        <div class="mock-row"<?php echo $isLast ? ' style="margin-bottom:0;"' : ''; ?>>
          <span><i class="bi <?php echo $badge['icon']; ?> me-2" style="color:<?php echo $badge['iconColor']; ?>;"></i><?php echo htmlspecialchars($label); ?></span>
          <span class="badge-pill" style="background:<?php echo $badge['bg']; ?>; color:<?php echo $badge['fg']; ?>;"><?php echo $badge['label']; ?></span>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="mock-row">
          <span><i class="bi bi-file-earmark-text me-2" style="color:var(--blue-accent);"></i>Residential Building Permit — Blk 4 Lot 12</span>
          <span class="badge-pill" style="background:#fef3c7; color:#b45309;">In Review</span>
        </div>
        <div class="mock-row">
          <span><i class="bi bi-clipboard-check me-2" style="color:var(--success);"></i>Site Inspection — Riverside Commercial</span>
          <span class="badge-pill" style="background:#d1fae5; color:#047857;">Scheduled</span>
        </div>
        <div class="mock-row" style="margin-bottom:0;">
          <span><i class="bi bi-map me-2" style="color:var(--purple);"></i>Zoning Overlay — Barangay Poblacion</span>
          <span class="badge-pill" style="background:#e0e7ff; color:#4338ca;">Verified</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <svg class="hero-wave" viewBox="0 0 1440 80" preserveAspectRatio="none" style="height:70px;">
    <path fill="#ffffff" d="M0,32 C240,80 480,0 720,24 C960,48 1200,80 1440,32 L1440,80 L0,80 Z"></path>
  </svg>
</section>

<!-- TRUST STRIP -->
<div class="trust-strip">
  <div class="container-xl row-stats reveal">
    <div class="stat-item">
      <div class="stat-icon"><i class="bi bi-file-earmark-check"></i></div>
      <div class="num" data-count="<?php echo (int)$lp_totalProcessed; ?>" data-suffix="+">0</div><div class="lab">Applications processed</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
      <div class="num" data-count="<?php echo (int)$lp_onTimeRate; ?>" data-suffix="%">0</div><div class="lab">On-time review rate</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon"><i class="bi bi-geo-alt"></i></div>
      <div class="num" data-count="<?php echo (int)$lp_barangaysCovered; ?>" data-suffix="">0</div><div class="lab">Barangays covered</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
      <div class="num" data-count="4" data-suffix="">0</div><div class="lab">Role-based access levels</div>
    </div>
  </div>
</div>

<!-- FEATURES -->
<section id="features">
  <div class="container-xl">
    <div class="section-head reveal">
      <div class="eyebrow">Platform</div>
      <h2 class="section-title">Everything the office and the field team need.</h2>
      <p class="section-sub">Built around the actual permit lifecycle — from a resident's application to a signed-off inspection report.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-4 reveal">
        <div class="feature-card" style="--card-bg:#eff6ff; --card-border:#dbeafe; --card-accent:#3b82f6;">
          <div class="feature-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);"><i class="bi bi-file-earmark-text"></i></div>
          <h3>Digital Permit Applications</h3>
          <p>Residents and developers submit development permit applications online, with status tracking from submission to release.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="feature-card" style="--card-bg:#eef2ff; --card-border:#e0e7ff; --card-accent:#6366f1;">
          <div class="feature-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);"><i class="bi bi-map"></i></div>
          <h3>GIS Zoning Map</h3>
          <p>An interactive map layer for checking zoning classifications and overlays before an application is even filed.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="feature-card" style="--card-bg:#ecfdf5; --card-border:#d1fae5; --card-accent:#10b981;">
          <div class="feature-icon" style="background:linear-gradient(135deg,#10b981,#059669);"><i class="bi bi-clipboard-check"></i></div>
          <h3>Monitoring &amp; Inspections</h3>
          <p>Inspectors get assigned tasks, log site visits, and file findings directly against the application record.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="feature-card" style="--card-bg:#fffbeb; --card-border:#fef3c7; --card-accent:#f59e0b;">
          <div class="feature-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="bi bi-clock-history"></i></div>
          <h3>Real-Time Status Tracking</h3>
          <p>Applicants and staff see exactly where a permit sits — submitted, under review, approved, or rejected — at all times.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="feature-card" style="--card-bg:#fdf2f8; --card-border:#fce7f3; --card-accent:#ec4899;">
          <div class="feature-icon" style="background:linear-gradient(135deg,#ec4899,#db2777);"><i class="bi bi-journal-text"></i></div>
          <h3>Audit Logs</h3>
          <p>Every approval, edit, and status change is recorded, so there's a clear record for accountability and review.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="feature-card" style="--card-bg:#f5f3ff; --card-border:#ede9fe; --card-accent:#8b5cf6;">
          <div class="feature-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);"><i class="bi bi-graph-up"></i></div>
          <h3>Reports &amp; Analytics</h3>
          <p>Admins get a running view of application volume, approval rates, and turnaround time across the LGU.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PROCESS -->
<section id="process" class="process-section">
  <div class="container-xl">
    <div class="section-head reveal">
      <div class="eyebrow">How it works</div>
      <h2 class="section-title">From application to approval.</h2>
    </div>
    <div class="process-strip">
      <div class="process-plane" aria-hidden="true"><i class="bi bi-send-fill"></i></div>
      <div class="process-step reveal">
        <div class="step-num">1</div>
        <h4>Submit</h4>
        <p>Applicant creates an account and files a development permit application with the required documents.</p>
      </div>
      <div class="process-step reveal">
        <div class="step-num">2</div>
        <h4>Review</h4>
        <p>Assessors and admin staff check the application against zoning rules and requirements.</p>
      </div>
      <div class="process-step reveal">
        <div class="step-num">3</div>
        <h4>Inspect</h4>
        <p>An inspector is assigned for a site visit and logs findings straight into the application record.</p>
      </div>
      <div class="process-step reveal">
        <div class="step-num">4</div>
        <h4>Approve</h4>
        <p>The permit is approved or rejected, with the decision and reasoning logged for the record.</p>
      </div>
    </div>
  </div>
</section>

<!-- ROLES -->
<section id="roles">
  <div class="container-xl">
    <div class="section-head reveal">
      <div class="eyebrow">Built for the whole office</div>
      <h2 class="section-title">One system, role-based access.</h2>
      <p class="section-sub">Every user sees only what their role needs — no manual gatekeeping required.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3 reveal">
        <div class="role-card">
          <div class="role-icon"><i class="bi bi-person-badge"></i></div>
          <h4>Administrators</h4>
          <p>Full oversight — user management, audit logs, and reports across all applications.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="role-card">
          <div class="role-icon"><i class="bi bi-calculator"></i></div>
          <h4>Assessors</h4>
          <p>Review applications against zoning and code requirements before approval.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="role-card">
          <div class="role-icon"><i class="bi bi-clipboard-check"></i></div>
          <h4>Inspectors</h4>
          <p>Manage assigned site inspections and submit findings directly from the field.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="role-card">
          <div class="role-icon"><i class="bi bi-people"></i></div>
          <h4>Residents &amp; Developers</h4>
          <p>Apply for permits, upload documents, and track status without an office visit.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-md-4">
        <h5>Urban Planning and Development</h5>
        <p style="color:rgba(255,255,255,0.6); font-size:0.86rem;">Development Permit Management System for local government units — permitting, zoning, and inspections in one place.</p>
      </div>
      <div class="col-6 col-md-2">
        <h5>Platform</h5>
        <a href="#features" class="flink">Features</a>
        <a href="#process" class="flink">How it Works</a>
        <a href="#roles" class="flink">Roles</a>
      </div>
      <div class="col-6 col-md-2">
        <h5>Account</h5>
        <a href="login.php" class="flink">Log In</a>
        <a href="register.php" class="flink">Register</a>
        <a href="forgot_password.php" class="flink">Forgot Password</a>
      </div>
      <div class="col-6 col-md-2">
        <h5>Support</h5>
        <a href="#" class="flink" data-bs-toggle="modal" data-bs-target="#contactModal">Contacts</a>
        <a href="#" class="flink" data-bs-toggle="modal" data-bs-target="#faqModal">FAQs</a>
        <a href="#" class="flink" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
        <a href="#" class="flink" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div>&copy; <?php echo date('Y'); ?> Urban Planning and Development</div>
      <div>Development Permit Management System</div>
    </div>
  </div>
</footer>

<button id="backToTop" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

<!-- CONTACT SUPPORT MODAL -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-headset"></i>Contacts</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="lede">For inquiries regarding urban planning, zoning, and development permits, please contact:</p>
        <div class="contact-block">
          <h6>City Planning and Development Department (CPDD)</h6>
          <p>
            <i class="bi bi-geo-alt"></i>7th Floor, Civic Center Bldg. A, QC Hall Complex<br>
            <i class="bi bi-envelope"></i>cpdd@quezoncity.gov.ph<br>
            <i class="bi bi-telephone"></i>(02) 8988-4242 loc. 1400 / 1404
          </p>
          <h6>Office Hours</h6>
          <p class="mb-0"><i class="bi bi-clock"></i>Monday – Friday, 8:00 AM – 5:00 PM</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-close-modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- PRIVACY POLICY MODAL -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-shield-lock"></i>Data Privacy Policy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="lede">The Local Government Unit (LGU) is committed to protecting the privacy of its stakeholders in accordance with the Data Privacy Act of 2012.</p>
        <div class="contact-block">
          <h6>I. Commitment to Data Privacy</h6>
          <p>The Local Government Unit (LGU) is committed to protecting the privacy of its stakeholders and ensuring that all personal data collected through the Urban Planning and Development Permit Management System are processed in accordance with <strong>Republic Act No. 10173</strong>, otherwise known as the <strong>Data Privacy Act of 2012</strong>.</p>
          <h6>II. Collection and Use of Personal Information</h6>
          <p>We collect personal information solely for the purpose of processing development permits, verifying identity, and official communication regarding urban planning applications. This may include, but is not limited to, names, contact details, and property documents.</p>
          <h6>III. Security Measures</h6>
          <p class="mb-0">Strict organizational, physical, and technical security measures are implemented to protect your data against unauthorized access, alteration, or disclosure. Only authorized LGU personnel are granted access to your information.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-close-modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- TERMS OF SERVICE MODAL -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text"></i>Terms of Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="lede">By accessing and using this portal, you agree to the following terms:</p>
        <div class="contact-block">
          <h6>1. Accuracy of Information</h6>
          <p>Users are responsible for ensuring all submitted documents and data are true, accurate, and up-to-date. Any falsification of public documents is subject to legal action under the Revised Penal Code.</p>
          <h6>2. Proper Use of Portal</h6>
          <p>This system shall be used exclusively for official urban planning and development permit applications. Unauthorized attempts to bypass security or modify data are strictly prohibited.</p>
          <h6>3. Compliance</h6>
          <p class="mb-0">Applications are subject to the National Building Code of the Philippines, local zoning ordinances, and other relevant environmental and safety regulations.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-close-modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- FAQ MODAL -->
<div class="modal fade" id="faqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle"></i>Frequently Asked Questions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="lede">Common questions about applying for and tracking development permits through this portal.</p>
        <div class="accordion faq-accordion" id="faqAccordion">
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                Who can apply for a development permit here?
              </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Any resident, property owner, or developer with a project within the LGU's jurisdiction can register an account and submit a permit application, along with the required supporting documents.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                How long does the review process take?
              </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Most applications are reviewed within 5 business days of submission, provided all required documents are complete. Applications requiring a site inspection may take longer depending on inspector availability.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                What documents do I need to prepare?
              </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Requirements vary by permit type, but generally include proof of property ownership or authorization, project plans, and valid identification. The exact checklist is shown once you start an application.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                Can I track my application status online?
              </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Yes. Once logged in, your dashboard shows real-time status for every application — pending, under review, approved, or rejected — along with any notes from the assessor or inspector.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                What happens if my application is rejected?
              </button>
            </h2>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">You'll receive the specific reason for rejection on your dashboard. Most applications can be revised and resubmitted directly through the portal without starting a new application from scratch.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                Who do I contact if I need further help?
              </button>
            </h2>
            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Reach out to the City Planning and Development Department using the details under "Contacts" in the footer, or visit the office in person during business hours.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-close-modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  // Fix: Bootstrap sets aria-hidden="true" on a modal while its close button
  // (or another focused descendant) still has focus, which browsers correctly
  // flag as an accessibility violation. Blur focus out of the modal first,
  // returning it to the element that triggered the modal, before Bootstrap
  // applies aria-hidden.
  document.querySelectorAll('.modal').forEach(function(modalEl){
    modalEl.addEventListener('hide.bs.modal', function(){
      var focused = document.activeElement;
      if (focused && modalEl.contains(focused)){
        focused.blur();
      }
    });
  });

  // Navbar shrink on scroll + back-to-top visibility
  var navbar = document.getElementById('topNavbar');
  var backToTop = document.getElementById('backToTop');
  function onScroll(){
    var y = window.scrollY || document.documentElement.scrollTop;
    if (navbar) navbar.classList.toggle('is-scrolled', y > 24);
    if (backToTop) backToTop.classList.toggle('is-visible', y > 480);
  }
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  if (backToTop){
    backToTop.addEventListener('click', function(){
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Mobile nav toggle
  var toggleBtn = document.getElementById('navToggleBtn');
  var mobileNav = document.getElementById('mobileNav');
  if (toggleBtn && mobileNav){
    toggleBtn.addEventListener('click', function(){
      var open = mobileNav.classList.toggle('is-open');
      toggleBtn.classList.toggle('is-open', open);
      toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mobileNav.querySelectorAll('a').forEach(function(link){
      link.addEventListener('click', function(){
        mobileNav.classList.remove('is-open');
        toggleBtn.classList.remove('is-open');
        toggleBtn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Scroll-triggered reveal animations
  var revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry, i){
        if (entry.isIntersecting){
          var el = entry.target;
          setTimeout(function(){ el.classList.add('is-visible'); }, (i % 6) * 70);
          io.unobserve(el);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
  } else {
    revealEls.forEach(function(el){ el.classList.add('is-visible'); });
  }

  // Animated counters for the trust strip
  var counters = document.querySelectorAll('.num[data-count]');
  function animateCounter(el){
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var suffix = el.getAttribute('data-suffix') || '';
    var duration = 1400;
    var start = null;
    function step(ts){
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = Math.round(target * eased);
      el.textContent = value.toLocaleString() + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  if (counters.length){
    if ('IntersectionObserver' in window){
      var counterIo = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if (entry.isIntersecting){
            animateCounter(entry.target);
            counterIo.unobserve(entry.target);
          }
        });
      }, { threshold: 0.5 });
      counters.forEach(function(el){ counterIo.observe(el); });
    } else {
      counters.forEach(animateCounter);
    }
  }

  // Subtle cursor-tilt on the hero mock dashboard card
  var mockCard = document.querySelector('.mock-card');
  if (mockCard && window.matchMedia('(hover: hover)').matches){
    mockCard.addEventListener('mousemove', function(e){
      var rect = mockCard.getBoundingClientRect();
      var relX = (e.clientX - rect.left) / rect.width - 0.5;
      var relY = (e.clientY - rect.top) / rect.height - 0.5;
      mockCard.style.transform = 'rotate(1deg) translateY(-4px) rotateX(' + (relY * -6) + 'deg) rotateY(' + (relX * 8) + 'deg)';
    });
    mockCard.addEventListener('mouseleave', function(){
      mockCard.style.transform = '';
    });
  }
})();
</script>

</body>
</html>
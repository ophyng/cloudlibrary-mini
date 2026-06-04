<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CloudLibrary Mini — Perpustakaan Digital</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Nunito:wght@400;600;700;800;900&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root{
      --navy:#0b1e38;--d1:#1e3a5f;--d2:#2d5986;--d3:#5b8fb9;--d4:#a8c8e8;
      --pk:#f472b6;--pk2:#fbbfd8;--gold:#f9c74f;
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}
    body{font-family:'Nunito',sans-serif;background:var(--navy);color:#fff;overflow-x:hidden;}

    /* ══ NAVBAR ══ */
    nav{
      position:fixed;top:0;width:100%;z-index:200;
      padding:0 60px;height:68px;
      display:flex;align-items:center;justify-content:space-between;
      background:rgba(11,30,56,0.55);
      backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
      border-bottom:1px solid rgba(255,255,255,0.08);
      transition:background .3s;
    }
    nav.scrolled{background:rgba(11,30,56,0.92);}
    .nav-logo{display:flex;align-items:center;gap:11px;text-decoration:none;}
    .logo-svg{width:46px;height:46px;flex-shrink:0;}
    .nav-logo-text{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:#fff;line-height:1.1;}
    .nav-logo-text span{display:block;font-size:9px;font-weight:700;color:rgba(255,255,255,0.45);letter-spacing:2px;text-transform:uppercase;}
    .nav-links{display:flex;gap:4px;align-items:center;}
    .nav-links a{color:rgba(255,255,255,0.60);text-decoration:none;font-size:13px;font-weight:700;padding:7px 16px;border-radius:100px;transition:all .2s;display:inline-flex;align-items:center;gap:5px;}
    .nav-links a:hover{color:#fff;background:rgba(255,255,255,0.10);}
    .nav-cta{background:linear-gradient(135deg,var(--d2),var(--d3))!important;color:#fff!important;box-shadow:0 4px 16px rgba(45,89,134,0.45);}
    .nav-cta:hover{transform:translateY(-2px)!important;box-shadow:0 8px 24px rgba(45,89,134,0.55)!important;}

    /* ══ HERO ══ */
    .hero{
      position:relative;min-height:100vh;
      display:grid;grid-template-columns:1fr 1fr;
      overflow:hidden;
    }
    /* KIRI — background navy, teks */
    .hero-left{
      position:relative;z-index:2;
      display:flex;align-items:center;
      padding:130px 50px 90px 60px;
      background:linear-gradient(135deg,#0b1e38 0%,#152d55 100%);
    }
    .hero-left::before{
      content:'';position:absolute;inset:0;pointer-events:none;
      background-image:radial-gradient(circle,rgba(255,255,255,0.055) 1px,transparent 1px);
      background-size:28px 28px;
    }
    .hero-left::after{
      content:'';position:absolute;inset:0;pointer-events:none;
      background:radial-gradient(ellipse 80% 60% at 0% 70%,rgba(244,114,182,0.12) 0%,transparent 65%);
    }
    /* KANAN — foto natural tanpa stretch */
    .hero-right{position:relative;overflow:hidden;}
    .hero-photo{width:100%;height:100%;object-fit:cover;object-position:center center;display:block;}
    /* Fade kiri tipis di sisi foto supaya menyatu */
    .hero-right::before{
      content:'';position:absolute;inset:0;z-index:1;pointer-events:none;
      background:linear-gradient(90deg,rgba(11,30,56,0.35) 0%,transparent 30%);
    }
    .hero-photo-fade{display:none;}
    .hero-dots{display:none;}
    .hero-inner{position:relative;z-index:2;width:100%;}
    .hero-content{max-width:520px;}
    .hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(249,199,79,0.14);border:1px solid rgba(249,199,79,0.35);color:var(--gold);font-size:11px;font-weight:900;letter-spacing:2px;text-transform:uppercase;padding:6px 16px;border-radius:100px;margin-bottom:28px;opacity:0;animation:fadeUp .6s .2s forwards;}
    .hero-h1{font-family:'Syne',sans-serif;font-size:clamp(36px,4vw,60px);font-weight:900;line-height:1.08;color:#fff;margin-bottom:20px;opacity:0;animation:fadeUp .6s .4s forwards;}
    .hero-h1 .gradient-text{display:block;background:linear-gradient(90deg,var(--pk),var(--pk2) 50%,var(--gold));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
    .hero-p{font-size:15px;color:rgba(255,255,255,0.78);line-height:1.85;margin-bottom:40px;font-weight:600;opacity:0;animation:fadeUp .6s .6s forwards;}
    .hero-btns{display:flex;gap:12px;flex-wrap:wrap;opacity:0;animation:fadeUp .6s .8s forwards;}
    @media(max-width:860px){.hero{grid-template-columns:1fr;}.hero-right{display:none;}.hero-left{padding:120px 24px 80px;}}
    .btn-a{display:inline-flex;align-items:center;gap:8px;padding:14px 30px;border-radius:100px;font-family:'Nunito',sans-serif;font-size:14px;font-weight:900;text-decoration:none;transition:all .25s;}
    .btn-fill{background:linear-gradient(135deg,var(--d1),var(--d2),var(--d3));color:#fff;box-shadow:0 6px 24px rgba(45,89,134,0.50);}
    .btn-fill:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(45,89,134,0.60);}
    .btn-ghost{background:rgba(255,255,255,0.08);border:1.5px solid rgba(255,255,255,0.22);color:#fff;backdrop-filter:blur(8px);}
    .btn-ghost:hover{background:rgba(255,255,255,0.16);border-color:rgba(255,255,255,0.38);}

    /* Floating mini books */
    .hero-books{position:absolute;bottom:52px;left:60px;z-index:3;display:flex;gap:7px;align-items:flex-end;opacity:0;animation:fadeUp .8s 1.0s forwards;}
    .spine{width:20px;border-radius:3px 7px 7px 3px;box-shadow:2px 4px 14px rgba(0,0,0,0.50);animation:spineFloat 4s ease-in-out infinite;}
    .spine:nth-child(2){animation-delay:.4s;}.spine:nth-child(3){animation-delay:.8s;}.spine:nth-child(4){animation-delay:1.2s;}.spine:nth-child(5){animation-delay:1.6s;}
    @keyframes spineFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}

    /* Floating badges */
    .float-badge{
      position:absolute;z-index:3;
      background:rgba(11,30,56,0.70);
      border:1px solid rgba(255,255,255,0.18);
      backdrop-filter:blur(18px);border-radius:16px;padding:13px 18px;
      display:flex;align-items:center;gap:12px;
      animation:badgeFloat 5s ease-in-out infinite;
    }
    .fb1{right:6%;top:28%;animation-delay:0s;}
    .fb2{right:3%;top:54%;animation-delay:2s;}
    .fb-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;}
    .fb-txt{font-size:12px;font-weight:800;color:#fff;line-height:1.3;}
    .fb-sub{font-size:10px;color:rgba(255,255,255,0.50);font-weight:600;}
    @keyframes badgeFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-10px);}}

    /* ══ STATS ══ */
    .stats-bar{position:relative;z-index:2;background:rgba(255,255,255,0.05);border-top:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);display:grid;grid-template-columns:repeat(4,1fr);}
    .si{padding:30px 32px;text-align:center;border-right:1px solid rgba(255,255,255,0.07);opacity:0;animation:fadeUp .5s forwards;}
    .si:last-child{border-right:none;}
    .si:nth-child(1){animation-delay:.1s;}.si:nth-child(2){animation-delay:.2s;}.si:nth-child(3){animation-delay:.3s;}.si:nth-child(4){animation-delay:.4s;}
    .si-num{font-family:'Syne',sans-serif;font-size:36px;font-weight:900;color:#fff;line-height:1;}
    .si-num em{font-style:normal;color:var(--gold);}
    .si-lbl{font-size:11px;color:rgba(255,255,255,0.45);margin-top:6px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;}

    /* ══ SECTIONS ══ */
    section{padding:88px 60px;position:relative;z-index:2;}
    .sec-inner{max-width:1200px;margin:0 auto;}
    .tag-pill{display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--pk);background:rgba(244,114,182,0.12);border:1px solid rgba(244,114,182,0.28);padding:5px 14px;border-radius:100px;margin-bottom:14px;}
    .sec-h2{font-family:'Syne',sans-serif;font-size:clamp(24px,3vw,42px);font-weight:900;color:#fff;line-height:1.15;margin-bottom:12px;}
    .sec-h2 .au{color:var(--gold);}
    .sec-p{color:rgba(255,255,255,0.52);font-size:14px;line-height:1.85;max-width:480px;font-weight:600;}

    /* ══ GENRE ══ */
    .genre-bg{background:linear-gradient(180deg,rgba(255,255,255,0.025) 0%,transparent 100%);}
    .genre-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(165px,1fr));gap:13px;margin-top:42px;}
    .genre-card{background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.10);border-radius:20px;padding:24px 14px 20px;text-align:center;text-decoration:none;color:#fff;cursor:pointer;transition:all .22s;position:relative;overflow:hidden;}
    .genre-card::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:20px 20px 0 0;opacity:0;transition:opacity .2s;}
    .genre-card:hover{transform:translateY(-6px);border-color:rgba(255,255,255,0.24);background:rgba(255,255,255,0.10);box-shadow:0 18px 44px rgba(0,0,0,0.38);}
    .genre-card:hover::after{opacity:1;}
    .gc-ico{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,0.35);}
    .gc-name{font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:#fff;margin-bottom:4px;}
    .gc-ct{font-size:11px;color:rgba(255,255,255,0.42);font-weight:600;}
    .gc-badge{display:inline-block;font-size:9px;font-weight:900;letter-spacing:.8px;text-transform:uppercase;padding:3px 10px;border-radius:100px;margin-top:10px;}
    .b-fiksi{background:rgba(45,89,134,0.35);color:var(--d4);border:1px solid rgba(91,143,185,0.38);}
    .b-nonfiksi{background:rgba(249,199,79,0.14);color:var(--gold);border:1px solid rgba(249,199,79,0.32);}

    /* Per-genre accent line colors */
    .g0::after{background:linear-gradient(90deg,#3949ab,#5c6bc0);}
    .g1::after{background:linear-gradient(90deg,#7b1fa2,#ab47bc);}
    .g2::after{background:linear-gradient(90deg,#2e7d32,#43a047);}
    .g3::after{background:linear-gradient(90deg,#c62828,#ef5350);}
    .g4::after{background:linear-gradient(90deg,#b71c1c,#d32f2f);}
    .g5::after{background:linear-gradient(90deg,#e65100,#ff6d00);}
    .g6::after{background:linear-gradient(90deg,#006064,#0097a7);}
    .g7::after{background:linear-gradient(90deg,#37474f,#546e7a);}
    .g8::after{background:linear-gradient(90deg,#1565c0,#1976d2);}
    .g9::after{background:linear-gradient(90deg,#4e342e,#6d4c41);}

    /* ══ FITUR ══ */
    .fitur-bg{background:rgba(255,255,255,0.02);border-top:1px solid rgba(255,255,255,0.06);}
    .fitur-head{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;margin-bottom:42px;}
    .fitur-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
    @media(max-width:860px){.fitur-grid{grid-template-columns:1fr 1fr;}}
    @media(max-width:540px){.fitur-grid{grid-template-columns:1fr;}}
    .fc{background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.09);border-radius:20px;padding:28px 22px;position:relative;overflow:hidden;transition:all .22s;}
    .fc:hover{transform:translateY(-5px);border-color:rgba(255,255,255,0.20);background:rgba(255,255,255,0.09);}
    .fc-line{position:absolute;top:0;left:0;right:0;height:3px;border-radius:20px 20px 0 0;}
    .l0{background:linear-gradient(90deg,#38bdf8,#2d5986);}
    .l1{background:linear-gradient(90deg,#f472b6,#fb7185);}
    .l2{background:linear-gradient(90deg,#f9c74f,#f97316);}
    .l3{background:linear-gradient(90deg,#a78bfa,#7c3aed);}
    .l4{background:linear-gradient(90deg,#34d399,#059669);}
    .l5{background:linear-gradient(90deg,#f472b6,#f9c74f);}
    .fc-ico{width:48px;height:48px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:16px;background:rgba(255,255,255,0.10);color:#fff;}
    .fc h4{font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:#fff;margin-bottom:9px;}
    .fc p{font-size:12px;color:rgba(255,255,255,0.52);line-height:1.72;font-weight:600;}

    /* ══ QUOTE ══ */
    .quote-bg{background:linear-gradient(135deg,rgba(45,89,134,0.28) 0%,rgba(91,143,185,0.14) 100%);border-top:1px solid rgba(255,255,255,0.07);border-bottom:1px solid rgba(255,255,255,0.07);}
    .quote-center{max-width:760px;margin:0 auto;text-align:center;}
    .qi{width:56px;height:56px;border-radius:18px;background:rgba(249,199,79,0.14);border:1px solid rgba(249,199,79,0.28);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--gold);margin:0 auto 26px;}
    .qt{font-family:'DM Serif Display',serif;font-size:clamp(18px,2.4vw,28px);color:#fff;line-height:1.55;font-style:italic;margin-bottom:22px;}
    .qt strong{color:var(--gold);font-style:normal;}
    .qsub{font-size:11px;color:rgba(255,255,255,0.35);font-weight:700;letter-spacing:1.5px;text-transform:uppercase;}

    /* ══ CTA ══ */
    .cta-wrap{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--d1) 0%,var(--d2) 55%,var(--d3) 100%);}
    .cta-glow{position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 55% 50% at 20% 50%,rgba(244,114,182,0.18) 0%,transparent 65%),radial-gradient(ellipse 45% 40% at 80% 50%,rgba(249,199,79,0.12) 0%,transparent 65%);}
    .cta-dots{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.06) 1px,transparent 1px);background-size:32px 32px;}
    .cta-inner{position:relative;z-index:1;max-width:680px;margin:0 auto;text-align:center;}
    .cta-wrap section{padding:88px 60px;}
    .cta-inner h2{font-family:'Syne',sans-serif;font-size:clamp(28px,4vw,54px);font-weight:900;color:#fff;line-height:1.1;margin-bottom:16px;}
    .cta-inner h2 em{font-style:normal;color:var(--pk2);}
    .cta-inner p{font-size:14px;color:rgba(255,255,255,0.65);font-weight:600;line-height:1.8;margin-bottom:36px;}
    .cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .btn-white{display:inline-flex;align-items:center;gap:8px;padding:14px 30px;border-radius:100px;background:#fff;color:var(--d1);font-size:14px;font-weight:900;text-decoration:none;box-shadow:0 6px 24px rgba(0,0,0,0.22);transition:all .25s;font-family:'Nunito',sans-serif;}
    .btn-white:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,0.30);}
    .btn-outline-w{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;border-radius:100px;background:transparent;border:1.5px solid rgba(255,255,255,0.42);color:#fff;font-size:14px;font-weight:800;text-decoration:none;transition:all .25s;font-family:'Nunito',sans-serif;}
    .btn-outline-w:hover{background:rgba(255,255,255,0.14);border-color:rgba(255,255,255,0.65);}

    /* ══ FOOTER ══ */
    footer{background:rgba(0,0,0,0.30);border-top:1px solid rgba(255,255,255,0.07);padding:26px 60px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;position:relative;z-index:2;}
    .foot-logo{display:flex;align-items:center;gap:10px;}
    .foot-ico{width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,var(--d2),var(--d3));display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;}
    .foot-txt{font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:#fff;}
    footer p{font-size:12px;color:rgba(255,255,255,0.35);font-weight:600;}

    /* ══ ANIM ══ */
    @keyframes fadeUp{from{opacity:0;transform:translateY(22px);}to{opacity:1;transform:translateY(0);}}
    .reveal{opacity:0;transform:translateY(26px);transition:opacity .65s ease,transform .65s ease;}
    .reveal.vis{opacity:1;transform:translateY(0);}
    .reveal-delay-1{transition-delay:.10s;}.reveal-delay-2{transition-delay:.20s;}.reveal-delay-3{transition-delay:.30s;}

    @media(max-width:900px){
      nav{padding:0 20px;}
      .hero-inner{padding:110px 20px 80px;}
      .hero-books{left:20px;bottom:40px;}
      .float-badge{display:none;}
      .stats-bar{grid-template-columns:1fr 1fr;}
      .si{border-right:none;border-bottom:1px solid rgba(255,255,255,0.07);}
      .si:nth-child(even){border-right:none;}
      section{padding:64px 20px;}
      footer{padding:22px 20px;flex-direction:column;text-align:center;}
      .cta-wrap section{padding:64px 20px;}
    }
  </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav id="mainNav">
  <a href="#" class="nav-logo">
    <!-- PREMIUM SVG LOGO -->
    <svg class="logo-svg" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="logoBase" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#0f2744"/>
          <stop offset="100%" stop-color="#1e4a82"/>
        </linearGradient>
        <linearGradient id="logoGold" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#f9c74f"/>
          <stop offset="60%" stop-color="#f472b6"/>
          <stop offset="100%" stop-color="#a78bfa"/>
        </linearGradient>
        <linearGradient id="pageL" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="rgba(255,255,255,0.28)"/>
          <stop offset="100%" stop-color="rgba(255,255,255,0.10)"/>
        </linearGradient>
        <linearGradient id="pageR" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="rgba(255,255,255,0.18)"/>
          <stop offset="100%" stop-color="rgba(255,255,255,0.06)"/>
        </linearGradient>
        <filter id="glow">
          <feGaussianBlur stdDeviation="1.5" result="blur"/>
          <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
      </defs>
      <!-- Outer ring glow -->
      <circle cx="22" cy="22" r="21" fill="none" stroke="url(#logoGold)" stroke-width="0.6" opacity="0.45"/>
      <!-- Main background -->
      <rect x="3" y="3" width="38" height="38" rx="12" fill="url(#logoBase)"/>
      <!-- Inner shine top -->
      <rect x="3" y="3" width="38" height="16" rx="12" fill="rgba(255,255,255,0.06)"/>
      <!-- Open book left page -->
      <path d="M7,31 L7,15 Q7,13 9.5,12.5 L21,11 L21,31 Z" fill="url(#pageL)" stroke="rgba(255,255,255,0.35)" stroke-width="0.8"/>
      <!-- Open book right page -->
      <path d="M23,11 L34.5,12.5 Q37,13 37,15 L37,31 L23,31 Z" fill="url(#pageR)" stroke="rgba(255,255,255,0.25)" stroke-width="0.8"/>
      <!-- Book spine -->
      <line x1="22" y1="11" x2="22" y2="31" stroke="rgba(255,255,255,0.80)" stroke-width="1.2" stroke-linecap="round"/>
      <!-- Bottom shadow/base -->
      <path d="M7,31 Q7,33 9,33 L22,31 L35,33 Q37,33 37,31" fill="rgba(0,0,0,0.20)" stroke="rgba(255,255,255,0.20)" stroke-width="0.7"/>
      <!-- Lines left page -->
      <line x1="9.5" y1="17" x2="19.5" y2="16.5" stroke="rgba(255,255,255,0.45)" stroke-width="1" stroke-linecap="round"/>
      <line x1="9.5" y1="20.5" x2="18.5" y2="20" stroke="rgba(255,255,255,0.30)" stroke-width="1" stroke-linecap="round"/>
      <line x1="9.5" y1="24" x2="19" y2="23.5" stroke="rgba(255,255,255,0.25)" stroke-width="1" stroke-linecap="round"/>
      <!-- Lines right page -->
      <line x1="24.5" y1="16.5" x2="34.5" y2="17" stroke="rgba(255,255,255,0.45)" stroke-width="1" stroke-linecap="round"/>
      <line x1="25.5" y1="20" x2="34.5" y2="20.5" stroke="rgba(255,255,255,0.30)" stroke-width="1" stroke-linecap="round"/>
      <line x1="25" y1="23.5" x2="34.5" y2="24" stroke="rgba(255,255,255,0.25)" stroke-width="1" stroke-linecap="round"/>
      <!-- Gold star accent top-right -->
      <circle cx="34" cy="10" r="7" fill="url(#logoBase)" stroke="url(#logoGold)" stroke-width="1" filter="url(#glow)"/>
      <polygon points="34,6 35.2,9.2 38.5,9.2 35.8,11.3 36.8,14.5 34,12.5 31.2,14.5 32.2,11.3 29.5,9.2 32.8,9.2" fill="url(#logoGold)"/>
    </svg>
    <div class="nav-logo-text">
      CloudLibrary Mini
      <span>Perpustakaan Digital</span>
    </div>
  </a>
  <div class="nav-links">
    <a href="#genre"><i class="fas fa-th" style="font-size:10px;"></i> Koleksi</a>
    <a href="#fitur"><i class="fas fa-star" style="font-size:10px;"></i> Fitur</a>
    <a href="auth/login.php" class="nav-cta"><i class="fas fa-sign-in-alt"></i> Masuk</a>
  </div>
</nav>

<!-- ══ HERO ══ -->
<section class="hero">
  <!-- KIRI: teks -->
  <div class="hero-left">
    <div class="hero-inner">
      <div class="hero-content">
        <h1 class="hero-h1">
          Baca Lebih Banyak,<br>
          <span class="gradient-text">Jelajahi Tanpa Batas</span>
        </h1>
        <p class="hero-p">Perpustakaan digital modern — pinjam, baca, dan kelola buku favoritmu kapan saja dan di mana saja. Sistem otomatis dengan notifikasi cerdas.</p>
        <div class="hero-btns">
          <a href="auth/register.php" class="btn-a btn-fill"><i class="fas fa-user-plus"></i> Daftar Gratis Sekarang</a>
          <a href="auth/login.php" class="btn-a btn-ghost"><i class="fas fa-sign-in-alt"></i> Sudah Punya Akun</a>
        </div>
      </div>
    </div>
  </div>
  <!-- KANAN: foto natural -->
  <div class="hero-right">
    <img src="Beautiful School Library.jpg" alt="Library" class="hero-photo">
  </div>
</section>

<!-- ══ STATS ══ -->
<div class="stats-bar">
  <div class="si"><div class="si-num">100<em>+</em></div><div class="si-lbl">Koleksi Buku</div></div>
  <div class="si"><div class="si-num">10</div><div class="si-lbl">Genre Tersedia</div></div>
  <div class="si"><div class="si-num">2</div><div class="si-lbl">Tipe Buku</div></div>
  <div class="si"><div class="si-num">24<em>/7</em></div><div class="si-lbl">Bisa Diakses</div></div>
</div>

<!-- ══ GENRE ══ -->
<section id="genre" class="genre-bg">
  <div class="sec-inner">
    <div class="reveal">
      <div class="tag-pill"><i class="fas fa-th" style="font-size:9px;"></i> Koleksi Buku</div>
      <h2 class="sec-h2">Temukan Buku <span class="au">Favoritmu</span></h2>
      <p class="sec-p">Dari novel klasik hingga buku sains — koleksi lengkap untuk semua selera pembaca.</p>
    </div>
    <div class="genre-grid">
      <?php
      $genres=[
        ['Novel',   'fa-book',       '#1a237e','#283593','fiksi'],
        ['Cerpen',  'fa-file-alt',   '#4a148c','#6a1b9a','fiksi'],
        ['Fantasi', 'fa-hat-wizard', '#1b5e20','#2e7d32','fiksi'],
        ['Romance', 'fa-heart',      '#880e4f','#ad1457','fiksi'],
        ['Horror',  'fa-ghost',      '#b71c1c','#c62828','fiksi'],
        ['Misteri', 'fa-search',     '#e65100','#f57c00','fiksi'],
        ['Sci-Fi',  'fa-rocket',     '#006064','#00838f','fiksi'],
        ['Filsafat','fa-landmark',   '#37474f','#546e7a','nonfiksi'],
        ['Sains',   'fa-flask',      '#1565c0','#1976d2','nonfiksi'],
        ['Biografi','fa-user',       '#4e342e','#6d4c41','nonfiksi'],
      ];
      foreach($genres as $i=>[$name,$icon,$c1,$c2,$tipe]):
      ?>
      <a href="auth/login.php" class="genre-card g<?= $i ?> reveal reveal-delay-<?= ($i%3)+1 ?>">
        <div class="gc-ico" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);">
          <i class="fas <?= $icon ?>"></i>
        </div>
        <div class="gc-name"><?= $name ?></div>
        <div class="gc-ct">10 buku</div>
        <span class="gc-badge <?= $tipe==='fiksi'?'b-fiksi':'b-nonfiksi' ?>"><?= $tipe==='fiksi'?'Fiksi':'Non-Fiksi' ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ FITUR ══ -->
<section id="fitur" class="fitur-bg">
  <div class="sec-inner">
    <div class="fitur-head reveal">
      <div>
        <div class="tag-pill"><i class="fas fa-bolt" style="font-size:9px;"></i> Fitur Unggulan</div>
        <h2 class="sec-h2">Lebih dari Sekadar <span class="au">Perpustakaan</span></h2>
        <p class="sec-p">Sistem cerdas yang memudahkan proses peminjaman dan membaca buku digital.</p>
      </div>
      <a href="auth/register.php" style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:100px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.16);color:#fff;text-decoration:none;font-size:13px;font-weight:800;white-space:nowrap;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
        <i class="fas fa-arrow-right"></i> Coba Sekarang
      </a>
    </div>
    <div class="fitur-grid">
      <?php
      $fiturs=[
        ['fa-clock',    'l0','Batas Waktu Otomatis',  'Akses buku otomatis ditutup saat jatuh tempo. Tidak perlu pengembalian manual ke petugas.'],
        ['fa-book-open','l1','Baca Online',             'PDF viewer langsung di browser. Tidak perlu download — langsung baca kapan saja.'],
        ['fa-bell',     'l2','Notifikasi Cerdas',       'Notifikasi H-1 sebelum batas peminjaman habis. Perpanjang langsung dari notifikasi.'],
        ['fa-star',     'l3','Review &amp; Rating',     'Beri ulasan dan rating untuk buku yang sudah dibaca. Bantu sesama menemukan buku terbaik.'],
        ['fa-trophy',   'l4','Poin &amp; Badge',        'Kumpulkan poin dari setiap aktivitas membaca dan review. Raih badge eksklusif.'],
        ['fa-heart',    'l5','Wishlist Pribadi',        'Simpan buku favorit ke wishlist. Pinjam langsung dari daftar wishlist kapan saja.'],
      ];
      foreach($fiturs as $i=>[$icon,$lc,$title,$desc]):
      ?>
      <div class="fc reveal reveal-delay-<?= ($i%3)+1 ?>">
        <div class="fc-line <?= $lc ?>"></div>
        <div class="fc-ico"><i class="fas <?= $icon ?>"></i></div>
        <h4><?= $title ?></h4>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ QUOTE ══ -->
<section class="quote-bg">
  <div class="sec-inner">
    <div class="quote-center reveal">
      <div class="qi"><i class="fas fa-quote-left"></i></div>
      <div class="qt">Membaca adalah jendela dunia. <strong>CloudLibrary Mini</strong> menghadirkan perpustakaan digital berbasis cloud yang cerdas, modern, dan mudah diakses oleh siapa saja.</div>
      <div class="qsub"><i class="fas fa-cloud" style="margin-right:6px;color:rgba(255,255,255,0.30);font-size:11px;"></i>CloudLibrary Mini — Perpustakaan Digital</div>
    </div>
  </div>
</section>

<!-- ══ CTA ══ -->
<div class="cta-wrap">
  <div class="cta-glow"></div>
  <div class="cta-dots"></div>
  <section>
    <div class="cta-inner reveal">
      <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.20);color:rgba(255,255,255,0.80);font-size:11px;font-weight:900;letter-spacing:2px;text-transform:uppercase;padding:5px 16px;border-radius:100px;margin-bottom:22px;">
        <i class="fas fa-bolt" style="color:var(--gold);font-size:10px;"></i> Gratis &amp; Mudah
      </div>
      <h2>Siap Mulai <em>Membaca?</em></h2>
      <p>Daftar gratis dan akses ratusan buku digital sekarang juga — pinjam, baca, dan kumpulkan badge eksklusifmu.</p>
      <div class="cta-btns">
        <a href="auth/register.php" class="btn-white"><i class="fas fa-user-plus" style="color:var(--d1);"></i> Daftar Sekarang</a>
        <a href="auth/login.php" class="btn-outline-w"><i class="fas fa-sign-in-alt"></i> Login</a>
      </div>
    </div>
  </section>
</div>

<!-- ══ FOOTER ══ -->
<footer>
  <div class="foot-logo">
    <div class="foot-ico"><i class="fas fa-cloud"></i></div>
    <span class="foot-txt">CloudLibrary Mini</span>
  </div>
  <p>Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?php echo date('Y'); ?></p>
</footer>

<script>
const nav=document.getElementById('mainNav');
window.addEventListener('scroll',()=>nav.classList.toggle('scrolled',window.scrollY>40));
const obs=new IntersectionObserver(e=>e.forEach(en=>{if(en.isIntersecting){en.target.classList.add('vis');obs.unobserve(en.target);}}),{threshold:.12});
document.querySelectorAll('.reveal').forEach(r=>obs.observe(r));
</script>
</body>
</html>
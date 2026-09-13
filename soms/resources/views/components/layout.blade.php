@props(['title' => 'Dashboard'])
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} — SOMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --radius-sm:8px; --radius-md:12px; --radius-lg:18px;
  --ease:cubic-bezier(.4,0,.2,1);
  --font-ui:'Inter',system-ui,sans-serif;
  --font-display:'Inter Tight','Inter',system-ui,sans-serif;

  /* "Lively" light-first palette (Sept 2026 redesign) — vivid blue primary
     against light content, dark navy reserved for the sidebar/chrome only
     (see the .sidebar block below, which locally re-scopes these same
     variable names to on-dark equivalents via CSS custom-property
     cascading — every component that reads var(--text-muted) etc. picks
     up the right value automatically depending on whether it's inside
     the sidebar or the main content area, with zero per-component
     changes needed). */
  --primary:#2563EB; --violet:#4F7DF3;
  --primary-soft:rgba(37,99,235,.12);
  --purple:#8B5CF6; --purple-soft:rgba(139,92,246,.12);
  --emerald:#10B981; --emerald-soft:rgba(16,185,129,.12);
  --amber:#F59E0B; --amber-soft:rgba(245,158,11,.12);
  --rose:#F43F5E; --rose-soft:rgba(244,63,94,.12);
  /* Accessibility audit (Sep 2026) — see resources/views/layouts/app.blade.php
     for the contrast-ratio rationale. Same fix, duplicated here since this
     file re-declares the same palette rather than sharing it. */
  --emerald-text:#0B7C56; --amber-text:#996206; --rose-text:#D00C2E;
  --bg:#F4F6FB; --bg-elevated:#0B1330; --surface:#FFFFFF; --surface-2:#F1F4FA;
  --border:rgba(15,23,42,.08); --border-strong:rgba(15,23,42,.14);
  --text:#0F172A; --text-muted:#64748B; --text-faint:#94A3B8;
  --shadow-sm: 0 1px 2px rgba(15,23,42,.05), 0 1px 1px rgba(15,23,42,.04);
  --shadow-md: 0 12px 28px -10px rgba(15,23,42,.16);
}
*{box-sizing:border-box;}
html{
  -webkit-text-size-adjust:100%;
}
body{
  margin:0;
  background: var(--bg);
  color: var(--text);
  font-family: var(--font-ui);
  -webkit-font-smoothing:antialiased;
}
h1,h2,h3,h4{
  font-family: var(--font-display);
  margin: 0;
  letter-spacing: -.01em;
}
p{ margin:0;}
a{
  color:inherit;
  text-decoration:none;
}
img{ max-width: 100%;}
table{ max-width: 100%;}

.app-shell{
  display:flex;
  min-height: 100vh;
}
.menu-toggle{
  display:none;
  position: fixed;
  top:14px;
  left:14px;
  z-index:110;
  width:42px;
  height:42px;
  border-radius:var(--radius-sm);
  align-items:center;
  justify-content: center;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  flex-direction: column;
  gap:4px;
  background: var(--surface);
  border: 1px solid var(--border);
}
.menu-toggle span{
  display: block;
  width:18px;
  height:2px;
  background: var(--text);
  border-radius: 2px;
  transition: transform .2s var(--ease), opacity .2s var(--ease);
}
.menu-toggle.open span:nth-child(1){ transform: translateY(6px) rotate(45deg);}
.menu-toggle.open span:nth-child(2) {opacity:0;}
.menu-toggle.open span:nth-child(3) { transform:translateY(-6px) rotate(-45deg);}

.sidebar-overlay{
  display:none;
  position:fixed;
  inset:0;
  background: rgba(0,0,0,.55);
  z-index:95;
  opacity:0;
  transition:opacity .25s var(--ease);
}
.sidebar-overlay.visible { display:block; opacity:1;}

.sidebar{
  width:248px;
  background: var(--bg-elevated);
  border-right:1px solid var(--border);
  display:flex;
  flex-direction: column;
  padding:22px 16px; 
  flex-shrink: 0;
  /* Sidebar chrome stays dark navy even though the rest of the app is now
     light — re-scoping these variable names locally (rather than adding
     new ones) means every existing rule below that reads var(--text),
     var(--text-muted), var(--surface), var(--border) etc. automatically
     renders correctly on dark, with no per-selector changes needed. */
  --surface: rgba(255,255,255,.05);
  --surface-2: rgba(255,255,255,.09);
  --border: rgba(255,255,255,.10);
  --border-strong: rgba(255,255,255,.20);
  --text: #F3F5FC;
  --text-muted: #97A3C4;
  --text-faint: #6B77A0;
}
.sidebar-brand{
  display:flex;
  align-items: center;
  gap:10px;
  padding:0 8px 24px;
}
.sidebar-brand .mark{
  width:34px;
  height:34px;
  border-radius: 9px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  flex-shrink:0;
  object-fit: cover;
  display:block;
}
.sidebar-brand b{
  font-size: 15px;
  font-family:var(--font-display);
  /* Fix (Sep 2026) — same bug as .who-row .who b: no color declared, so
     this inherited the page's dark-navy computed color instead of the
     sidebar's re-scoped --text. The "Admin/Officer workspace" span right
     below it was fine because it explicitly references var(--text-faint). */
  color: var(--text);
}
.sidebar-brand span{
  display:block;
  font-size:11px;
  color:var(--text-faint);
}
.nav-group{
  margin-bottom: 18px;
}
.nav-group .nav-label{
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform:uppercase;
  color:var(--text-faint);
  padding: 0 12px;
  margin-bottom:6px;
}
.nav-link{
  display:flex;
  align-items: center;
  gap: 10px;
  padding:9px 12px;
  border-radius: var(--radius-sm);
  font-size: 13.5px;
  font-weight: 500;
  color: var(--text-muted);
  margin-bottom:2px;
  cursor:pointer;
}
.nav-link .ic{
  width:22px;
  height:22px;
  border-radius:6px;
  background:var(--surface-2);
  flex-shrink:0;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:12.5px;
  line-height:1;
}
.nav-link .ic.orange{ background:var(--primary-soft); }
.nav-link .ic.purple{ background:var(--purple-soft); }
.nav-link .ic.green{ background:var(--emerald-soft); }
.nav-link .ic.amber{ background:var(--amber-soft); }
.nav-link .ic.rose{ background:var(--rose-soft); }
.nav-link:hover{
  background:var(--surface-2);
  color: var(--text);
}
.nav-link.active{
  background:var(--primary-soft);
  color: var(--primary);
  font-weight:600;
}
.nav-link.active .ic{
  background: var(--primary);
}
.nav-link .badge-count{
  margin-left: auto;
  font-size: 10px;
  background: var(--rose);
  color:#fff;
  padding: 1px 6px;
  border-radius: 99px;
  font-weight:700;
}
.sidebar-foot{ margin-top:auto;}
.who-row{
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 12px;
  border-radius: var(--radius-md);
  background: var(--surface-2);
  margin-bottom: 8px;
}
.who-row-top{
margin:4px 0 18px;
}
.avatar{
  width: 34px;
  height:34px;
  border-radius:50%;
  background: linear-gradient(135deg, var(--emerald), var(--primary));
  flex-shrink:0;
}
.who-row .who b{
  display:block;
  font-size:13px;
  /* Fix (Sep 2026) — this had no color at all, so it inherited the
     already-computed dark-navy color from the page body instead of the
     sidebar's re-scoped --text (#F3F5FC). CSS inheritance passes down a
     resolved value, not the var() expression -- only a rule that
     explicitly references var(--text) itself re-resolves it against the
     sidebar's local override, which is why the ID line below (which
     already did this) was visible while the name wasn't. */
  color: var(--text);
}
.who-row .who span{
  font-size: 11px;
  color:var(--text-muted);
}
.avatar-upload{
  position:relative;
  width:34px;
  height:34px;
  flex-shrink:0;
  border-radius:50%;
  overflow:hidden;
}
.avatar-upload .avatar,
.avatar-upload img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
  border-radius:50%;
}
.avatar-upload input[type="file"]{
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  opacity:0;
  cursor:pointer;
}
.avatar-upload .avatar-edit-badge{
  position:absolute;
  inset:0;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(0,0,0,.5);
  color:#fff;
  font-size:9px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.04em;
  opacity:0;
  transition:opacity .15s var(--ease);
  pointer-events:none;
}
.avatar-upload:hover .avatar-edit-badge{ opacity:1;}
.avatar-upload-note{
  font-size:10px;
  color:var(--text-faint);
  margin-top:6px;
}
.avatar-remove-link{
  display:block;
  background:none;
  border:none;
  padding:0;
  margin-top:3px;
  font-size:10.5px;
  font-family:var(--font-ui);
  font-weight:500;
  color:var(--text-faint);
  cursor:pointer;
  text-decoration:underline;
  text-decoration-style:dotted;
  text-underline-offset:2px;
}
.avatar-remove-link:hover{ color:var(--rose-text); }
.logout-btn{
  width:100%;
  height:38px;
  border-radius: var(--radius-sm);
  border:1px solid var(--border);
  background: var(--surface);
  color:var(--text-muted);
  font-family:var(--font-ui);
  font-weight:600;
  font-size: 12.5px;
  cursor:pointer;
}
.logout-btn:hover{
  color:var(--rose-text);
  border-color:var(--rose);
}
.btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  height:42px;
  padding:0 16px;
  border-radius:var(--radius-md);
  border:none;
  cursor:pointer;
  font-family:var(--font-ui);
  font-weight:600;
  font-size:13.5px;
  transition:transform .15s var(--ease), box-shadow .2s;
  white-space:nowrap;
}
.btn:active{ transform:scale(.98);}
.btn-primary{
  background: linear-gradient(135deg, var(--primary), var(--violet));
  color:#fff;
  box-shadow: 0 6px 18px rgba(91,91,246,.3);
}
.btn-ghost{
  background: var(--surface-2);
  color: var(--text);
  border:1px solid var(--border);
}

.field-input{
  height:42px;
  border-radius: var(--radius-sm);
  border:1px solid var(--border-strong);
  background: var(--surface-2);
  color: var(--text);
  padding: 0 12px;
  font-size:13px;
  font-family: var(--font-ui);
  outline: none;
  transition: border-color .2s;
  min-width:0;
}
.field-input:focus{ border-color: var(--primary);}

.mini-btn{
  padding:7px 12px;
  font-size:11px;
  font-weight:700;
  border-radius:7px;
  cursor:pointer;
  border:none;
  font-family: var(--font-ui);
  white-space:nowrap;
}
.mini-btn.approve{ background: var(--emerald-soft); color: var(--emerald-text);}
.mini-btn.reject{ background: var(--rose-soft); color: var(--rose-text);}

.queue-actions{
  display:flex;
  gap:6px;
  margin-left:auto;
  flex-wrap:wrap;
}

.link-sm{
  font-size:12.5px;
  color: var(--primary);
  font-weight:600;
  cursor:pointer;
}
.link-sm.disabled{
  cursor:default;
  color:var(--text-faint);
}
.text-faint{
  color:var(--text-faint);
}

.filter-form{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-bottom:16px;
}
.filter-form .field-input{ flex:1; min-width:160px;}

.data-table{
  width:100%;
  border-collapse:collapse;
  font-size:13px;
}
.data-table th{
  text-align:left;
  padding:10px 8px;
  border-bottom:1px solid var(--border);
  color:var(--text-muted);
  font-weight:600;
  font-size:12px;
  white-space:nowrap;
}
.data-table td{
  padding:12px 8px;
  border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.data-table tbody tr{
  transition: background .15s var(--ease);
}
.data-table tbody tr:hover{
  background: var(--surface-2);
}
.data-table tbody tr:last-child td{
  border-bottom:none;
}

.user-cell{
  display:flex;
  align-items:center;
  gap:10px;
}
.user-cell .sub{
  font-size:11px;
  color:var(--text-muted);
}

.avatar-sm{
  width:28px;
  height:28px;
  border-radius:50%;
  background: linear-gradient(135deg, var(--emerald), var(--primary));
  flex-shrink:0;
  display:inline-block;
}

.badge{
  display:inline-flex;
  align-items:center;
  font-size:11px;
  font-weight:700;
  padding:3px 9px;
  border-radius:99px;
  white-space:nowrap;
}
.badge.approved{ background: var(--emerald-soft); color: var(--emerald-text);}
.badge.pending{ background: var(--amber-soft); color: var(--amber-text);}
.badge.paid{ background: var(--emerald-soft); color: var(--emerald-text);}
.badge.waived{ background: var(--primary-soft); color: var(--primary);}
.badge.unpaid{ background: var(--rose-soft); color: var(--rose-text);}
.badge.flagged{ background: var(--rose-soft); color: var(--rose-text);}
.badge.rejected{ background: var(--rose-soft); color: var(--rose-text);}

.main{
  flex:1;
  padding:28px 32px;
  overflow-x: hidden;
  min-width:0;
}
.header-bar{
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:12px;
  margin-bottom:20px;
}
.header-search{
  position:relative;
  flex:1;
  max-width:320px;
}
.header-search input{
  width:100%;
  height:40px;
  border-radius:10px;
  border:1px solid var(--border);
  background:var(--surface-2);
  color:var(--text);
  padding:0 14px 0 38px;
  font-size:13px;
}
.header-search input::placeholder{ color:var(--text-faint); }
.header-search input:focus{ outline:none; border-color:var(--border-strong); }
.header-search .icon{
  position:absolute;
  left:12px;
  top:50%;
  transform:translateY(-50%);
  color:var(--text-faint);
  font-size:14px;
  pointer-events:none;
}
.header-bell-wrap{
  position:relative;
  flex-shrink:0;
}
.header-bell{
  position:relative;
  width:40px;
  height:40px;
  border-radius:10px;
  border:1px solid var(--border);
  background:var(--surface-2);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
  color:var(--text-muted);
  flex-shrink:0;
  text-decoration:none;
  cursor:pointer;
  font-family:inherit;
}
.notif-dropdown{
  display:none;
  position:absolute;
  top:calc(100% + 8px);
  right:0;
  width:300px;
  max-height:360px;
  overflow-y:auto;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius-md);
  box-shadow:var(--shadow-lg);
  z-index:50;
}
.notif-dropdown.open{ display:block; }
.notif-dropdown-head{
  padding:12px 14px;
  font-size:12px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.04em;
  color:var(--text-faint);
  border-bottom:1px solid var(--border);
}
.notif-item{
  display:block;
  padding:10px 14px;
  border-bottom:1px solid var(--border);
  text-decoration:none;
}
.notif-item:last-child{ border-bottom:none; }
.notif-item:hover{ background:var(--surface-2); }
.notif-item b{
  display:block;
  font-size:13px;
  color:var(--text);
  margin-bottom:2px;
}
.notif-item span{
  font-size:11.5px;
  color:var(--text-faint);
}
.notif-empty{
  padding:16px 14px;
  font-size:12.5px;
  color:var(--text-faint);
  text-align:center;
}
.header-bell .badge{
  position:absolute;
  top:-6px;
  right:-6px;
  min-width:18px;
  height:18px;
  padding:0 4px;
  border-radius:99px;
  background:var(--primary);
  color:#fff;
  font-size:10.5px;
  font-weight:700;
  display:flex;
  align-items:center;
  justify-content:center;
}
.header-avatar{
  width:40px;
  height:40px;
  border-radius:50%;
  overflow:hidden;
  border:1px solid var(--border);
  flex-shrink:0;
  display:flex;
  align-items:center;
  justify-content:center;
  background:var(--surface-2);
  color:var(--text-muted);
  font-weight:700;
  font-size:14px;
  text-decoration:none;
}
.header-avatar img{ width:100%; height:100%; object-fit:cover; }
@media (max-width: 640px){
  .header-search{ display:none; }
}
.topbar{
  display:flex;
  align-items:center;
  justify-content: space-between;
  gap:12px;
  flex-wrap:wrap;
  margin-bottom:28px;
}
.topbar h1{ font-size:22px;}
.topbar .meta{
  font-size:13px;
  color:var(--text-muted);
  margin-top:2px;
}
.banner{
  display:flex;
  align-items: center;
  gap:10px;
  padding:12px 16px;
  border-radius:var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  margin-bottom:20px;
}
.banner-dismiss{
  margin-left:auto;
  background:none;
  border:none;
  color:inherit;
  opacity:.65;
  cursor:pointer;
  font-size:15px;
  line-height:1;
  padding:4px;
  flex-shrink:0;
}
.banner-dismiss:hover{ opacity:1; }
.banner.warn{
  background: var(--amber-soft);
  color: var(--amber-text);
  border:1px solid rgba(245,166,35, .3);
}
.banner.danger{
  background:var(--rose-soft);
  color:var(--rose-text);
  border:1px solid rgba(245,73, 122, .3);
}
.banner.success{
  background: var(--emerald-soft);
  color: var(--emerald-text);
  border:1px solid rgba(31,201,141, .3);
}
.banner a{
  text-decoration:underline;
}

.stat-grid{
  display:grid;
  grid-template-columns: repeat(4,1fr);
  gap:16px;
  margin-bottom:24px;
}
.stat-card{
  background: var(--surface);
  border:1px solid var(--border);
  border-radius: var(--radius-lg);
  padding:18px 20px;
  box-shadow: var(--shadow-sm);
  min-width:0;
  transition: transform .18s var(--ease), box-shadow .18s var(--ease);
}
.stat-card:hover{
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}
.stat-icon{
  width:36px;
  height:36px;
  border-radius: 10px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
}
.stat-icon.violet,
.stat-icon.orange{
  background:var(--primary-soft);
  color: var(--primary);
}
.stat-icon.purple{
  background:var(--purple-soft);
  color: var(--purple);
}
.stat-icon.green{
  background: var(--emerald-soft);
  color:var(--emerald-text);
}
.stat-icon.amber{
  background:var(--amber-soft);
  color:var(--amber-text);
}
.stat-icon.rose{
  background:var(--rose-soft);
  color:var(--rose-text);
}
.stat-card .value{
  font-size: 26px;
  font-weight:700;
  margin:14px 0 2px;
  font-family:var(--font-display);
  word-break: break-word;
}
.stat-card .label{
  font-size:12.5px;
  color:var(--text-muted);
}

.dash-row{
  display:grid;
  grid-template-columns: 1.6fr 1fr;
  gap:16px;
  margin-bottom:20px;
  align-items:stretch;
}
.dash-tag{
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:11px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.06em;
  color:var(--primary);
  margin-bottom:10px;
}
.dash-tag .view-all{
  margin-left:auto;
  text-transform:none;
  letter-spacing:0;
  font-weight:600;
  color:var(--text-muted);
  cursor:pointer;
}
.feature-card{
  position:relative;
  border-radius: var(--radius-lg);
  overflow:hidden;
  border:1px solid var(--border);
  min-height:280px;
  display:flex;
  align-items:flex-end;
  box-shadow: var(--shadow-sm);
}
.feature-card .feature-bg{
  position:absolute;
  inset:0;
  background-size:cover;
  background-position:center;
  background-color: var(--surface-2);
}
.feature-card .feature-bg::after{
  content:"";
  position:absolute;
  inset:0;
  /* Fix (Sep 2026) — scrim strengthened and extended further up the card.
     The old stops (.92 / .55 / .15) left the title, which sits near the
     TOP of the bottom-aligned content block, under only ~15-55% darkening
     — not enough against a bright sky/mountain photo, which is exactly
     what broke in the reported screenshot. */
  background:linear-gradient(0deg, rgba(10,11,16,.95) 15%, rgba(10,11,16,.78) 60%, rgba(10,11,16,.35) 100%);
}
.feature-card .feature-content{
  position:relative;
  z-index:1;
  padding:20px 22px;
  width:100%;
}
.feature-card h2{
  font-size:22px;
  margin-bottom:8px;
  /* Fix (Sep 2026) — previously unset, so this inherited --text (dark
     navy, calibrated for the light page background) and nearly vanished
     against the photo. text-shadow is a safety net for whatever the
     scrim alone doesn't cover on an unusually bright uploaded photo. */
  color:#FFFFFF;
  text-shadow: 0 1px 3px rgba(0,0,0,.5);
}
.feature-card p.desc{
  font-size:13px;
  /* Fix (Sep 2026) — was var(--text-muted), a mid-gray meant for the
     light page background, not a dark photo scrim. */
  color:rgba(255,255,255,.82);
  margin-bottom:12px;
  max-width:480px;
}
.feature-meta{
  display:flex;
  flex-wrap:wrap;
  gap:14px;
  font-size:12.5px;
  /* Fix (Sep 2026) — same reasoning as .feature-card h2/p.desc above.
     This happened to be borderline-legible only because it sits in the
     darkest part of the scrim -- relying on that for every future event
     photo isn't a real fix. */
  color:rgba(255,255,255,.75);
  margin-bottom:16px;
}
.feature-meta span{
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.announce-card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius: var(--radius-lg);
  padding:20px;
  box-shadow: var(--shadow-sm);
  display:flex;
  flex-direction:column;
  min-width:0;
}
.announce-card h4{
  font-size:15px;
  margin-bottom:8px;
}
.announce-card .body-text{
  font-size:13px;
  color:var(--text-muted);
  margin-bottom:14px;
  flex:1;
  overflow:hidden;
  display:-webkit-box;
  -webkit-line-clamp:4;
  -webkit-box-orient:vertical;
}
.announce-card .when{
  font-size:11.5px;
  color:var(--text-faint);
  margin-bottom:10px;
}
.qa-grid{
  display:grid;
  grid-template-columns: repeat(4,1fr);
  gap:14px;
}
.qa-card{
  display:flex;
  align-items:flex-start;
  gap:12px;
  padding:16px;
  border-radius: var(--radius-md);
  background: var(--surface-2);
  border:1px solid var(--border);
  cursor:pointer;
  transition: border-color .15s var(--ease), transform .15s var(--ease);
  min-width:0;
}
.qa-card:hover{
  border-color:var(--border-strong);
  transform:translateY(-1px);
}
.qa-card .qa-icon{
  width:38px;
  height:38px;
  border-radius:10px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:17px;
  flex-shrink:0;
}
.qa-card .qa-text b{
  display:block;
  font-size:13.5px;
  margin-bottom:2px;
}
.qa-card .qa-text span{
  font-size:11.5px;
  color:var(--text-muted);
}
.empty-feature{
  display:flex;
  align-items:center;
  justify-content:center;
  min-height:280px;
  border-radius:var(--radius-lg);
  border:1px dashed var(--border-strong);
  color:var(--text-faint);
  font-size:13px;
  text-align:center;
  padding:20px;
}
@media (max-width: 980px){
  .dash-row{ grid-template-columns: 1fr;}
  .qa-grid{ grid-template-columns: repeat(2,1fr);}
}
@media (max-width: 560px){
  .qa-grid{ grid-template-columns: 1fr 1fr;}
  .feature-card{ min-height:220px;}
}

.panel{
  background: var(--surface);
  border:1px solid var(--border);
  border-radius: var(--radius-lg);
  padding:22px;
  box-shadow: var(--shadow-sm);
  margin-bottom: 20px;
  min-width:0;
}
.panel-head{
  display:flex;
  align-items: center;
  justify-content: space-between;
  gap:10px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.panel-head h3{ font-size:15px;}
.panel-head .note{
  font-size:12.5px;
  color:var(--text-muted);
}
.queue-item{
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px;
  border-radius: var(--radius-md);
  background: var(--surface-2);
  margin-bottom:8px;
  flex-wrap: wrap;
}
.queue-item .who b{
  font-size:12.5px;
  display:block;
}
.queue-item .who span{
  font-size:11px;
  color:var(--text-muted);
}
.empty-note{
  font-size:12.5px;
  color:var(--text-muted);
  padding:8px 0;
}
/* Table cells need more breathing room than the base .empty-note default
   -- was previously set inline (style="padding:16px 8px;") separately in
   activity-logs, users, reports, and user-activity-log, with no single
   source of truth for the value. */
td.empty-note{
  padding:16px 8px;
}
/* Wrapper for $paginator->links() below a table -- was previously a bare
   style="margin-top:16px;" div repeated in activity-logs, users, and
   user-activity-log. */
.pagination-wrap{
  margin-top:16px;
}

.table-responsive{
  width:100%;
  overflow-x:auto;
  -webkit-overflow-scrolling:touch;
}
.table-responsive table{
  min-width:560px;
}

.soms-pagination{
  display:flex;
  align-items:center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap:10px;
}
.pagination-list{
  display:flex;
  align-items:center;
  gap:4px;
  list-style:none;
  margin:0;
  padding:0;
  flex-wrap: wrap;
}
.pagination-list .page-item{display:flex;}
.pagination-list .page-link{
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width:32px;
  height:32px;
  padding:0 10px;
  border-radius: var(--radius-sm);
  border:1px solid var(--border);
  background: var(--surface-2);
  color:var(--text-muted);
  font-size:12.5px;
  font-weight:600;
  cursor:pointer;
  transition:background .15s var(--ease), color .15s var(--ease), border-color .15s var(--ease);
  white-space:nowrap;
}
.pagination-list .page-item.active .page-link{
  background: var(--primary);
  color:#fff;
  border-color:var(--primary);
}
.pagination-list .page-item.disabled .page-link{
  opacity: .4;
  cursor:default;
  pointer-events:none;
}
.pagination-list .page-link.dots{
  background:transparent;
  border:none;
  cursor:default;
}
.pagination-summary{
  font-size:12px;
  color:var(--text-faint);
  margin:0;
}

@media (max-width: 980px){
  .menu-toggle{ display: flex;}
  .sidebar-overlay{ display:none;}

  .app-shell{ flex-direction: column;}
  .sidebar{
    position:fixed;
    top:0;
    left:0;
    height: 100vh;
    width:260px;
    max-width: 80vw;
    transform: translateX(-100%);
    transition: transform .3s var(--ease);
    z-index:100;
    overflow-y:auto;
    border-right: 1px solid var(--border);
    border-bottom: none;
  }
  .sidebar.open { transform: translateX(0);}
  .sidebar-brand {padding: 44px 8px 24px 8px;}
  .main {padding: 76px 18px 24px 18px;}
  .stat-grid{ grid-template-columns: repeat(2,1fr);}
}

@media (max-width: 560px){
  .stat-grid{grid-template-columns: 1fr;}
  .main{padding: 72px 14px 20px 14px;}
  .topbar h1{font-size: 19px;}
  .panel{padding:16px;}
  .queue-actions{width:100%;}
}
</style>
{{ $styles ?? '' }}

@include('partials.favicon')
</head>
<body>
<div class="menu-toggle" id="menuToggle" role="button" aria-label="Toggle menu" aria-expanded="false">
  <span></span>
  <span></span>
  <span></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-shell">
  <div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="{{ asset('images/logo.png') }}" alt="SOMS" class="mark">
      <div><b>SOMS</b><span>{{ ucfirst(auth()->user()->role ?? 'guest') }} workspace</span></div>
    </div>
    <div class="who-row who-row-top">
        <form method="POST" action="{{ route('avatar.' . (auth()->user()->avatar_path ? 'update' : 'store')) }}" enctype="multipart/form-data" class="avatar-upload" id="avatarUploadForm" title="Click to change photo — JPG, PNG or WEBP, max 2MB">
          @csrf
          @if(auth()->user()->avatar_path)
            @method('PUT')
            <img src="{{ Storage::disk('r2')->url(auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}">
          @else
            <div class="avatar"></div>
          @endif
          <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/webp">
          <span class="avatar-edit-badge">Edit</span>
        </form>
        <div class="who">
          <b>{{ auth()->user()->name ?? '' }}</b>
          <span>{{ auth()->user()->student_id ?? '' }}</span>
          @error('avatar')
          <div style="color:var(--rose-text); font-size:10.5px; margin-top:4px;">{{ $message }}</div>
          @enderror
          @if(auth()->user()->avatar_path)
          <form method="POST" action="{{ route('avatar.destroy') }}" onsubmit="return confirm('Remove your profile photo?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="avatar-remove-link">Remove photo</button>
          </form>
          @endif
        </div>
      </div>
    {{ $nav ?? '' }}


    <div class="sidebar-foot">
      
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <div class="main">
    @php
      // Best-effort notification feed for the bell dropdown. What counts
      // as a "notification" depends on role — officers/students see
      // recent announcements (same window as the badge count before);
      // admins see pending user approvals, since that's the thing an
      // admin actually needs to act on (see partials/admin-nav.blade.php,
      // which already badges the Users link with this same figure).
      // Wrapped defensively since this shared layout renders for every
      // role, including guests mid-redirect.
      $headerNotifCount = 0;
      $headerNotifItems = collect();
      $headerNotifKind = null;
      if (auth()->check()) {
        try {
          if (in_array(auth()->user()->role, ['officer', 'student'], true)) {
            $headerOrgId = auth()->user()->activeOfficerPosition?->organization_id
                ?? \App\Models\Organization::query()->value('id');
            $headerNotifKind = 'announcement';
            $headerNotifItems = \App\Models\Announcement::where('organization_id', $headerOrgId)
                ->where('is_published', true)
                ->where('created_at', '>=', now()->subDays(3))
                ->latest()
                ->limit(5)
                ->get();
            $headerNotifCount = $headerNotifItems->count();
          } elseif (auth()->user()->role === 'admin') {
            $headerNotifKind = 'approval';
            $headerNotifItems = \App\Models\User::where('is_approved', false)
                ->latest()
                ->limit(5)
                ->get();
            $headerNotifCount = $headerNotifItems->count();
          }
        } catch (\Throwable $e) {
          $headerNotifCount = 0;
          $headerNotifItems = collect();
        }
      }
    @endphp
    <div class="header-bar">
      <form class="header-search" action="{{ route('search') }}" method="GET">
        <span class="icon">🔍</span>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search anything...">
      </form>
      <div class="header-bell-wrap">
        {{-- Roadmap Phase 3.2 -- title="" alone isn't reliably announced by
             screen readers; aria-label carries the count, and
             aria-haspopup/aria-expanded describe this as the disclosure
             toggle it actually is (JS below should keep aria-expanded in
             sync when the dropdown opens/closes). --}}
        <button type="button" class="header-bell" id="headerBellBtn"
                aria-label="Notifications{{ $headerNotifCount > 0 ? ' (' . $headerNotifCount . ' unread)' : '' }}"
                aria-haspopup="true" aria-expanded="false">
          🔔
          @if($headerNotifCount > 0)
          <span class="badge" aria-hidden="true">{{ $headerNotifCount > 9 ? '9+' : $headerNotifCount }}</span>
          @endif
        </button>
        <div class="notif-dropdown" id="headerNotifDropdown">
          <div class="notif-dropdown-head">{{ $headerNotifKind === 'approval' ? 'Pending approvals' : 'Recent announcements' }}</div>
          @forelse($headerNotifItems as $item)
            @if($headerNotifKind === 'approval')
            <a href="{{ route('admin.users.index') }}" class="notif-item">
              <b>{{ $item->name }}</b>
              <span>Awaiting approval &middot; {{ $item->student_id }}</span>
            </a>
            @else
            <a href="{{ auth()->user()->role === 'student' ? route('student.announcements.show', $item) : route('officer.announcements.index') }}" class="notif-item">
              <b>{{ $item->title }}</b>
              <span>{{ $item->created_at->diffForHumans() }}</span>
            </a>
            @endif
          @empty
          <div class="notif-empty">Nothing new right now.</div>
          @endforelse
        </div>
      </div>
      @if(auth()->check())
      <a href="{{ route('settings.profile.edit') }}" class="header-avatar" title="{{ auth()->user()->name }}">
        @if(auth()->user()->avatar_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('r2')->url(auth()->user()->avatar_path) }}" alt="">
        @else
        {{ strtoupper(substr(auth()->user()->name ?? '?', 0, 1)) }}
        @endif
      </a>
      @endif
    </div>
    @if(session('status'))
    <div class="banner" style="background:var(--emerald-soft); color:var(--emerald-text); margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    {{ $content ?? '' }}
  </div>
</div>

<script>
  (function() {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!toggle || !sidebar || !overlay) return;

    function openMenu(){
      sidebar.classList.add('open');
      overlay.classList.add('visible');
      toggle.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu(){
      sidebar.classList.remove('open');
      overlay.classList.remove('visible');
      toggle.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function(){
      sidebar.classList.contains('open') ? closeMenu() : openMenu();
    });

    overlay.addEventListener('click', closeMenu);

    sidebar.querySelectorAll('.nav-link').forEach(function(link){
      link.addEventListener('click', closeMenu);
    });

    window.addEventListener('resize', function(){
      if (window.innerWidth > 980) closeMenu();
    });
  })();

  (function() {
    const avatarInput = document.getElementById('avatarInput');
    const avatarForm = document.getElementById('avatarUploadForm');
    if (!avatarInput || !avatarForm) return;

    avatarInput.addEventListener('change', function () {
      if (avatarInput.files && avatarInput.files.length > 0) {
        avatarForm.submit();
      }
    });
  })();

  (function() {
    const bellBtn = document.getElementById('headerBellBtn');
    const dropdown = document.getElementById('headerNotifDropdown');
    if (!bellBtn || !dropdown) return;

    bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.toggle('open');
      bellBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target) && e.target !== bellBtn) {
        dropdown.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
      }
    });

    // Roadmap Phase 3.2 -- Escape closes the dropdown and returns focus
    // to the trigger, matching expected disclosure-widget keyboard behavior.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && dropdown.classList.contains('open')) {
        dropdown.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
        bellBtn.focus();
      }
    });
  })();

  (function() {
    // Dismissible banners: any element with [data-dismiss-key] gets an
    // auto-inserted ✕ button. Dismissal is stored in sessionStorage, so
    // it's gone for the rest of this browser tab session but comes back
    // next time you sign in fresh — matches how these are meant to
    // behave: a nudge you can clear for now, not a permanent opt-out of
    // a real underlying issue (see admin/dashboard.blade.php).
    document.querySelectorAll('[data-dismiss-key]').forEach(function (banner) {
      const key = 'dismissed-banner:' + banner.dataset.dismissKey;
      if (sessionStorage.getItem(key)) {
        banner.style.display = 'none';
        return;
      }
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'banner-dismiss';
      btn.setAttribute('aria-label', 'Dismiss');
      btn.textContent = '✕';
      btn.addEventListener('click', function () {
        sessionStorage.setItem(key, '1');
        banner.style.display = 'none';
      });
      banner.appendChild(btn);
    });
  })();
</script>
</body>
</html>
@props(['title' => 'Dashboard'])
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }} — SOMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --radius-sm:8px; --radius-md:12px; --radius-lg:18px;
  --ease:cubic-bezier(.4,0,.2,1);
  --font-ui:'Inter',system-ui,sans-serif;
  --font-display:'Inter Tight','Inter',system-ui,sans-serif;
  --primary:#5B5BF6; --violet:#9B5CF6;
  --primary-soft:rgba(91,91,246,.14);
  --emerald:#1FC98D; --emerald-soft:rgba(31,201,141,.14);
  --amber:#F5A623; --amber-soft:rgba(245,166,35,.14);
  --rose:#F5497A; --rose-soft:rgba(245,73,122,.14);
  --bg:#0A0B10; --bg-elevated:#11131C; --surface:#15171F; --surface-2:#1B1E29;
  --border:rgba(255,255,255,.08); --border-strong:rgba(255,255,255,.14);
  --text:#EDEEF4; --text-muted:#8C90A3; --text-faint:#5C6075;
  --shadow-sm: 0 2px 8px rgba(0,0,0,.3);
  --shadow-md: 0 8px 24px rgba(0,0,0,.4);
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
}
.sidebar-brand{
  display:flex;
  align-items: center;
  gap:10px;
  padding:0 8px 24px;
}
.sidebar-brand .mark{
  width:32px;
  height:32px;
  border-radius: 9px;
  background: linear-gradient(135deg, var(--primary), var(--violet));
  flex-shrink:0;
}
.sidebar-brand b{
  font-size: 15px;
  font-family:var(--font-display);
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
  width:18px;
  height:18px;
  border-radius:5px;
  background:var(--surface-2);
  flex-shrink:0;
}
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
.avatar-remove-link:hover{ color:var(--rose); }
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
  color:var(--rose);
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
.mini-btn.approve{ background: var(--emerald-soft); color: var(--emerald);}
.mini-btn.reject{ background: var(--rose-soft); color: var(--rose);}

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
.badge.approved{ background: var(--emerald-soft); color: var(--emerald);}
.badge.pending{ background: var(--amber-soft); color: var(--amber);}
.badge.paid{ background: var(--emerald-soft); color: var(--emerald);}
.badge.waived{ background: var(--primary-soft); color: var(--primary);}
.badge.unpaid{ background: var(--rose-soft); color: var(--rose);}
.badge.flagged{ background: var(--rose-soft); color: var(--rose);}
.badge.rejected{ background: var(--rose-soft); color: var(--rose);}

.main{
  flex:1;
  padding:28px 32px;
  overflow-x: hidden;
  min-width:0;
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
.banner.warn{
  background: var(--amber-soft);
  color: var(--amber);
  border:1px solid rgba(245,166,35, .3);
}
.banner.danger{
  background:var(--rose-soft);
  color:var(--rose);
  border:1px solid rgba(245,73, 122, .3);
}
.banner.success{
  background: var(--emerald-soft);
  color: var(--emerald);
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
.stat-icon.violet{
  background:var(--primary-soft);
  color: var(--primary);
}
.stat-icon.green{
  background: var(--emerald-soft);
  color:var(--emerald);
}
.stat-icon.amber{
  background:var(--amber-soft);
  color:var(--amber);
}
.stat-icon.rose{
  background:var(--rose-soft);
  color:var(--rose);
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
      <div class="mark"></div>
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
          <div style="color:var(--rose); font-size:10.5px; margin-top:4px;">{{ $message }}</div>
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
    @if(session('status'))
    <div class="banner" style="background:var(--emerald-soft); color:var(--emerald); margin-bottom:16px;">{{ session('status') }}</div>
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
</script>
</body>
</html>
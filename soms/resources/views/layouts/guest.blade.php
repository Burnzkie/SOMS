<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'SOMS') — SGO Edition</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --radius-md:12px; --radius-lg:18px; --radius-xl:24px;
  --ease:cubic-bezier(.4,0,.2,1);
  --font-ui:'Inter',system-ui,sans-serif;
  --font-display:'Inter Tight','Inter',system-ui,sans-serif;
  --primary:#5B5BF6; --violet:#9B5CF6; --emerald:#1FC98D; --rose:#F5497A;
  --bg:#0A0B10; --text:#EDEEF4; --text-muted:#8C90A3; --text-faint:#5C6075;
  --border-strong:rgba(255,255,255,.14);
  --shadow-lg: 0 24px 60px rgba(0,0,0,.55);
  --glass-bg: rgba(20,22,32,.55);
  --mesh-1:#5B5BF6; --mesh-2:#9B5CF6; --mesh-3:#1FC98D;
}
*{box-sizing:border-box;}
body{
  margin:0; min-height:100vh; background:var(--bg); color:var(--text);
  font-family:var(--font-ui); -webkit-font-smoothing:antialiased;
  display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden;
}
h1,h2,h3{font-family:var(--font-display); margin:0; letter-spacing:-.01em;}
p{margin:0;}
a{color:inherit; text-decoration:none;}
.mesh{
  position:absolute; inset:0; z-index:0;
  background:
    radial-gradient(circle at 18% 22%, var(--mesh-1) 0%, transparent 42%),
    radial-gradient(circle at 82% 18%, var(--mesh-2) 0%, transparent 40%),
    radial-gradient(circle at 50% 88%, var(--mesh-3) 0%, transparent 45%);
  filter:blur(70px); opacity:.55;
}
.auth-card{
  position:relative; z-index:2; width:100%; max-width:400px; padding:40px 36px; margin:40px 16px;
  background:var(--glass-bg); border:1px solid rgba(255,255,255,.18);
  border-radius:var(--radius-lg); backdrop-filter:blur(22px) saturate(140%);
  box-shadow:var(--shadow-lg);
}
.auth-logo{
  width:44px; height:44px; border-radius:12px; margin-bottom:22px;
  background:linear-gradient(135deg,var(--primary),var(--violet));
  display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; font-size:15px;
}
.auth-card h2{font-size:24px; margin-bottom:6px;}
.auth-card .sub{color:var(--text-muted); font-size:13px; margin-bottom:24px;}
.field{margin-bottom:16px;}
.field label{display:block; font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:6px;}
.field input, .field select{
  width:100%; height:46px; border-radius:var(--radius-md); border:1px solid var(--border-strong);
  background:rgba(255,255,255,.06); color:var(--text); padding:0 14px; font-size:14px; font-family:var(--font-ui);
  outline:none; transition:border-color .2s, background .2s;
}
.field input:focus, .field select:focus{border-color:var(--primary);}
.field input::placeholder{color:var(--text-faint);}
.field-error{color:var(--rose); font-size:12px; margin-top:6px;}
.field-grid{display:grid; grid-template-columns:1fr 1fr; gap:12px;}
.btn{
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  height:46px; border-radius:var(--radius-md); border:none; cursor:pointer; width:100%;
  font-family:var(--font-ui); font-weight:600; font-size:14px; transition:transform .15s var(--ease), box-shadow .2s;
}
.btn:active{transform:scale(.98);}
.btn-primary{
  background:linear-gradient(135deg,var(--primary),var(--violet)); color:#fff;
  box-shadow:0 10px 30px rgba(91,91,246,.35);
}
.auth-foot{margin-top:22px; text-align:center; font-size:12px; color:var(--text-muted);}
.auth-foot a{color:var(--primary); font-weight:600;}
.alert{padding:12px 14px; border-radius:var(--radius-md); font-size:12.5px; margin-bottom:18px;}
.alert-success{background:rgba(31,201,141,.14); color:var(--emerald); border:1px solid rgba(31,201,141,.3);}
.alert-error{background:rgba(245,73,122,.14); color:var(--rose); border:1px solid rgba(245,73,122,.3);}


@media (max-width: 420px){
  .field-grid{grid-template-columns:1fr;}
  .auth-card{padding:32px 24px; margin:20px 12px;}
}
</style>

@include('partials.favicon')
</head>
<body>
<div class="mesh"></div>
<div class="auth-card">
  <div class="auth-logo">S</div>
  @yield('content')
</div>
</body>
</html>
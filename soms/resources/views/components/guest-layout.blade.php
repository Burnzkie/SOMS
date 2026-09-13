@props(['title' => 'SOMS'])
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }} — SGO Edition</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --radius-md:12px; --radius-lg:18px; --radius-xl:24px;
  --ease:cubic-bezier(.4,0,.2,1);
  --font-ui:'Inter',system-ui,sans-serif;
  --font-display:'Inter Tight','Inter',system-ui,sans-serif;
  /* Login/register stays a dramatic dark glass card over a blurred color
     mesh — deliberately distinct from the light app interior (see
     components/layout.blade.php), retinted to the same blue/purple/
     emerald "lively" palette so the brand still reads as one system. */
  --primary:#2563EB; --violet:#4F7DF3; --emerald:#10B981; --rose:#F43F5E;
  --bg:#070B18; --text:#F3F5FC; --text-muted:#97A3C4; --text-faint:#6B77A0;
  --border-strong:rgba(255,255,255,.14);
  --shadow-lg: 0 24px 60px rgba(0,0,0,.55);
  --glass-bg: rgba(20,26,46,.55);
  --mesh-1:#2563EB; --mesh-2:#8B5CF6; --mesh-3:#10B981;
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
  width:64px; height:64px; border-radius:16px; margin:0 auto 22px auto;
  background:linear-gradient(135deg,var(--primary),var(--violet));
  display:flex; align-items:center; justify-content:center; padding:4px;
}
.auth-logo img{
  width:100%; height:100%; border-radius:12px; object-fit:cover;
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
  box-shadow:0 10px 30px rgba(37,99,235,.35);
}
.auth-foot{margin-top:22px; text-align:center; font-size:12px; color:var(--text-muted);}
.auth-foot a{color:var(--primary); font-weight:600;}
.alert{padding:12px 14px; border-radius:var(--radius-md); font-size:12.5px; margin-bottom:18px;}
.alert-success{background:rgba(16,185,129,.14); color:var(--emerald); border:1px solid rgba(16,185,129,.3);}
.alert-error{background:rgba(244,63,94,.14); color:var(--rose); border:1px solid rgba(244,63,94,.3);}


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
  <div class="auth-logo"><img src="{{ asset('images/logo.png') }}" alt="SOMS"></div>
  {{ $content ?? '' }}
</div>
</body>
</html>
<?php
require_once 'db.php';
requireAdmin();
$adminName = $_SESSION['admin_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — Admin Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--bg-card-hover:rgba(255,255,255,0.10);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--accent:#4a9fff;--accent-bg:rgba(74,159,255,0.13);--red:#e74c3c;--sidebar:230px;--r:12px;--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:var(--font-b);background:var(--bg-deep);color:var(--text);}
::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--border-hi);border-radius:4px;}
@keyframes fadeup{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
a{text-decoration:none;}
.app-shell{display:flex;height:100vh;overflow:hidden;}
.sidebar{width:var(--sidebar);min-width:var(--sidebar);height:100vh;background:rgba(0,0,0,0.25);border-right:1px solid var(--border);display:flex;flex-direction:column;}
.sb-logo{padding:28px 22px 20px;font-family:var(--font-d);font-size:26px;letter-spacing:-.5px;border-bottom:1px solid var(--border);}
.sb-logo span{color:var(--accent);}
.sb-nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:3px;}
.sb-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r-sm);font-size:14px;font-weight:500;color:var(--muted);transition:background .15s,color .15s;border:1px solid transparent;}
.sb-item:hover{background:var(--bg-card-hover);color:var(--text);}
.sb-item.active{background:var(--accent-bg);color:var(--accent);border-color:rgba(74,159,255,.2);}
.sb-item svg{width:17px;height:17px;flex-shrink:0;}
.sb-bottom{padding:14px 10px;}
.sb-user{padding:12px 14px;border-radius:var(--r-sm);background:var(--bg-card);border:1px solid var(--border);margin-bottom:8px;}
.sb-user-name{font-size:13px;font-weight:600;}
.sb-user-role{font-size:11px;color:var(--muted);margin-top:2px;}
.main{flex:1;height:100vh;overflow-y:auto;background:var(--bg);}
.page{padding:32px 36px;animation:fadeup .3s ease;}
.page-title{font-family:var(--font-d);font-size:30px;letter-spacing:-.5px;margin-bottom:6px;}
.page-sub{color:var(--muted);font-size:13px;margin-bottom:28px;}
.profile-card{display:flex;align-items:center;gap:20px;padding:24px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);margin-bottom:20px;}
.avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#e74c3c,#c0392b);display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-size:24px;color:#fff;flex-shrink:0;}
.profile-name{font-family:var(--font-d);font-size:22px;}
.profile-role{font-size:12px;color:var(--muted);margin-top:3px;}
.admin-badge{display:inline-flex;background:var(--accent-bg);color:var(--accent);border:1px solid rgba(74,159,255,.3);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-top:6px;}
.profile-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.pf-item{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px;}
.pf-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:4px;}
.pf-val{font-size:15px;font-weight:500;}
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-top:20px;}
</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sb-logo">in<span>S</span>ight</div>
    <nav class="sb-nav">
      <a href="admin.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Admin Panel
      </a>
      <a href="admin_profile.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>My Profile
      </a>
    </nav>
    <div class="sb-bottom">
      <div class="sb-user">
        <div class="sb-user-name"><?= htmlspecialchars($adminName) ?></div>
        <div class="sb-user-role">Administrator</div>
      </div>
      <a href="logout.php" class="sb-item" style="color:var(--red);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
      </a>
    </div>
  </aside>
  <main class="main">
    <div class="page">
      <div class="page-title">Admin Profile</div>
      <div class="page-sub">Your administrator account information</div>
      <div class="profile-card">
        <div class="avatar">AM</div>
        <div>
          <div class="profile-name"><?= htmlspecialchars($adminName) ?></div>
          <div class="profile-role">Administrator</div>
          <div class="admin-badge">Admin</div>
        </div>
      </div>
      <div class="profile-fields">
        <div class="pf-item"><div class="pf-label">Name</div><div class="pf-val"><?= htmlspecialchars($adminName) ?></div></div>
        <div class="pf-item"><div class="pf-label">Email</div><div class="pf-val">admin@insight.sa</div></div>
        <div class="pf-item"><div class="pf-label">Username</div><div class="pf-val" style="font-family:monospace;">admin</div></div>
        <div class="pf-item"><div class="pf-label">Role</div><div class="pf-val">Administrator</div></div>
      </div>
      <div class="card">
        <div style="font-size:13px;color:var(--muted);line-height:1.7;">As an administrator, you have full access to manage all registered users, block or unblock accounts, add new users, and delete accounts from the system.</div>
      </div>
    </div>
  </main>
</div>
</body>
</html>

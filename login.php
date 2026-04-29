<?php
// login.php  — replaces login.html
require_once 'db.php';
startSecureSession();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $result = login($username, $password, $role);
        if ($result['success']) {
            if ($role === 'admin') {
                setAdminSession($result['data']);
                header('Location: admin.php');
            } else {
                setUserSession($result['data']);
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--dim:rgba(232,240,254,0.30);--accent:#4a9fff;--accent-bg:rgba(74,159,255,0.13);--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:var(--font-b);background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
body::before{content:'';position:absolute;width:700px;height:700px;background:radial-gradient(circle,rgba(74,159,255,.12) 0%,transparent 65%);top:-200px;right:-150px;pointer-events:none;}
body::after{content:'';position:absolute;width:500px;height:500px;background:radial-gradient(circle,rgba(46,204,113,.07) 0%,transparent 65%);bottom:-150px;left:-100px;pointer-events:none;}
@keyframes fadeup{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
.login-wrap{position:relative;z-index:1;width:420px;animation:fadeup .5s ease;}
.auth-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);text-decoration:none;margin-bottom:20px;transition:color .2s;}
.auth-back:hover{color:var(--text);}
.login-logo{font-family:var(--font-d);font-size:42px;letter-spacing:-1px;margin-bottom:2px;}
.login-logo span{color:var(--accent);}
.login-sub{color:var(--muted);font-size:13px;margin-bottom:32px;}
.login-card{background:var(--bg-card);border:1px solid var(--border-hi);border-radius:18px;padding:36px;backdrop-filter:blur(20px);}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.fg input,.fg select{width:100%;padding:11px 14px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font-size:14px;outline:none;transition:border-color .2s;font-family:var(--font-b);}
.fg input:focus,.fg select:focus{border-color:var(--accent);background:rgba(74,159,255,0.07);}
.fg select option{background:#01204c;}
.btn-primary{background:var(--accent);color:#fff;width:100%;padding:12px 20px;border:none;border-radius:var(--r-sm);font-size:14px;font-weight:600;cursor:pointer;margin-top:4px;font-family:var(--font-b);}
.btn-primary:hover{opacity:.88;}
.err{color:#e74c3c;font-size:12px;margin-bottom:12px;background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);border-radius:6px;padding:8px 12px;}
.demo-hint{margin-top:18px;text-align:center;font-size:12px;color:var(--dim);}
.demo-hint strong{color:var(--muted);}
</style>
</head>
<body>
<div class="login-wrap">
  <a href="index.php" class="auth-back">← Back to home</a>
  <div class="login-logo">in<span>S</span>ight</div>
  <div class="login-sub">Utility Consumption &amp; Tracking System</div>
  <div class="login-card">
    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="login.php">
      <div class="fg">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" autocomplete="off" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="fg">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password">
      </div>
      <div class="fg">
        <label>Sign in as</label>
        <select name="role">
          <option value="user"  <?= ($_POST['role'] ?? '') === 'user'  ? 'selected' : '' ?>>Household User</option>
          <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <button type="submit" class="btn-primary">Sign In</button>
    </form>
  </div>
  <div class="demo-hint">Demo: <strong>user / user123</strong> &nbsp;·&nbsp; Admin: <strong>admin / admin123</strong></div>
</div>
</body>
</html>

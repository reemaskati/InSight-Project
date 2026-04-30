<?php
require_once 'db.php';
startSecureSession();

if (!empty($_SESSION['user_id']))  { header('Location: dashboard.php'); exit; }
if (!empty($_SESSION['admin_id'])) { header('Location: admin.php');     exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';
    $age      = (int)($_POST['age']      ?? 0);

    if (!$name || !$username || !$email || !$password || $age < 1) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = getDB();
        // Check if username or email already exists
        $check = $db->prepare("SELECT UserID FROM USER WHERE Username=? OR Email=?");
        $check->bind_param('ss', $username, $email);
        $check->execute();
        $check->get_result()->num_rows > 0
            ? $error = 'Username or email already exists.'
            : null;

        if (!$error) {
            $stmt = $db->prepare("INSERT INTO USER (Username, Name, Email, Password, Age, CreatedAt) VALUES (?,?,?,?,?,CURDATE())");
            $stmt->bind_param('ssssi', $username, $name, $email, $password, $age);
            if ($stmt->execute()) {
                // Auto login after register
                $newUser = [
                    'id'    => $db->insert_id,
                    'name'  => $name,
                    'Email' => $email,
                    'Age'   => $age,
                    'Budget'=> 500,
                ];
                setUserSession($newUser);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — Register</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--dim:rgba(232,240,254,0.30);--accent:#4a9fff;--red:#e74c3c;--green:#2ecc71;--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:var(--font-b);background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
body::before{content:'';position:absolute;width:700px;height:700px;background:radial-gradient(circle,rgba(74,159,255,.12) 0%,transparent 65%);top:-200px;right:-150px;pointer-events:none;}
body::after{content:'';position:absolute;width:500px;height:500px;background:radial-gradient(circle,rgba(46,204,113,.07) 0%,transparent 65%);bottom:-150px;left:-100px;pointer-events:none;}
@keyframes fadeup{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
a{text-decoration:none;}
.wrap{position:relative;z-index:1;width:440px;animation:fadeup .5s ease;}
.auth-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-bottom:20px;transition:color .2s;}
.auth-back:hover{color:var(--text);}
.logo{font-family:var(--font-d);font-size:42px;letter-spacing:-1px;margin-bottom:2px;}
.logo span{color:var(--accent);}
.sub{color:var(--muted);font-size:13px;margin-bottom:32px;}
.card{background:var(--bg-card);border:1px solid var(--border-hi);border-radius:18px;padding:36px;backdrop-filter:blur(20px);}
.card h2{font-family:var(--font-d);font-size:22px;margin-bottom:20px;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.fg input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font-size:14px;outline:none;transition:border-color .2s;font-family:var(--font-b);}
.fg input:focus{border-color:var(--accent);background:rgba(74,159,255,0.07);}
.btn-primary{background:var(--accent);color:#fff;width:100%;padding:12px 20px;border:none;border-radius:var(--r-sm);font-size:14px;font-weight:600;cursor:pointer;margin-top:6px;font-family:var(--font-b);transition:opacity .2s;}
.btn-primary:hover{opacity:.88;}
.err{color:var(--red);font-size:12px;margin-bottom:14px;background:rgba(231,76,60,0.08);border:1px solid rgba(231,76,60,0.25);border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:8px;}
.login-link{margin-top:16px;text-align:center;font-size:13px;color:var(--muted);}
.login-link a{color:var(--accent);}
</style>
</head>
<body>
<div class="wrap">
  <a href="index.html" class="auth-back">← Back to home</a>
  <div class="logo">in<span>S</span>ight</div>
  <div class="sub">Create your free account</div>
  <div class="card">
    <h2>Register</h2>
    <?php if ($error): ?>
      <div class="err"><span>⚠️</span><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="register.php">
      <div class="row">
        <div class="fg"><label>Full Name</label><input type="text" name="name" placeholder="Sara Al-Rashidi" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
        <div class="fg"><label>Age</label><input type="number" name="age" placeholder="29" min="1" max="120" required value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"></div>
      </div>
      <div class="fg"><label>Username</label><input type="text" name="username" placeholder="sara123" autocomplete="off" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></div>
      <div class="fg"><label>Email</label><input type="email" name="email" placeholder="sara@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
      <div class="fg"><label>Password</label><input type="password" name="password" placeholder="Min 6 characters" required></div>
      <button type="submit" class="btn-primary">Create Account</button>
    </form>
    <div class="login-link">Already have an account? <a href="login.php">Sign In</a></div>
  </div>
</div>
</body>
</html>

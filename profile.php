<?php
require_once 'db.php';
requireLogin();

$userID   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userEmail= $_SESSION['user_email'] ?? '';
$userAge  = $_SESSION['user_age']   ?? '';
$budget   = (float)($_SESSION['user_budget'] ?? 500);

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $age   = (int)($_POST['age']  ?? 0);
        if (!$name || !$email || $age < 1) {
            $error = 'Please fill in all fields correctly.';
        } else {
            $r = updateProfile($userID, $name, $email, $age);
            if ($r['success']) {
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_age']   = $age;
                $userName = $name;
                $userEmail = $email;
                $userAge   = $age;
                $msg = 'Profile updated successfully!';
            } else {
                $error = $r['message'];
            }
        }
    } elseif ($action === 'update_budget') {
        $nb = (float)($_POST['budget'] ?? 0);
        if ($nb <= 0) {
            $error = 'Budget must be greater than 0.';
        } else {
            $r = updateBudget($userID, $nb);
            if ($r['success']) {
                $_SESSION['user_budget'] = $nb;
                $budget = $nb;
                $msg = 'Budget updated to SAR ' . number_format($nb, 2);
            }
        }
    }
}

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $userName), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — My Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--bg-card-hover:rgba(255,255,255,0.10);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--accent:#4a9fff;--accent-bg:rgba(74,159,255,0.13);--gold:#f4c94b;--red:#e74c3c;--green:#2ecc71;--green-bg:rgba(46,204,113,0.13);--sidebar:230px;--r:12px;--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;--font-m:'DM Mono',monospace;}
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
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);padding:20px;}
.divider{height:1px;background:var(--border);margin:24px 0;}
.btn{padding:10px 18px;border:none;border-radius:var(--r-sm);font-size:14px;font-weight:600;cursor:pointer;font-family:var(--font-b);transition:opacity .15s;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{opacity:.88;}
.btn-ghost{background:transparent;border:1px solid var(--border-hi);color:var(--text);}
.btn-ghost:hover{background:var(--bg-card-hover);}
.btn-sm{padding:6px 12px;font-size:12px;}
.alert{padding:12px 16px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px;}
.alert-success{background:var(--green-bg);border:1px solid rgba(46,204,113,.3);color:var(--green);}
.alert-error{background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,.3);color:var(--red);}
.profile-card{display:flex;align-items:center;gap:20px;padding:24px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);margin-bottom:20px;}
.avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#7b6ff0);display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-size:24px;color:#fff;flex-shrink:0;}
.profile-name{font-family:var(--font-d);font-size:22px;}
.profile-role{font-size:12px;color:var(--muted);margin-top:3px;}
.profile-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;}
.pf-item{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px;}
.pf-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:4px;}
.pf-val{font-size:15px;font-weight:500;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#012458;border:1px solid var(--border-hi);border-radius:18px;padding:32px;width:420px;max-height:90vh;overflow-y:auto;animation:fadeup .3s ease;}
.modal h2{font-family:var(--font-d);font-size:22px;margin-bottom:20px;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.fg input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font-size:14px;outline:none;transition:border-color .2s;font-family:var(--font-b);}
.fg input:focus{border-color:var(--accent);}
</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sb-logo">in<span>S</span>ight</div>
    <nav class="sb-nav">
      <a href="dashboard.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Home
      </a>
      <a href="bills.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Bill History
      </a>
      <a href="profile.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile
      </a>
    </nav>
    <div class="sb-bottom">
      <div class="sb-user">
        <div class="sb-user-name"><?php echo htmlspecialchars($userName); ?></div>
        <div class="sb-user-role">Household User</div>
      </div>
      <a href="logout.php" class="sb-item" style="color:var(--red);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
      </a>
    </div>
  </aside>

  <main class="main">
    <div class="page">
      <div class="page-title">My Profile</div>
      <div class="page-sub">Manage your account information</div>

      <?php if ($msg && !$error): ?>
        <div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-error">&#9888; <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="profile-card">
        <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div>
          <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
          <div class="profile-role">Household User</div>
        </div>
        <button class="btn btn-ghost btn-sm" style="margin-left:auto;" onclick="openEdit()">Edit Profile</button>
      </div>

      <div class="profile-fields">
        <div class="pf-item">
          <div class="pf-label">Full Name</div>
          <div class="pf-val"><?php echo htmlspecialchars($userName); ?></div>
        </div>
        <div class="pf-item">
          <div class="pf-label">Email</div>
          <div class="pf-val"><?php echo htmlspecialchars($userEmail); ?></div>
        </div>
        <div class="pf-item">
          <div class="pf-label">Age</div>
          <div class="pf-val"><?php echo (int)$userAge; ?></div>
        </div>
        <div class="pf-item">
          <div class="pf-label">Username</div>
          <div class="pf-val" style="font-family:var(--font-m);"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></div>
        </div>
      </div>

      <div class="divider"></div>
      <div style="font-size:15px;font-weight:600;margin-bottom:14px;">Budget Settings</div>
      <div class="card" style="max-width:380px;">
        <form method="POST" action="profile.php">
          <input type="hidden" name="action" value="update_budget">
          <div class="fg" style="margin-bottom:0;">
            <label>Monthly Budget Threshold (SAR)</label>
            <div style="display:flex;gap:10px;margin-top:8px;">
              <input type="number" name="budget" style="flex:1;" min="1" value="<?php echo $budget; ?>">
              <button type="submit" class="btn btn-primary" style="width:auto;">Save</button>
            </div>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:10px;">
            Current: <strong style="color:var(--gold);">SAR <?php echo number_format($budget, 2); ?></strong>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <h2>Edit Profile</h2>
    <form method="POST" action="profile.php">
      <input type="hidden" name="action" value="update_profile">
      <div class="fg">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($userName); ?>" required>
      </div>
      <div class="fg">
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" required>
      </div>
      <div class="fg">
        <label>Age</label>
        <input type="number" name="age" value="<?php echo (int)$userAge; ?>" min="1" max="120" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(){ document.getElementById('modal-edit').classList.add('open'); }
function closeEdit(){ document.getElementById('modal-edit').classList.remove('open'); }
document.getElementById('modal-edit').addEventListener('click', function(e){
  if(e.target === document.getElementById('modal-edit')) closeEdit();
});
</script>
</body>
</html>

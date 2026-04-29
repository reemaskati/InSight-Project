<?php
require_once 'db.php';
requireAdmin();

$adminName = $_SESSION['admin_name'];
$db = getDB();
$msg = ''; $error = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $age      = (int)($_POST['age']     ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password']       ?? '';

        if (!$name || !$email || !$username || !$password || $age < 1) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $db->prepare("SELECT UserID FROM USER WHERE Username=? OR Email=?");
            $check->bind_param('ss', $username, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'Username or email already exists.';
            } else {
                $stmt = $db->prepare("INSERT INTO USER (Username, Name, Email, Password, Age, CreatedAt) VALUES (?,?,?,?,?,CURDATE())");
                $stmt->bind_param('ssssi', $username, $name, $email, $password, $age);
                $stmt->execute()
                    ? $msg = 'User created successfully!'
                    : $error = 'Failed to create user.';
            }
        }

    } elseif ($action === 'toggle_block') {
        $userID = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE USER SET Blocked = NOT Blocked WHERE UserID=?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $msg = 'User status updated.';

    } elseif ($action === 'delete_user') {
        $userID = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM USER WHERE UserID=?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $msg = 'User deleted.';
    }

    header('Location: admin.php?msg=' . urlencode($msg) . ($error ? '&err=1&errmsg=' . urlencode($error) : ''));
    exit;
}

if (isset($_GET['msg']))    $msg   = $_GET['msg'];
if (isset($_GET['errmsg'])) $error = $_GET['errmsg'];

// ── Fetch all users with bill count ──────────────────────────
$search = trim($_GET['search'] ?? '');
$sql = "SELECT u.UserID, u.Username, u.Name, u.Email, u.Age, u.Blocked, u.Budget,
               COUNT(b.BillID) AS BillCount
        FROM USER u
        LEFT JOIN BILL b ON u.UserID = b.UserID";
if ($search !== '') {
    $sql .= " WHERE u.Name LIKE '%" . $db->real_escape_string($search) . "%'
               OR u.Email LIKE '%" . $db->real_escape_string($search) . "%'
               OR u.Username LIKE '%" . $db->real_escape_string($search) . "%'";
}
$sql .= " GROUP BY u.UserID ORDER BY u.UserID";
$users   = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
$total   = count($users);
$blocked = count(array_filter($users, fn($u) => $u['Blocked']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — Admin Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--bg-card-hover:rgba(255,255,255,0.10);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--dim:rgba(232,240,254,0.30);--green:#2ecc71;--green-bg:rgba(46,204,113,0.13);--red:#e74c3c;--red-bg:rgba(231,76,60,0.13);--accent:#4a9fff;--accent-bg:rgba(74,159,255,0.13);--sidebar:230px;--r:12px;--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;--font-m:'DM Mono',monospace;}
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
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:24px;}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-green{background:var(--green-bg);color:var(--green);}
.badge-red{background:var(--red-bg);color:var(--red);}
.badge-blue{background:var(--accent-bg);color:var(--accent);}
.btn{padding:8px 16px;border:none;border-radius:var(--r-sm);font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b);transition:opacity .15s;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{opacity:.88;}
.btn-ghost{background:transparent;border:1px solid var(--border-hi);color:var(--text);}
.btn-ghost:hover{background:var(--bg-card-hover);}
.btn-danger{background:var(--red-bg);color:var(--red);border:1px solid rgba(231,76,60,.3);}
.btn-danger:hover{background:rgba(231,76,60,.22);}
.btn-sm{padding:5px 10px;font-size:12px;}
.admin-header{display:flex;align-items:center;gap:14px;padding:16px 20px;background:linear-gradient(90deg,rgba(74,159,255,.12),transparent);border:1px solid rgba(74,159,255,.2);border-radius:var(--r);margin-bottom:24px;}
.admin-badge-label{background:var(--accent-bg);color:var(--accent);border:1px solid rgba(74,159,255,.3);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;}
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.search-box{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);padding:8px 12px;flex:1;}
.search-box input{background:none;border:none;outline:none;color:var(--text);font-size:13px;font-family:var(--font-b);width:100%;}
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{text-align:left;padding:10px 14px;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);background:rgba(0,0,0,0.15);}
td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:var(--bg-card-hover);}
.tbl-actions{display:flex;gap:6px;}
.alert{padding:12px 16px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px;}
.alert-success{background:var(--green-bg);border:1px solid rgba(46,204,113,.3);color:var(--green);}
.alert-error{background:var(--red-bg);border:1px solid rgba(231,76,60,.3);color:var(--red);}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#012458;border:1px solid var(--border-hi);border-radius:18px;padding:32px;width:460px;max-height:90vh;overflow-y:auto;animation:fadeup .3s ease;}
.modal h2{font-family:var(--font-d);font-size:22px;margin-bottom:20px;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.fg input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font-size:14px;outline:none;transition:border-color .2s;font-family:var(--font-b);}
.fg input:focus{border-color:var(--accent);}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.confirm-modal{background:#012458;border:1px solid rgba(231,76,60,.4);border-radius:18px;padding:28px;width:360px;animation:fadeup .3s ease;}
.confirm-modal h3{font-size:18px;font-weight:600;margin-bottom:10px;}
.confirm-modal p{font-size:13px;color:var(--muted);margin-bottom:24px;}
.confirm-footer{display:flex;gap:10px;justify-content:flex-end;}
</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sb-logo">in<span>S</span>ight</div>
    <nav class="sb-nav">
      <a href="admin.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Admin Panel
      </a>
      <a href="admin_profile.php" class="sb-item">
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
      <div class="page-title">Admin Panel</div>
      <div class="page-sub">Manage all registered users</div>

      <?php if ($msg && !$error): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="admin-header">
        <div class="admin-badge-label">Admin</div>
        <div>
          <div style="font-weight:600;"><?= htmlspecialchars($adminName) ?></div>
          <div style="font-size:12px;color:var(--muted);">Full system access</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:20px;text-align:center;">
          <div><div style="font-family:var(--font-d);font-size:22px;"><?= $total ?></div><div style="font-size:11px;color:var(--muted);">Users</div></div>
          <div><div style="font-family:var(--font-d);font-size:22px;color:var(--red);"><?= $blocked ?></div><div style="font-size:11px;color:var(--muted);">Blocked</div></div>
        </div>
      </div>

      <div class="card">
        <form method="GET" action="admin.php">
          <div class="toolbar">
            <div style="font-size:15px;font-weight:600;flex:1;">Registered Users</div>
            <div class="search-box">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" name="search" placeholder="Search users…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Search</button>
            <a href="admin.php" class="btn btn-ghost btn-sm">Clear</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddUser()">+ Add User</button>
          </div>
        </form>

        <div class="tbl-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Name</th><th>Email</th><th>Age</th><th>Bills</th><th>Budget</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted);">No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $i => $u): ?>
                <tr style="<?= $u['Blocked'] ? 'opacity:.55' : '' ?>">
                  <td style="color:var(--muted);font-family:var(--font-m)"><?= $i+1 ?></td>
                  <td>
                    <strong><?= htmlspecialchars($u['Name']) ?></strong>
                    <div style="font-size:11px;color:var(--muted);font-family:monospace;">@<?= htmlspecialchars($u['Username']) ?></div>
                  </td>
                  <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($u['Email']) ?></td>
                  <td><?= $u['Age'] ?></td>
                  <td><span class="badge badge-blue"><?= $u['BillCount'] ?></span></td>
                  <td style="font-size:12px;">SAR <?= number_format($u['Budget'], 2) ?></td>
                  <td><span class="badge <?= $u['Blocked'] ? 'badge-red' : 'badge-green' ?>"><?= $u['Blocked'] ? 'Blocked' : 'Active' ?></span></td>
                  <td>
                    <div class="tbl-actions">
                      <form method="POST" action="admin.php" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_block">
                        <input type="hidden" name="user_id" value="<?= $u['UserID'] ?>">
                        <button type="submit" class="btn <?= $u['Blocked'] ? 'btn-ghost' : 'btn-danger' ?> btn-sm">
                          <?= $u['Blocked'] ? 'Unblock' : 'Block' ?>
                        </button>
                      </form>
                      <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $u['UserID'] ?>, '<?= htmlspecialchars($u['Name']) ?>')">Delete</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <h2>Add New User</h2>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="add_user">
      <div class="row2">
        <div class="fg"><label>Full Name</label><input type="text" name="name" placeholder="Sara Al-Rashidi" required></div>
        <div class="fg"><label>Age</label><input type="number" name="age" placeholder="29" min="1" max="120" required></div>
      </div>
      <div class="fg"><label>Username</label><input type="text" name="username" placeholder="sara123" required></div>
      <div class="fg"><label>Email</label><input type="email" name="email" placeholder="sara@example.com" required></div>
      <div class="fg"><label>Password</label><input type="password" name="password" placeholder="Min 6 characters" required></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeAdd()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="confirm-modal">
  <div class="confirm-modal">
    <h3>Delete User</h3>
    <p id="confirm-text">Are you sure you want to delete this user? All their bills will also be deleted.</p>
    <form method="POST" action="admin.php" id="delete-form">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="delete-user-id" value="">
      <div class="confirm-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('confirm-modal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddUser(){ document.getElementById('modal-add').classList.add('open'); }
function closeAdd(){ document.getElementById('modal-add').classList.remove('open'); }

function confirmDelete(id, name){
  document.getElementById('delete-user-id').value = id;
  document.getElementById('confirm-text').textContent = `Are you sure you want to delete "${name}"? All their bills will also be deleted.`;
  document.getElementById('confirm-modal').classList.add('open');
}

['modal-add','confirm-modal'].forEach(id=>{
  document.getElementById(id).addEventListener('click',e=>{
    if(e.target===document.getElementById(id))
      document.getElementById(id).classList.remove('open');
  });
});
</script>
</body>
</html>

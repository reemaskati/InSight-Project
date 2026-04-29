<?php
require_once 'db.php';
requireLogin();

$userID   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$budget   = (float)($_SESSION['user_budget'] ?? 500);

// ── Handle POST actions ───────────────────────────────────────
$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type  = $_POST['bill_type']     ?? '';
        $month = $_POST['billing_month'] ?? '';
        $meter = (float)($_POST['meter_reading'] ?? 0);
        $cost  = (float)($_POST['total_cost']    ?? 0);
        if (!$type || !$month || $meter < 0 || $cost < 0) {
            $error = 'Please fill in all fields with valid values.';
        } else {
            $r = addBill($userID, $type, $month, $meter, $cost);
            $msg = $r['success'] ? 'Bill added successfully!' : $r['message'];
            if (!$r['success']) $error = $r['message'];
        }
    } elseif ($action === 'update') {
        $billID = (int)($_POST['bill_id'] ?? 0);
        $type   = $_POST['bill_type']     ?? '';
        $month  = $_POST['billing_month'] ?? '';
        $meter  = (float)($_POST['meter_reading'] ?? 0);
        $cost   = (float)($_POST['total_cost']    ?? 0);
        $r = updateBill($billID, $userID, $type, $month, $meter, $cost);
        $msg = $r['success'] ? 'Bill updated successfully!' : $r['message'];
        if (!$r['success']) $error = $r['message'];
    } elseif ($action === 'delete') {
        $billID = (int)($_POST['bill_id'] ?? 0);
        deleteBill($billID, $userID);
        $msg = 'Bill deleted.';
    }
    // Redirect to avoid resubmission
    header('Location: bills.php?msg=' . urlencode($msg) . ($error ? '&err=1' : ''));
    exit;
}

if (isset($_GET['msg'])) $msg   = $_GET['msg'];
if (isset($_GET['err'])) $error = $msg;

// ── Filters ───────────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$filterType = $_GET['type']   ?? '';
$filterYear = $_GET['year']   ?? '';
$filterStat = $_GET['status'] ?? '';

// Get all bills with filters
$db     = getDB();
$sql    = "SELECT * FROM BILL WHERE UserID=?";
$params = [$userID];
$types  = 'i';

if ($search !== '') {
    $sql .= " AND (DATE_FORMAT(BillingMonth,'%M %Y') LIKE ? OR DATE_FORMAT(BillingMonth,'%Y-%m') LIKE ?)";
    $like = '%'.$search.'%'; $params[]=$like; $params[]=$like; $types.='ss';
}
if ($filterType !== '') { $sql.=" AND BillType=?"; $params[]=$filterType; $types.='s'; }
if ($filterYear !== '') { $sql.=" AND YEAR(BillingMonth)=?"; $params[]=(int)$filterYear; $types.='i'; }
$sql .= " ORDER BY BillingMonth DESC, BillType";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Apply status filter in PHP (needs budget)
if ($filterStat === 'over') {
    $bills = array_filter($bills, fn($b) => $b['TotalCost'] > $budget);
} elseif ($filterStat === 'ok') {
    $bills = array_filter($bills, fn($b) => $b['TotalCost'] <= $budget);
}
$bills = array_values($bills);

// Get available years for filter dropdown
$yStmt = $db->prepare("SELECT DISTINCT YEAR(BillingMonth) AS yr FROM BILL WHERE UserID=? ORDER BY yr DESC");
$yStmt->bind_param('i', $userID);
$yStmt->execute();
$years = array_column($yStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'yr');

// If editing, fetch that bill
$editBill = null;
if (isset($_GET['edit'])) {
    $editBill = getBillByID((int)$_GET['edit'], $userID);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — Bill History</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--bg-card-hover:rgba(255,255,255,0.10);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--accent:#4a9fff;--accent-bg:rgba(74,159,255,0.13);--gold:#f4c94b;--gold-bg:rgba(244,201,75,0.13);--red:#e74c3c;--red-bg:rgba(231,76,60,0.13);--green:#2ecc71;--green-bg:rgba(46,204,113,0.13);--sidebar:230px;--r:12px;--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;--font-m:'DM Mono',monospace;}
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
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.search-box{display:flex;align-items:center;gap:8px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r-sm);padding:9px 14px;flex:1;min-width:180px;}
.search-box input{background:none;border:none;outline:none;color:var(--text);font-size:13px;font-family:var(--font-b);width:100%;}
.filter-select{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r-sm);padding:9px 12px;color:var(--text);font-size:13px;font-family:var(--font-b);outline:none;cursor:pointer;}
.filter-select option{background:#01204c;}
.btn{padding:9px 16px;border:none;border-radius:var(--r-sm);font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b);transition:opacity .15s;display:inline-flex;align-items:center;gap:6px;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{opacity:.88;}
.btn-ghost{background:transparent;border:1px solid var(--border-hi);color:var(--text);}
.btn-ghost:hover{background:var(--bg-card-hover);}
.btn-danger{background:var(--red-bg);color:var(--red);border:1px solid rgba(231,76,60,.3);}
.btn-danger:hover{background:rgba(231,76,60,.22);}
.btn-sm{padding:5px 10px;font-size:12px;}
.results-info{font-size:12px;color:var(--muted);margin-bottom:10px;}
.tbl-wrap{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{text-align:left;padding:12px 16px;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);background:rgba(0,0,0,0.15);}
td{padding:13px 16px;border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:var(--bg-card-hover);}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-elec{background:var(--gold-bg);color:var(--gold);}
.badge-water{background:var(--accent-bg);color:var(--accent);}
.badge-over{background:var(--red-bg);color:var(--red);}
.badge-ok{background:var(--green-bg);color:var(--green);}
.actions{display:flex;gap:6px;}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
.empty-icon{font-size:40px;margin-bottom:12px;}
.alert{padding:12px 16px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px;}
.alert-success{background:var(--green-bg);border:1px solid rgba(46,204,113,.3);color:var(--green);}
.alert-error{background:var(--red-bg);border:1px solid rgba(231,76,60,.3);color:var(--red);}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#012458;border:1px solid var(--border-hi);border-radius:18px;padding:32px;width:440px;max-height:90vh;overflow-y:auto;animation:fadeup .3s ease;}
.modal h2{font-family:var(--font-d);font-size:22px;margin-bottom:20px;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.fg input,.fg select{width:100%;padding:11px 14px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font-size:14px;outline:none;transition:border-color .2s;font-family:var(--font-b);}
.fg input:focus,.fg select:focus{border-color:var(--accent);}
.fg select option{background:#01204c;}
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
      <a href="dashboard.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Home
      </a>
      <a href="bills.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Bill History
      </a>
      <a href="profile.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile
      </a>
    </nav>
    <div class="sb-bottom">
      <div class="sb-user">
        <div class="sb-user-name"><?= htmlspecialchars($userName) ?></div>
        <div class="sb-user-role">Household User</div>
      </div>
      <a href="logout.php" class="sb-item" style="color:var(--red);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
      </a>
    </div>
  </aside>

  <main class="main">
    <div class="page">
      <div class="page-title">Bill History</div>
      <div class="page-sub">Manage all your electricity and water bills</div>

      <?php if ($msg && !$error): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- TOOLBAR -->
      <form method="GET" action="bills.php">
        <div class="toolbar">
          <div class="search-box">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="search" placeholder="Search by month or year…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <select name="type" class="filter-select">
            <option value="">All Types</option>
            <option value="electricity" <?= $filterType==='electricity'?'selected':'' ?>>Electricity</option>
            <option value="water"       <?= $filterType==='water'      ?'selected':'' ?>>Water</option>
          </select>
          <select name="year" class="filter-select">
            <option value="">All Years</option>
            <?php foreach ($years as $yr): ?>
              <option value="<?= $yr ?>" <?= $filterYear==$yr?'selected':'' ?>><?= $yr ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="filter-select">
            <option value="">All Status</option>
            <option value="over" <?= $filterStat==='over'?'selected':'' ?>>Over Budget</option>
            <option value="ok"   <?= $filterStat==='ok'  ?'selected':'' ?>>Within Budget</option>
          </select>
          <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
          <a href="bills.php" class="btn btn-ghost btn-sm">Clear</a>
          <button type="button" class="btn btn-primary" onclick="openAddModal()">+ Add Bill</button>
        </div>
      </form>

      <div class="results-info">Showing <?= count($bills) ?> bill(s)</div>

      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Month</th><th>Type</th><th>Meter Reading</th><th>Total Cost</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if (empty($bills)): ?>
              <tr><td colspan="7">
                <div class="empty-state">
                  <div class="empty-icon">📋</div>
                  <p>No bills found.</p>
                  <button class="btn btn-primary" onclick="openAddModal()">+ Add First Bill</button>
                </div>
              </td></tr>
            <?php else: ?>
              <?php foreach ($bills as $i => $b):
                $over = $b['TotalCost'] > $budget;
                $unit = $b['BillType']==='electricity' ? 'kWh' : 'm³';
                $monthLabel = date('F Y', strtotime($b['BillingMonth']));
                $monthVal   = date('Y-m', strtotime($b['BillingMonth']));
              ?>
              <tr>
                <td style="color:var(--muted);font-family:var(--font-m)"><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($monthLabel) ?></strong></td>
                <td><span class="badge <?= $b['BillType']==='electricity'?'badge-elec':'badge-water' ?>">
                  <?= $b['BillType']==='electricity'?'⚡ Electricity':'💧 Water' ?>
                </span></td>
                <td style="font-family:var(--font-m)"><?= $b['MeterReading'] ?> <?= $unit ?></td>
                <td style="font-family:var(--font-m);font-weight:600;color:<?= $over?'var(--red)':'var(--text)' ?>">
                  SAR <?= number_format($b['TotalCost'], 2) ?>
                </td>
                <td><span class="badge <?= $over?'badge-over':'badge-ok' ?>"><?= $over?'Over Budget':'Within Budget' ?></span></td>
                <td>
                  <div class="actions">
                    <button class="btn btn-ghost btn-sm"
                      onclick="openEditModal(<?= $b['BillID'] ?>,'<?= $b['BillType'] ?>','<?= $monthVal ?>',<?= $b['MeterReading'] ?>,<?= $b['TotalCost'] ?>)">
                      Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="openDeleteConfirm(<?= $b['BillID'] ?>)">Delete</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal-overlay" id="bill-modal">
  <div class="modal">
    <h2 id="modal-title">Add Bill</h2>
    <form method="POST" action="bills.php">
      <input type="hidden" name="action" id="f-action" value="add">
      <input type="hidden" name="bill_id" id="f-bill-id" value="">
      <div class="fg">
        <label>Bill Type</label>
        <select name="bill_type" id="f-type">
          <option value="electricity">⚡ Electricity</option>
          <option value="water">💧 Water</option>
        </select>
      </div>
      <div class="fg">
        <label>Billing Month</label>
        <input type="month" name="billing_month" id="f-month" required>
      </div>
      <div class="fg">
        <label>Meter Reading (kWh / m³)</label>
        <input type="number" name="meter_reading" id="f-meter" placeholder="e.g. 320" min="0" step="0.01" required>
      </div>
      <div class="fg">
        <label>Total Cost (SAR)</label>
        <input type="number" name="total_cost" id="f-cost" placeholder="e.g. 185.50" min="0" step="0.01" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Bill</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="confirm-modal">
  <div class="confirm-modal">
    <h3>Delete Bill</h3>
    <p>Are you sure you want to permanently delete this bill? This action cannot be undone.</p>
    <form method="POST" action="bills.php" id="delete-form">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="bill_id" id="delete-bill-id" value="">
      <div class="confirm-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('confirm-modal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal(){
  document.getElementById('modal-title').textContent = 'Add Bill';
  document.getElementById('f-action').value  = 'add';
  document.getElementById('f-bill-id').value = '';
  document.getElementById('f-type').value    = 'electricity';
  document.getElementById('f-month').value   = '';
  document.getElementById('f-meter').value   = '';
  document.getElementById('f-cost').value    = '';
  document.getElementById('bill-modal').classList.add('open');
}

function openEditModal(id, type, month, meter, cost){
  document.getElementById('modal-title').textContent = 'Edit Bill';
  document.getElementById('f-action').value  = 'update';
  document.getElementById('f-bill-id').value = id;
  document.getElementById('f-type').value    = type;
  document.getElementById('f-month').value   = month;
  document.getElementById('f-meter').value   = meter;
  document.getElementById('f-cost').value    = cost;
  document.getElementById('bill-modal').classList.add('open');
}

function closeModal(){ document.getElementById('bill-modal').classList.remove('open'); }

function openDeleteConfirm(id){
  document.getElementById('delete-bill-id').value = id;
  document.getElementById('confirm-modal').classList.add('open');
}

['bill-modal','confirm-modal'].forEach(id=>{
  document.getElementById(id).addEventListener('click',e=>{
    if(e.target===document.getElementById(id))
      document.getElementById(id).classList.remove('open');
  });
});

<?php if ($editBill): ?>
openEditModal(<?= $editBill['BillID'] ?>,'<?= $editBill['BillType'] ?>',
  '<?= date('Y-m',strtotime($editBill['BillingMonth'])) ?>',
  <?= $editBill['MeterReading'] ?>,<?= $editBill['TotalCost'] ?>);
<?php endif; ?>
</script>
</body>
</html>

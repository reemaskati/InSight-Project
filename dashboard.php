<?php
require_once 'db.php';
requireLogin();

$userID   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$budget   = (float)($_SESSION['user_budget'] ?? 500);

// ── Fetch all data server-side ────────────────────────────────
$db = getDB();

// Year totals
$stmt = $db->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN BillType='electricity' THEN TotalCost ELSE 0 END),0) AS totalElec,
        COALESCE(SUM(CASE WHEN BillType='water'       THEN TotalCost ELSE 0 END),0) AS totalWater,
        COALESCE(SUM(TotalCost),0) AS grandTotal,
        COUNT(BillID) AS billCount
    FROM BILL WHERE UserID=? AND YEAR(BillingMonth)=YEAR(CURDATE())
");
$stmt->bind_param('i', $userID);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();

// Monthly data last 6 months for chart
$stmt2 = $db->prepare("
    SELECT DATE_FORMAT(BillingMonth,'%b %Y') AS label,
           DATE_FORMAT(BillingMonth,'%Y-%m') AS ym,
           COALESCE(SUM(CASE WHEN BillType='electricity' THEN TotalCost ELSE 0 END),0) AS elec,
           COALESCE(SUM(CASE WHEN BillType='water'       THEN TotalCost ELSE 0 END),0) AS water,
           COALESCE(SUM(TotalCost),0) AS total
    FROM BILL
    WHERE UserID=? AND BillingMonth >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY BillingMonth ORDER BY BillingMonth ASC
");
$stmt2->bind_param('i', $userID);
$stmt2->execute();
$monthly = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// All months for overview
$stmt3 = $db->prepare("
    SELECT DATE_FORMAT(BillingMonth,'%M %Y') AS label,
           DATE_FORMAT(BillingMonth,'%Y-%m') AS ym,
           COALESCE(SUM(CASE WHEN BillType='electricity' THEN TotalCost ELSE 0 END),0) AS elec,
           COALESCE(SUM(CASE WHEN BillType='water'       THEN TotalCost ELSE 0 END),0) AS water,
           COALESCE(SUM(TotalCost),0) AS total,
           COALESCE(SUM(CASE WHEN BillType='electricity' THEN MeterReading ELSE 0 END),0) AS elecMeter,
           COALESCE(SUM(CASE WHEN BillType='water'       THEN MeterReading ELSE 0 END),0) AS waterMeter
    FROM BILL WHERE UserID=?
    GROUP BY BillingMonth ORDER BY BillingMonth DESC
");
$stmt3->bind_param('i', $userID);
$stmt3->execute();
$allMonths = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

// Saving tips
$tips = getSavingTips($userID);

// Pass data to JS
$chartLabels = json_encode(array_column($monthly, 'label'));
$chartElec   = json_encode(array_map(fn($r)=>(float)$r['elec'],   $monthly));
$chartWater  = json_encode(array_map(fn($r)=>(float)$r['water'],  $monthly));
$maxTotal    = $allMonths ? max(array_column($allMonths, 'total')) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>inSight — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{--bg:#01204c;--bg-deep:#010f2a;--bg-card:rgba(255,255,255,0.06);--bg-card-hover:rgba(255,255,255,0.10);--border:rgba(255,255,255,0.10);--border-hi:rgba(255,255,255,0.20);--text:#e8f0fe;--muted:rgba(232,240,254,0.55);--accent:#4a9fff;--accent-bg:rgba(74,159,255,0.13);--gold:#f4c94b;--red:#e74c3c;--red-bg:rgba(231,76,60,0.13);--green:#2ecc71;--green-bg:rgba(46,204,113,0.13);--sidebar:230px;--r:12px;--r-sm:8px;--font-d:'Abril Fatface',serif;--font-b:'DM Sans',sans-serif;--font-m:'DM Mono',monospace;}
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
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);padding:20px;}
.stat-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:8px;}
.stat-val{font-family:var(--font-d);font-size:26px;line-height:1;}
.stat-sub{font-size:12px;color:var(--muted);margin-top:6px;}
.charts-row{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px;}
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:20px;}
.card-title{font-size:15px;font-weight:600;margin-bottom:16px;}
.month-grid{display:grid;gap:8px;}
.month-row{display:grid;grid-template-columns:130px 1fr auto auto;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--r-sm);background:rgba(255,255,255,0.03);border:1px solid var(--border);}
.month-row.over-budget{border-color:rgba(231,76,60,.3);background:var(--red-bg);}
.month-name{font-size:13px;font-weight:600;}
.month-bar-wrap{height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;}
.month-bar{height:100%;border-radius:3px;background:var(--accent);}
.month-bar.over{background:var(--red);}
.month-cost{font-size:13px;font-weight:600;font-family:var(--font-m);min-width:90px;text-align:right;}
.tips-list{display:grid;gap:10px;}
.tip-card{background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px 16px;display:flex;gap:12px;}
.tip-icon{font-size:18px;flex-shrink:0;}
.tip-type{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;}
.tip-type.electricity{color:var(--gold);}
.tip-type.water{color:var(--accent);}
.tip-type.general{color:var(--green);}
.tip-text{font-size:13px;color:var(--muted);line-height:1.6;}
.empty-tips{color:var(--muted);font-size:13px;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#012458;border:1px solid var(--border-hi);border-radius:18px;padding:32px;width:360px;animation:fadeup .3s ease;}
.modal h3{font-family:var(--font-d);font-size:20px;margin-bottom:16px;}
.modal-tip-item{background:rgba(255,255,255,0.05);border-radius:8px;padding:12px;font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:8px;}
.modal-close{margin-top:12px;width:100%;padding:10px;background:var(--accent);color:#fff;border:none;border-radius:var(--r-sm);cursor:pointer;font-weight:600;font-family:var(--font-b);}
</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sb-logo">in<span>S</span>ight</div>
    <nav class="sb-nav">
      <a href="dashboard.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Home
      </a>
      <a href="bills.php" class="sb-item">
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
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Welcome back, <?= htmlspecialchars(explode(' ', $userName)[0]) ?>!</div>

      <!-- STAT CARDS -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-label">Total Spent (This Year)</div>
          <div class="stat-val">SAR <?= number_format($totals['grandTotal'], 2) ?></div>
          <div class="stat-sub"><?= $totals['billCount'] ?> bills recorded</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Electricity</div>
          <div class="stat-val" style="color:var(--gold);">SAR <?= number_format($totals['totalElec'], 2) ?></div>
          <div class="stat-sub">This year</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Water</div>
          <div class="stat-val" style="color:var(--accent);">SAR <?= number_format($totals['totalWater'], 2) ?></div>
          <div class="stat-sub">This year</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Monthly Budget</div>
          <div class="stat-val" style="color:var(--green);">SAR <?= number_format($budget, 2) ?></div>
          <div class="stat-sub">Set in profile</div>
        </div>
      </div>

      <!-- CHARTS -->
      <div class="charts-row">
        <div class="card" style="margin-bottom:0;">
          <div class="card-title">Monthly Spending Trend</div>
          <canvas id="lineChart" height="110"></canvas>
        </div>
        <div class="card" style="margin-bottom:0;">
          <div class="card-title">Electricity vs Water</div>
          <canvas id="doughnutChart" height="150"></canvas>
          <div style="margin-top:12px;display:flex;gap:16px;justify-content:center;font-size:12px;color:var(--muted);">
            <span>⚡ SAR <?= number_format($totals['totalElec'], 2) ?></span>
            <span>💧 SAR <?= number_format($totals['totalWater'], 2) ?></span>
          </div>
        </div>
      </div>
      <br>

      <!-- MONTHLY OVERVIEW -->
      <div class="card">
        <div class="card-title">Monthly Overview</div>
        <?php if (empty($allMonths)): ?>
          <div style="color:var(--muted);font-size:13px;">No bills yet. <a href="bills.php" style="color:var(--accent);">Add your first bill →</a></div>
        <?php else: ?>
          <div class="month-grid">
            <?php foreach ($allMonths as $m):
              $over = $m['total'] > $budget;
              $pct  = $maxTotal > 0 ? min(($m['total'] / $maxTotal) * 100, 100) : 0;
              $tips_encoded = htmlspecialchars(json_encode([
                $m['elecMeter'] > 450 ? 'High electricity! Service your AC and check standby devices.' :
                ($m['elecMeter'] > 0 ? 'Electricity is reasonable. Try setting AC to 24°C to save more.' : ''),
                $m['waterMeter'] > 30 ? 'High water usage! Check for hidden leaks in pipes and toilets.' :
                ($m['waterMeter'] > 0 ? 'Water usage looks healthy. Fix dripping taps to save more.' : ''),
                'Run appliances only when fully loaded to save up to 30%.',
              ]));
            ?>
            <div class="month-row <?= $over ? 'over-budget' : '' ?>">
              <div class="month-name"><?= htmlspecialchars($m['label']) ?></div>
              <div class="month-bar-wrap">
                <div class="month-bar <?= $over ? 'over' : '' ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <div class="month-cost" style="color:<?= $over ? 'var(--red)' : 'var(--text)' ?>">
                SAR <?= number_format($m['total'], 2) ?>
              </div>
              <?php if ($over): ?>
                <span style="cursor:pointer;font-size:14px;" onclick="showTipsModal('<?= htmlspecialchars($m['label']) ?>', <?= $tips_encoded ?>)">⚠️</span>
              <?php else: ?>
                <span style="font-size:14px;">✅</span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- SAVING TIPS -->
      <div class="card">
        <div class="card-title">💡 Saving Tips</div>
        <?php if (empty($tips)): ?>
          <div class="empty-tips">Add bills to get personalized saving tips.</div>
        <?php else: ?>
          <div class="tips-list">
            <?php foreach ($tips as $tip):
              $icon = $tip['type']==='electricity' ? '⚡' : ($tip['type']==='water' ? '💧' : '💡');
            ?>
            <div class="tip-card">
              <div class="tip-icon"><?= $icon ?></div>
              <div>
                <div class="tip-type <?= $tip['type'] ?>"><?= $tip['type'] ?></div>
                <div class="tip-text"><?= htmlspecialchars($tip['content']) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Tips Modal -->
<div class="modal-overlay" id="tips-modal">
  <div class="modal">
    <h3 id="modal-title">💡 Saving Tips</h3>
    <div id="modal-body"></div>
    <button class="modal-close" onclick="document.getElementById('tips-modal').classList.remove('open')">Close</button>
  </div>
</div>

<script>
// Charts
const labels = <?= $chartLabels ?>;
const elecData  = <?= $chartElec ?>;
const waterData = <?= $chartWater ?>;

new Chart(document.getElementById('lineChart').getContext('2d'), {
  type:'line',
  data:{labels,datasets:[
    {label:'Electricity',data:elecData,borderColor:'#f4c94b',backgroundColor:'rgba(244,201,75,0.1)',tension:.4,fill:true,pointRadius:4},
    {label:'Water',data:waterData,borderColor:'#4a9fff',backgroundColor:'rgba(74,159,255,0.1)',tension:.4,fill:true,pointRadius:4},
  ]},
  options:{responsive:true,plugins:{legend:{labels:{color:'rgba(232,240,254,0.7)',font:{size:11}}}},
    scales:{x:{ticks:{color:'rgba(232,240,254,0.5)',font:{size:11}},grid:{color:'rgba(255,255,255,0.05)'}},
            y:{ticks:{color:'rgba(232,240,254,0.5)',font:{size:11},callback:v=>'SAR '+v},grid:{color:'rgba(255,255,255,0.05)'}}}}
});

const totalElec  = <?= (float)$totals['totalElec'] ?>;
const totalWater = <?= (float)$totals['totalWater'] ?>;
new Chart(document.getElementById('doughnutChart').getContext('2d'), {
  type:'doughnut',
  data:{labels:['Electricity','Water'],datasets:[{data:[totalElec,totalWater],backgroundColor:['#f4c94b','#4a9fff'],borderWidth:0}]},
  options:{responsive:true,cutout:'70%',plugins:{legend:{display:false}}}
});

function showTipsModal(label, tips){
  document.getElementById('modal-title').textContent = '💡 Tips for ' + label;
  document.getElementById('modal-body').innerHTML =
    tips.filter(t=>t).map(t=>`<div class="modal-tip-item">${t}</div>`).join('');
  document.getElementById('tips-modal').classList.add('open');
}

document.getElementById('tips-modal').addEventListener('click', e=>{
  if(e.target===document.getElementById('tips-modal'))
    document.getElementById('tips-modal').classList.remove('open');
});
</script>
</body>
</html>

<?php
require __DIR__ . '/includes/auth.php';
requireTataDthAccess();
require_once __DIR__ . '/config/db.php';

// Same quota-badge pattern as rc_print.php/hp_gas.php.
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$quota = null;
if (!$isAdmin) {
    $stmt = $pdo->prepare('SELECT tata_dth_monthly_limit FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $monthlyLimit = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM search_logs WHERE user_id = :id AND search_type = 'tata_dth' AND searched_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $usedThisMonth = (int) $stmt->fetchColumn();

    $quota = ['used' => $usedThisMonth, 'limit' => $monthlyLimit];
}

$basePath = '';
require __DIR__ . '/includes/header.php';
?>

<!-- Confetti overlay - populated/cleared by startConfetti()/stopConfetti()
     (assets/confetti.js), only while a search has actually succeeded. -->
<div class="confetti-container" id="confetti-container"></div>

<div class="page-header" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px">
  <h1 class="page-title" style="margin:0"><i class="bi bi-router-fill"></i> Tata Sky DTH Search</h1>
  <?php if ($isAdmin): ?>
    <span id="tdQuotaBadge" class="badge badge-neutral" style="margin-left:auto">Unlimited (Admin)</span>
  <?php elseif ($quota !== null): ?>
    <span id="tdQuotaBadge" class="badge <?= $quota['used'] >= $quota['limit'] ? 'badge-danger' : 'badge-neutral' ?>"
          style="margin-left:auto">
      <?= $quota['limit'] - $quota['used'] > 0 ? $quota['limit'] - $quota['used'] : 0 ?> of <?= $quota['limit'] ?> left this month
    </span>
  <?php endif; ?>
</div>

<style>
  /* Same tokens/shape as rc_print.php/hp_gas.php's cards. */
  .td-card{background:var(--c-surface,#fff);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;}
  .td-card-body{padding:16px 18px;}
  .td-row{display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap;}
  .td-btn{padding:11px 26px;border-radius:9px;border:none;background:#4f46e5;color:#fff;
    font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;
    transition:all 150ms;box-shadow:0 4px 18px rgba(79,70,229,.3);}
  .td-btn:hover:not(:disabled){background:#4338ca;transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.45);}
  .td-btn:disabled{opacity:.65;cursor:wait;transform:none;}
  .td-btn-secondary{background:#fff;color:#333;border:1px solid #e0e0e0;box-shadow:none;}
  .td-btn-secondary:hover:not(:disabled){background:#eeeef6;border-color:#4f46e5;transform:none;box-shadow:none;}
  #tdStatus{font-size:12.5px;color:#555;white-space:pre-wrap;word-break:break-word;font-weight:500;}
  .td-progress-wrap{margin-top:12px;display:none;}
  .td-progress-track{height:8px;border-radius:6px;background:#eeeef6;overflow:hidden;border:1px solid #e0e0e0;}
  .td-progress-fill{height:100%;border-radius:6px;background:#4f46e5;width:100%;
    background-image:repeating-linear-gradient(45deg,#4f46e5 0 12px,#4338ca 12px 24px);
    background-size:34px 100%;animation:td-progress-stripes 1s linear infinite;}
  @keyframes td-progress-stripes{from{background-position:0 0;}to{background-position:-34px 0;}}
  .td-progress-meta{display:flex;justify-content:space-between;margin-top:6px;font-size:11.5px;color:#999;}
  .td-result-wrap{margin-top:16px;display:none;}
  .td-section{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;margin-bottom:14px;}
  .td-section-title{padding:10px 16px;background:#4f46e5;color:#fff;font-size:11.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.4px;}
  .td-section-table{width:100%;border-collapse:collapse;}
  .td-section-table tr:nth-child(odd){background:#fff;}
  .td-section-table tr:nth-child(even){background:#f8f8fc;}
  .td-section-table td{padding:8px 16px;font-size:12.5px;border-bottom:1px solid #eee;vertical-align:top;}
  .td-section-table tr:last-child td{border-bottom:none;}
  .td-field-label{width:38%;color:#777;font-weight:600;}
  .td-field-value{color:#222;font-weight:500;word-break:break-word;}
  .td-not-found{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;color:#f87171;font-weight:600;}
</style>

<div class="td-card">
  <div class="td-card-body">
    <input type="text" id="tdNumberBox" placeholder="9876543210" maxlength="10"
           style="width:100%;padding:11px 16px;font-size:13px;color:#333;border:1px solid #e0e0e0;border-radius:9px;background:#fff;outline:none;">
    <div class="td-row">
      <button id="tdSearchBtn" class="td-btn">Search</button>
      <button id="tdClearBtn" class="td-btn td-btn-secondary" type="button">Clear</button>
      <span id="tdStatus"></span>
    </div>
    <div class="td-progress-wrap" id="tdProgressWrap">
      <div class="td-progress-track"><div class="td-progress-fill" id="tdProgressFill"></div></div>
      <div class="td-progress-meta">
        <span id="tdProgressLabel">Logging in and running the search…</span>
        <span id="tdProgressElapsed"></span>
      </div>
    </div>
  </div>
</div>

<div class="td-result-wrap" id="tdResultWrap"></div>

<script>
const searchBtn      = document.getElementById("tdSearchBtn");
const clearBtn       = document.getElementById("tdClearBtn");
const numberBox      = document.getElementById("tdNumberBox");
const statusEl       = document.getElementById("tdStatus");
const resultWrap     = document.getElementById("tdResultWrap");
const quotaBadge     = document.getElementById("tdQuotaBadge");
const progressWrap   = document.getElementById("tdProgressWrap");
const progressLabel  = document.getElementById("tdProgressLabel");
const progressElapsed= document.getElementById("tdProgressElapsed");

let progressTimer = null;
let searchStartedAt = null;

function formatDuration(seconds) {
  seconds = Math.max(0, Math.round(seconds));
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

// No per-step progress to report (unlike LPG's job-based polling) - this is
// one blocking fetch for the whole SSO-login+Siebel-navigation+scrape
// sequence in Gas/lpg_web/tata_dth.py, so the bar itself is always
// indeterminate (striped, animating). The countdown is a fixed estimate
// (typical observed run: ~20-30s), not anything server-reported.
const ESTIMATED_SECONDS = 30;

function startProgress() {
  searchStartedAt = Date.now();
  progressLabel.textContent = "Logging in and running the search…";
  progressElapsed.textContent = `~${formatDuration(ESTIMATED_SECONDS)} estimated`;
  progressWrap.style.display = "block";
  clearInterval(progressTimer);
  progressTimer = setInterval(() => {
    const remaining = ESTIMATED_SECONDS - (Date.now() - searchStartedAt) / 1000;
    progressElapsed.textContent = remaining > 0
      ? `~${formatDuration(remaining)} remaining`
      : "Finishing up…";
  }, 1000);
}

function stopProgress(finalLabel) {
  clearInterval(progressTimer);
  if (finalLabel && searchStartedAt) {
    progressLabel.textContent = finalLabel;
    progressElapsed.textContent = `Done in ${formatDuration((Date.now() - searchStartedAt) / 1000)}`;
  } else {
    progressWrap.style.display = "none";
  }
}

function updateQuotaBadge(used, limit) {
  if (!quotaBadge) return;
  const remaining = Math.max(0, limit - used);
  quotaBadge.textContent = `${remaining} of ${limit} left this month`;
  quotaBadge.classList.toggle("badge-danger", used >= limit);
  quotaBadge.classList.toggle("badge-neutral", used < limit);
}

// Sections come from tata_dth.py scraping Siebel PRM's own account record
// generically (label/value pairs grouped under section headers like
// "Subscriber Details", "Address", "Digicard (<number>)") - rendered as-is
// here rather than assuming fixed field names.
function renderResult(data) {
  resultWrap.innerHTML = "";

  if (!data.found) {
    resultWrap.innerHTML = `<div class="td-not-found">Not found for ${data.mobileNumber}.</div>`;
  } else if (Array.isArray(data.sections) && data.sections.length) {
    data.sections.forEach(section => {
      const box = document.createElement("div");
      box.className = "td-section";
      const title = document.createElement("div");
      title.className = "td-section-title";
      title.textContent = section.title;
      box.appendChild(title);

      const table = document.createElement("table");
      table.className = "td-section-table";
      const tbody = document.createElement("tbody");
      section.fields.forEach(field => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td class="td-field-label"></td><td class="td-field-value"></td>`;
        tr.querySelector(".td-field-label").textContent = field.label;
        tr.querySelector(".td-field-value").textContent = field.value || "—";
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);
      box.appendChild(table);
      resultWrap.appendChild(box);
    });
  } else {
    // Fallback if Siebel's DOM structure ever changes and tata_dth.py
    // couldn't extract labeled sections - see tata_dth.py.
    const box = document.createElement("div");
    box.className = "td-section";
    box.innerHTML = `<div class="td-section-title">Result for ${data.mobileNumber}</div>
      <div style="padding:16px;font-family:monospace;font-size:12px;white-space:pre-wrap;word-break:break-word;"></div>`;
    box.querySelector("div:last-child").textContent = data.rawText || "(no details captured)";
    resultWrap.appendChild(box);
  }

  resultWrap.style.display = "block";
  if (data.found) startConfetti(); else stopConfetti();
  if (typeof data.used === "number" && typeof data.limit === "number") {
    updateQuotaBadge(data.used, data.limit);
  }
}

async function runSearch() {
  const mobileNumber = numberBox.value.replace(/\D/g, "");
  if (mobileNumber.length !== 10) {
    statusEl.textContent = "Enter a valid 10-digit mobile number.";
    return;
  }

  searchBtn.disabled = true;
  statusEl.textContent = "";
  resultWrap.style.display = "none";
  startProgress();

  try {
    const res = await fetch("tata_dth_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mobileNumber })
    });
    const data = await res.json();

    if (!res.ok) {
      stopProgress(null);
      statusEl.textContent = `Error: ${data.error || "could not complete search"}`;
      if (typeof data.used === "number" && typeof data.limit === "number") {
        updateQuotaBadge(data.used, data.limit);
      }
      return;
    }

    stopProgress(data.found ? "Result found" : "No result found");
    renderResult(data);
  } catch (err) {
    stopProgress(null);
    statusEl.textContent = `Could not reach the server: ${err.message}`;
  } finally {
    searchBtn.disabled = false;
  }
}

searchBtn.addEventListener("click", runSearch);
numberBox.addEventListener("keydown", (e) => {
  if (e.key === "Enter" && !searchBtn.disabled) runSearch();
});
clearBtn.addEventListener("click", () => {
  numberBox.value = "";
  statusEl.textContent = "";
  resultWrap.style.display = "none";
  stopProgress(null);
  stopConfetti();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

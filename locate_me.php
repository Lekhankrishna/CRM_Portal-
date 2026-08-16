<?php
require __DIR__ . '/includes/auth.php';
requireLocateMeAccess(); // requireLogin() + a 403 for logged-in users without the "Locate Me Access" permission (Admin > Agents)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/locateme_tools.php';

// Same quota-badge pattern as hp_gas.php/rc_print.php.
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$quota = null;
if (!$isAdmin) {
    $stmt = $pdo->prepare('SELECT locate_me_monthly_limit FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $monthlyLimit = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM search_logs WHERE user_id = :id AND search_type = 'locate_me' AND searched_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
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
  <h1 class="page-title" style="margin:0"><i class="bi bi-geo-alt-fill"></i> Locate Me</h1>
  <?php if ($isAdmin): ?>
    <span id="lmQuotaBadge" class="badge badge-neutral" style="margin-left:auto">Unlimited (Admin)</span>
  <?php elseif ($quota !== null): ?>
    <span id="lmQuotaBadge" class="badge <?= $quota['used'] >= $quota['limit'] ? 'badge-danger' : 'badge-neutral' ?>"
          style="margin-left:auto">
      <?= $quota['limit'] - $quota['used'] > 0 ? $quota['limit'] - $quota['used'] : 0 ?> of <?= $quota['limit'] ?> left this month
    </span>
  <?php endif; ?>
</div>

<style>
  /* Same tokens/shape as hp_gas.php/rc_print.php's cards. */
  .lm-card{background:var(--c-surface,#fff);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;}
  .lm-card-body{padding:16px 18px;}
  .lm-hint{color:#999;margin:0 0 14px;font-size:13px;}
  .lm-row{display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap;}
  .lm-btn{padding:11px 26px;border-radius:9px;border:none;background:#4f46e5;color:#fff;
    font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;
    transition:all 150ms;box-shadow:0 4px 18px rgba(79,70,229,.3);}
  .lm-btn:hover:not(:disabled){background:#4338ca;transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.45);}
  .lm-btn:disabled{opacity:.65;cursor:wait;transform:none;}
  .lm-btn-secondary{background:#fff;color:#333;border:1px solid #e0e0e0;box-shadow:none;}
  .lm-btn-secondary:hover:not(:disabled){background:#eeeef6;border-color:#4f46e5;transform:none;box-shadow:none;}
  #lmStatus{font-size:12.5px;color:#555;white-space:pre-wrap;word-break:break-word;font-weight:500;}
  .lm-progress-wrap{margin-top:12px;display:none;}
  .lm-progress-track{height:8px;border-radius:6px;background:#eeeef6;overflow:hidden;border:1px solid #e0e0e0;}
  .lm-progress-fill{height:100%;border-radius:6px;background:#4f46e5;width:100%;
    background-image:repeating-linear-gradient(45deg,#4f46e5 0 12px,#4338ca 12px 24px);
    background-size:34px 100%;animation:lm-progress-stripes 1s linear infinite;}
  @keyframes lm-progress-stripes{from{background-position:0 0;}to{background-position:-34px 0;}}
  .lm-progress-meta{display:flex;justify-content:space-between;margin-top:6px;font-size:11.5px;color:#999;}
  /* Results - one card per record/section (name+icon header, optional
     status badge, then a 2-column grid of icon+label+value fields) -
     same general shape as locateme.services' own result page, light
     card tokens matching hp_gas.php/rc_print.php's cards elsewhere in
     this app rather than that site's dark theme. */
  .lm-result-wrap{margin-top:16px;display:none;}
  .lm-result-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
  .lm-result-count{font-size:12px;color:#777;font-weight:600;}
  .lm-export-btn{background:#10b981;color:#fff;border:none;box-shadow:0 4px 18px rgba(16,185,129,.3);padding:9px 18px;
    border-radius:9px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;
    display:inline-flex;align-items:center;gap:8px;transition:all 150ms;}
  .lm-export-btn:hover:not(:disabled){background:#0d9668;transform:translateY(-1px);box-shadow:0 6px 20px rgba(16,185,129,.45);}
  .lm-export-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
  .lm-record{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;margin-bottom:14px;}
  .lm-record-header{padding:12px 16px;background:#4f46e5;color:#fff;display:flex;align-items:center;gap:10px;}
  .lm-record-header i{font-size:16px;}
  .lm-record-name{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
  .lm-record-status{margin-left:auto;font-size:10.5px;padding:2px 10px;border-radius:999px;
    font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:rgba(255,255,255,.2);}
  .lm-field-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;padding:16px;}
  .lm-field-item{display:flex;align-items:flex-start;gap:10px;}
  .lm-field-item i{font-size:15px;color:#4f46e5;margin-top:2px;flex-shrink:0;}
  .lm-field-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#999;margin-bottom:2px;}
  .lm-field-value{font-size:13px;font-weight:600;color:#222;word-break:break-word;}
  .lm-raw-body{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;
    font-family:monospace;font-size:12px;white-space:pre-wrap;word-break:break-word;color:#333;margin-top:16px;display:none;}
  .lm-no-results{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;
    padding:56px 16px;color:#999;margin-top:16px;}
  .lm-no-results i{font-size:38px;color:#ccc;width:74px;height:74px;display:flex;align-items:center;justify-content:center;
    border-radius:50%;border:1.5px solid #e5e5e5;}
  .lm-no-results span{font-size:14px;color:#888;}
  /* Tool picker - same tab-pill look as advanced_search.php's .as-tabs/.as-tab,
     just renamed with this page's own lm- prefix. Wraps onto multiple lines
     for Locate Me's 24 tools instead of advanced_search.php's 5 modes, same
     as that page's row already supports via flex-wrap. */
  .lm-tabs{display:flex;gap:10px;flex-wrap:wrap;padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #eee;}
  .lm-tab{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:10px;
    border:1px solid #e2e2ea;background:#fff;font-size:12.5px;font-weight:600;color:#555;
    cursor:pointer;transition:all 150ms;white-space:nowrap;}
  .lm-tab:hover{border-color:#4f46e5;color:#4f46e5;}
  .lm-tab.active{background:#4f46e5;border-color:#4f46e5;color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.35);}
</style>

<div class="lm-card">
  <div class="lm-card-body">
    <p class="lm-hint">Pick a tool, then enter the matching value to search locateme.services' database.</p>
    <div class="lm-tabs" id="lmToolTabs" role="tablist">
      <?php foreach (LOCATEME_TOOLS as $slug => $tool): ?>
        <button type="button" class="lm-tab<?= $slug === 'mobile-info' ? ' active' : '' ?>"
                data-tool="<?= htmlspecialchars($slug) ?>" data-placeholder="<?= htmlspecialchars($tool['placeholder']) ?>">
          <?= htmlspecialchars($tool['label']) ?>
        </button>
      <?php endforeach; ?>
    </div>
    <input type="text" id="lmQueryBox" placeholder="Enter Mobile Number" maxlength="100"
           style="width:100%;padding:11px 16px;font-size:13px;color:#333;border:1px solid #e0e0e0;border-radius:9px;background:#fff;outline:none;">
    <div class="lm-row">
      <button id="lmSearchBtn" class="lm-btn">Search</button>
      <button id="lmClearBtn" class="lm-btn lm-btn-secondary" type="button">Clear</button>
      <span id="lmStatus"></span>
    </div>
    <div class="lm-progress-wrap" id="lmProgressWrap">
      <div class="lm-progress-track"><div class="lm-progress-fill" id="lmProgressFill"></div></div>
      <div class="lm-progress-meta">
        <span id="lmProgressLabel">Logging in and running the search…</span>
        <span id="lmProgressElapsed"></span>
      </div>
    </div>
  </div>
</div>

<div class="lm-result-wrap" id="lmResultWrap">
  <div class="lm-result-toolbar">
    <span class="lm-result-count" id="lmResultCountText"></span>
    <button id="lmExportBtn" class="lm-export-btn" type="button" disabled>
      <i class="bi bi-file-earmark-excel"></i> Export as Excel (CSV)
    </button>
  </div>
  <div id="lmRecordsWrap"></div>
</div>
<div class="lm-raw-body" id="lmRawBody"></div>
<div class="lm-no-results" id="lmNoResults" style="display:none">
  <i class="bi bi-search"></i>
  <span>No records found</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const searchBtn      = document.getElementById("lmSearchBtn");
const clearBtn       = document.getElementById("lmClearBtn");
const toolTabs       = document.getElementById("lmToolTabs");
const queryBox       = document.getElementById("lmQueryBox");
const statusEl       = document.getElementById("lmStatus");
const resultWrap     = document.getElementById("lmResultWrap");
const resultCountText= document.getElementById("lmResultCountText");
const exportBtn      = document.getElementById("lmExportBtn");
const recordsWrap    = document.getElementById("lmRecordsWrap");
const rawBody        = document.getElementById("lmRawBody");
const noResultsEl    = document.getElementById("lmNoResults");
const quotaBadge     = document.getElementById("lmQuotaBadge");
const progressWrap   = document.getElementById("lmProgressWrap");
const progressLabel  = document.getElementById("lmProgressLabel");
const progressElapsed= document.getElementById("lmProgressElapsed");

let progressTimer = null;
let searchStartedAt = null;

function formatDuration(seconds) {
  seconds = Math.max(0, Math.round(seconds));
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

// Tab picker - same active-tab pattern as advanced_search.php's .as-tabs.
// Placeholder swaps to match whatever the selected tool actually expects
// (mobile number, Aadhaar number, vehicle number, email, IFSC code, ...) -
// see includes/locateme_tools.php for the full list, mirrored server-side
// in Gas/lpg_web/locate_tools.py's TOOL_REGISTRY.
let activeTool = "mobile-info";
toolTabs.querySelectorAll(".lm-tab").forEach(tab => tab.addEventListener("click", () => {
  toolTabs.querySelectorAll(".lm-tab").forEach(t => t.classList.remove("active"));
  tab.classList.add("active");
  activeTool = tab.dataset.tool;
  queryBox.placeholder = tab.dataset.placeholder;
  statusEl.textContent = "";
}));

// There's no per-step progress to report here (unlike LPG's job-based
// polling) - this is one blocking fetch for the whole login+search+scrape
// sequence in Gas/lpg_web/locate_tools.py, so the bar itself is always
// indeterminate (striped, animating). The countdown is a fixed estimate,
// same idea as hp_gas.php's own "~Xs remaining".
const ESTIMATED_SECONDS = 20;

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

// Best-guess icon per field label - not a literal match to whatever icon
// locateme.services itself shows (that varies per tool/field and isn't
// worth hand-mapping across 24 tools), just a reasonable visual cue based
// on common keywords so the field grid doesn't read as a flat wall of text.
function fieldIcon(label) {
  const l = label.toLowerCase();
  if (/(phone|mobile|node|number)/.test(l)) return "bi-telephone-fill";
  if (/(address|location|city|state|pincode|circle)/.test(l)) return "bi-geo-alt-fill";
  if (/name/.test(l)) return "bi-person-fill";
  if (/(bank|account|ifsc)/.test(l)) return "bi-bank";
  if (/(email|mail)/.test(l)) return "bi-envelope-fill";
  if (/(aadhaar|pan|id|linkage|imei)/.test(l)) return "bi-credit-card-2-front-fill";
  if (/(valid|verif|status|merchant)/.test(l)) return "bi-shield-check";
  if (/(vpa|upi|credit)/.test(l)) return "bi-wallet2";
  return "bi-info-circle-fill";
}

// Records come from Gas/lpg_web/locate_tools.py scraping locateme.services'
// own result cards generically (label/value field pairs under a
// name+status header, or under a section title - see locate_tools.py) -
// rendered as-is here rather than assuming fixed field names, since
// whatever fields locateme.services shows for a given query is what gets
// displayed. Some queries (e.g. a mobile number that's changed hands/been
// ported, or a "lookup" tool with several grouped sections) return more
// than one record - each gets its own card.
function buildRecordCard(record) {
  const box = document.createElement("div");
  box.className = "lm-record";

  const header = document.createElement("div");
  header.className = "lm-record-header";
  const headerIcon = record.status ? "bi-person-circle" : "bi-folder2-open";
  header.innerHTML = `<i class="bi ${headerIcon}"></i><span class="lm-record-name"></span><span class="lm-record-status"></span>`;
  header.querySelector(".lm-record-name").textContent = record.name || "Record";
  const statusBadge = header.querySelector(".lm-record-status");
  if (record.status) { statusBadge.textContent = record.status; } else { statusBadge.remove(); }
  box.appendChild(header);

  const grid = document.createElement("div");
  grid.className = "lm-field-grid";
  (record.fields || []).forEach(field => {
    const item = document.createElement("div");
    item.className = "lm-field-item";
    item.innerHTML = `<i class="bi"></i><div><div class="lm-field-label"></div><div class="lm-field-value"></div></div>`;
    item.querySelector("i").classList.add(fieldIcon(field.label));
    item.querySelector(".lm-field-label").textContent = field.label;
    item.querySelector(".lm-field-value").textContent = field.value || "—";
    grid.appendChild(item);
  });
  box.appendChild(grid);
  return box;
}

let lastRecords = [];

function renderResult(data) {
  recordsWrap.innerHTML = "";
  resultWrap.style.display = "none";
  rawBody.style.display = "none";
  noResultsEl.style.display = "none";
  exportBtn.disabled = true;
  lastRecords = [];

  const records = (data.found && Array.isArray(data.records)) ? data.records : [];

  if (records.length) {
    lastRecords = records;
    resultCountText.textContent = `${records.length} result${records.length === 1 ? "" : "s"} found`;
    exportBtn.disabled = false;
    records.forEach(r => recordsWrap.appendChild(buildRecordCard(r)));
    resultWrap.style.display = "block";
  } else if (data.found && data.rawText) {
    // Fallback if locateme.services' DOM structure matches neither
    // extraction pattern for this particular tool (e.g. whatsapp-dp, which
    // returns an image rather than label/value fields) - see
    // locate_tools.py's module docstring.
    rawBody.textContent = data.rawText;
    rawBody.style.display = "block";
  } else {
    noResultsEl.style.display = "flex";
  }

  if (records.length) startConfetti(); else stopConfetti();
  if (typeof data.used === "number" && typeof data.limit === "number") {
    updateQuotaBadge(data.used, data.limit);
  }
}

// Columns are fully dynamic (whatever fields locateme.services showed for
// each record - see buildRecordCard), built as the union of every label
// seen in first-seen order, same approach as hp_gas.php's bulk-search CSV
// export.
exportBtn.addEventListener("click", () => {
  if (!lastRecords.length) return;
  const hasName   = lastRecords.some(r => r.name);
  const hasStatus = lastRecords.some(r => r.status);
  const columns = [];
  if (hasName) columns.push("Record");
  if (hasStatus) columns.push("Status");
  const columnSet = new Set(columns);
  lastRecords.forEach(r => (r.fields || []).forEach(f => {
    if (!columnSet.has(f.label)) { columnSet.add(f.label); columns.push(f.label); }
  }));

  const aoa = [columns, ...lastRecords.map(r => {
    const valueMap = {};
    if (hasName) valueMap["Record"] = r.name || "";
    if (hasStatus) valueMap["Status"] = r.status || "";
    (r.fields || []).forEach(f => { valueMap[f.label] = f.value || ""; });
    return columns.map(c => valueMap[c] || "");
  })];
  const ws = XLSX.utils.aoa_to_sheet(aoa);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Result");
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  XLSX.writeFile(wb, `locate-me-${activeTool}-${stamp}.xlsx`);
});

async function runSearch() {
  const tool = activeTool;
  const query = queryBox.value.trim();
  if (!query) {
    statusEl.textContent = "Enter a value to search.";
    return;
  }

  searchBtn.disabled = true;
  statusEl.textContent = "";
  resultWrap.style.display = "none";
  rawBody.style.display = "none";
  noResultsEl.style.display = "none";
  startProgress();

  try {
    const res = await fetch("locate_me_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ tool, query })
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
queryBox.addEventListener("keydown", (e) => {
  if (e.key === "Enter" && !searchBtn.disabled) runSearch();
});
clearBtn.addEventListener("click", () => {
  queryBox.value = "";
  statusEl.textContent = "";
  resultWrap.style.display = "none";
  rawBody.style.display = "none";
  noResultsEl.style.display = "none";
  exportBtn.disabled = true;
  lastRecords = [];
  stopProgress(null);
  stopConfetti();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

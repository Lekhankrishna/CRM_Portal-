<?php
require __DIR__ . '/includes/auth.php';
requirePanIndiaProAccess();
require_once __DIR__ . '/config/db.php';

$basePath = '';
require __DIR__ . '/includes/header.php';
?>

<!-- Confetti overlay - populated/cleared by startConfetti()/stopConfetti()
     (assets/confetti.js), only while a search has actually succeeded. -->
<div class="confetti-container" id="confetti-container"></div>

<div class="page-header" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px">
  <h1 class="page-title" style="margin:0"><i class="bi bi-globe-asia-australia"></i> Night Out</h1>
  <span class="badge badge-neutral" style="margin-left:auto">Unlimited</span>
</div>

<style>
  /* Same tokens/shape as rc_print.php/hp_gas.php/advance_pan_india.php's
     cards - matches this app's usual white-card indigo tool styling rather
     than a one-off vendor-matched dark palette. */
  .pip-card{background:var(--c-surface,#fff);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;}
  .pip-card-body{padding:16px 18px;}
  .pip-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;}
  .pip-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#777;margin-bottom:4px;}
  .pip-field input{width:100%;padding:10px 12px;font-size:13px;color:#333;border:1px solid #e0e0e0;border-radius:8px;
    background:#fff;outline:none;transition:border-color 150ms,box-shadow 150ms;}
  .pip-field input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.15);}
  .pip-row{display:flex;align-items:center;gap:12px;margin-top:14px;flex-wrap:wrap;}
  .pip-btn{padding:11px 26px;border-radius:9px;border:none;background:#4f46e5;color:#fff;
    font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;
    transition:all 150ms;box-shadow:0 4px 18px rgba(79,70,229,.3);}
  .pip-btn:hover:not(:disabled){background:#4338ca;transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.45);}
  .pip-btn:disabled{opacity:.65;cursor:wait;transform:none;}
  .pip-btn-secondary{background:#fff;color:#333;border:1px solid #e0e0e0;box-shadow:none;}
  .pip-btn-secondary:hover:not(:disabled){background:#eeeef6;border-color:#4f46e5;transform:none;box-shadow:none;}
  .pip-btn-excel{background:#10b981;color:#fff;box-shadow:0 4px 18px rgba(16,185,129,.3);}
  .pip-btn-excel:hover:not(:disabled){background:#0d9668;transform:translateY(-1px);box-shadow:0 6px 20px rgba(16,185,129,.45);}
  #pipStatus{font-size:12.5px;color:#555;white-space:pre-wrap;word-break:break-word;font-weight:500;}
  .pip-progress-wrap{margin-top:12px;display:none;}
  .pip-progress-track{height:8px;border-radius:6px;background:#eeeef6;overflow:hidden;border:1px solid #e0e0e0;}
  .pip-progress-fill{height:100%;border-radius:6px;background:#4f46e5;width:100%;
    background-image:repeating-linear-gradient(45deg,#4f46e5 0 12px,#4338ca 12px 24px);
    background-size:34px 100%;animation:pip-progress-stripes 1s linear infinite;}
  @keyframes pip-progress-stripes{from{background-position:0 0;}to{background-position:-34px 0;}}
  .pip-progress-meta{display:flex;justify-content:space-between;margin-top:6px;font-size:11.5px;color:#999;}
  .pip-result-wrap{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-top:16px;overflow-x:auto;display:none;}
  .pip-result-toolbar{padding:10px 16px;background:#4f46e5;color:#fff;font-size:11.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.3px;}
  .pip-table{width:100%;border-collapse:collapse;font-size:11.5px;}
  .pip-table th{background:#eeeef6;color:#555;font-size:10px;font-weight:700;text-transform:uppercase;
    letter-spacing:.3px;padding:6px 8px;text-align:left;white-space:nowrap;border-bottom:1px solid #e0e0e0;}
  .pip-table td{padding:6px 8px;border-bottom:1px solid #eee;vertical-align:top;color:#333;max-width:260px;word-break:break-word;}
  .pip-table tr:nth-child(even) td{background:#f8f8fc;}
  .pip-no-results{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;color:#777;}

  /* Per-column colour coding (Name/Father's Name plain, Mobile=amber,
     Alt. Mobile=pink, Address=green, Alt. Address=teal, Email=orange,
     Reg. Year=cyan, Identity=blue) - same palette/nth-child convention and
     light-theme header+tint+left-border treatment as lpg_search.php's
     .lpg-table and pan_india.php's per-field highlighting, so this table
     reads as "one of this app's tables" instead of a one-off dark theme. */
  .pip-table th:nth-child(2){background:rgb(217,119,6);color:#fff;}
  .pip-table th:nth-child(3){background:rgb(219,39,119);color:#fff;}
  .pip-table th:nth-child(5){background:rgb(5,150,105);color:#fff;}
  .pip-table th:nth-child(6){background:rgb(13,148,136);color:#fff;}
  .pip-table th:nth-child(7){background:rgb(234,88,12);color:#fff;}
  .pip-table th:nth-child(8){background:rgb(2,132,199);color:#fff;}
  .pip-table th:nth-child(9){background:rgb(37,99,235);color:#fff;}
  .pip-table td:nth-child(2){background:rgba(217,119,6,.08);border-left:3px solid rgba(217,119,6,.5);}
  .pip-table td:nth-child(3){background:rgba(219,39,119,.08);border-left:3px solid rgba(219,39,119,.5);}
  .pip-table td:nth-child(5){background:rgba(5,150,105,.08);border-left:3px solid rgba(5,150,105,.5);}
  .pip-table td:nth-child(6){background:rgba(13,148,136,.08);border-left:3px solid rgba(13,148,136,.5);}
  .pip-table td:nth-child(7){background:rgba(234,88,12,.08);border-left:3px solid rgba(234,88,12,.5);}
  .pip-table td:nth-child(8){background:rgba(2,132,199,.08);border-left:3px solid rgba(2,132,199,.5);}
  .pip-table td:nth-child(9){background:rgba(37,99,235,.08);border-left:3px solid rgba(37,99,235,.5);}
  .pip-cell-badge{font-family:'Consolas','Cascadia Code','Courier New',monospace;font-size:11.5px;font-weight:700;
    padding:1px 7px;border-radius:5px;display:inline-block;letter-spacing:.2px;
    background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.3);color:#4338ca;}

  /* Bulk Search (mobile numbers) - same tab/textarea/progress pattern as
     hp_gas.php/advance_pan_india.php. */
  .pip-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
  .pip-tab{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:999px;
    border:1px solid #e2e2ea;background:#fff;font-size:12.5px;font-weight:600;color:#555;
    cursor:pointer;transition:all 150ms;white-space:nowrap;}
  .pip-tab i{font-size:14px;}
  .pip-tab:hover{border-color:#4f46e5;color:#4f46e5;}
  .pip-tab.active{background:#4f46e5;border-color:#4f46e5;color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.35);}
  .pip-textarea{width:100%;height:110px;padding:9px 14px;font-size:13px;color:#333;
    border:1px solid #e0e0e0;border-radius:9px;background:#fff;resize:vertical;outline:none;}
  .pip-textarea:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.25);}
  .pip-progress-fill.determinate{background-image:none;animation:none;background:#4f46e5;
    width:0%;transition:width .3s ease;}
  .pip-bulk-item{margin-bottom:16px;}
  .pip-bulk-item-header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#eeeef6;
    border-radius:10px 10px 0 0;font-size:12.5px;font-weight:700;color:#333;
    border:1px solid #e0e0e0;border-bottom:none;}
  .pip-bulk-badge{margin-left:auto;font-size:10.5px;padding:2px 10px;border-radius:999px;
    font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:#e0e0ea;color:#555;}
  .pip-bulk-badge-found{background:rgba(16,185,129,.15);color:#0d9668;}
  .pip-bulk-badge-notfound,.pip-bulk-badge-error{background:rgba(248,113,113,.15);color:#dc2626;}
  .pip-bulk-item .pip-result-wrap{margin-top:0;border-radius:0;box-shadow:none;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 10px 10px;}
  .pip-bulk-item .pip-no-results{border-radius:0;box-shadow:none;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 10px 10px;}
</style>

<div class="pip-tabs">
  <button type="button" class="pip-tab active" id="pipTabSingle"><i class="bi bi-search"></i> Single Search</button>
  <button type="button" class="pip-tab" id="pipTabBulk"><i class="bi bi-list-ol"></i> Bulk Search</button>
</div>

<div id="pipSingleMode">
<div class="pip-card">
  <div class="pip-card-body">
    <div class="pip-grid">
      <div class="pip-field"><label>Mobile</label><input type="text" id="pipMobile" placeholder="Enter mobile number"></div>
      <div class="pip-field"><label>Name</label><input type="text" id="pipName" placeholder="Enter name"></div>
      <div class="pip-field"><label>Father's Name</label><input type="text" id="pipFname" placeholder="Enter father's name"></div>
      <div class="pip-field"><label>Email</label><input type="email" id="pipEmail" placeholder="Enter email"></div>
      <div class="pip-field"><label>Address</label><input type="text" id="pipAddress" placeholder="Enter address"></div>
      <div class="pip-field"><label>Identity</label><input type="text" id="pipMasterId" placeholder="Enter identity"></div>
    </div>
    <div class="pip-row">
      <button id="pipSearchBtn" class="pip-btn">Search</button>
      <button id="pipClearBtn" class="pip-btn pip-btn-secondary" type="button">Clear</button>
      <button id="pipExportBtn" class="pip-btn pip-btn-excel" type="button" disabled>
        <i class="bi bi-file-earmark-excel"></i> Download Excel
      </button>
      <span id="pipStatus"></span>
    </div>
    <div class="pip-progress-wrap" id="pipProgressWrap">
      <div class="pip-progress-track"><div class="pip-progress-fill" id="pipProgressFill"></div></div>
      <div class="pip-progress-meta">
        <span id="pipProgressLabel">Searching Night Out…</span>
        <span id="pipProgressElapsed"></span>
      </div>
    </div>
  </div>
</div>

<div class="pip-result-wrap" id="pipResultWrap">
  <div class="pip-result-toolbar" id="pipResultToolbar"></div>
  <table class="pip-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Mobile</th>
        <th>Alt. Mobile</th>
        <th>Father's Name</th>
        <th>Address</th>
        <th>Alt. Address</th>
        <th>Email</th>
        <th>Reg. Year</th>
        <th>Identity</th>
      </tr>
    </thead>
    <tbody id="pipResultBody"></tbody>
  </table>
</div>
</div>

<!-- Bulk Search: sequential, one mobile number at a time, over the same
     pan_india_pro_api.php single-search endpoint - same pattern as
     hp_gas.php's own Bulk Search. Available to every agent with Night Out
     access, same as Single Search (no separate toggle). Mobile numbers
     only (not the other fields) - matches how every other bulk tool here
     works. -->
<div id="pipBulkMode" style="display:none">
  <div class="pip-card">
    <div class="pip-card-body">
      <textarea id="pipBulkNumbersBox" class="pip-textarea" placeholder="9876543210, 9876543211, ..."></textarea>
      <div class="pip-row">
        <button id="pipBulkSearchBtn" class="pip-btn">Bulk Search</button>
        <button id="pipBulkClearBtn" class="pip-btn pip-btn-secondary" type="button">Clear</button>
        <button id="pipBulkExportBtn" class="pip-btn pip-btn-excel" type="button" disabled>
          <i class="bi bi-file-earmark-excel"></i> Export CSV
        </button>
        <span id="pipBulkStatus"></span>
      </div>
      <div class="pip-progress-wrap" id="pipBulkProgressWrap">
        <div class="pip-progress-track"><div class="pip-progress-fill determinate" id="pipBulkProgressFill"></div></div>
        <div class="pip-progress-meta">
          <span id="pipBulkProgressLabel"></span>
          <span id="pipBulkProgressEta"></span>
        </div>
      </div>
    </div>
  </div>
  <div id="pipBulkResultsWrap" style="margin-top:16px"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const searchBtn  = document.getElementById("pipSearchBtn");
const clearBtn   = document.getElementById("pipClearBtn");
const exportBtn  = document.getElementById("pipExportBtn");
const statusEl   = document.getElementById("pipStatus");
const resultWrap = document.getElementById("pipResultWrap");
const resultToolbar = document.getElementById("pipResultToolbar");
const resultBody = document.getElementById("pipResultBody");
let lastPipRows = [];
const progressWrap    = document.getElementById("pipProgressWrap");
const progressLabel   = document.getElementById("pipProgressLabel");
const progressElapsed = document.getElementById("pipProgressElapsed");

let progressTimer = null;
let searchStartedAt = null;

function formatDuration(seconds) {
  seconds = Math.max(0, Math.round(seconds));
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

// No per-step progress to report (unlike LPG's job-based polling) - this is
// one blocking fetch for the whole login(if needed)+search sequence in
// includes/pan_india_pro_client.php, so the bar itself is always
// indeterminate (striped, animating). The countdown is a fixed estimate
// (this is a plain JSON API, no browser automation, so typically just a
// couple seconds) - same idea as LPG's own "~Xs remaining", just without
// real done/total numbers to base it on.
const ESTIMATED_SECONDS = 5;

function startProgress() {
  searchStartedAt = Date.now();
  progressLabel.textContent = "Searching Night Out…";
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

const fields = {
  mobile: document.getElementById("pipMobile"),
  name: document.getElementById("pipName"),
  fname: document.getElementById("pipFname"),
  email: document.getElementById("pipEmail"),
  address: document.getElementById("pipAddress"),
  master_id: document.getElementById("pipMasterId"),
};

function cell(value) {
  return value ? String(value) : "—";
}

// Mobile/Alt. Mobile/Reg. Year render as small dark pill badges (see
// .pip-cell-badge) so they stand out on top of their column's own solid
// background colour (see .pip-table td:nth-child(N) above) - every other
// column just needs plain text, since the column colour already does the
// work the vendor's own table uses tinted text for.
function badgeCell(value) {
  const td = document.createElement("td");
  if (value) {
    const span = document.createElement("span");
    span.className = "pip-cell-badge";
    span.textContent = value;
    td.appendChild(span);
  } else {
    td.textContent = "—";
  }
  return td;
}

function textCell(value) {
  const td = document.createElement("td");
  td.textContent = cell(value);
  return td;
}

// Rows come from the vendor's own fixed JSON schema (id [called "Master ID"
// on the vendor's own site, shown here as "Identity"], name, mobile, alt,
// fname, address, alt_address, email, year_of_registration) - see
// includes/pan_india_pro_client.php - so this renders known columns
// directly instead of Advance Pan India's generic discovered-headers table.
function renderResult(data) {
  resultBody.innerHTML = "";
  lastPipRows = (data.totalResults && data.rows) ? data.rows : [];
  exportBtn.disabled = !lastPipRows.length;

  if (!lastPipRows.length) {
    resultWrap.style.display = "none";
  } else {
    resultToolbar.textContent = `${lastPipRows.length} result${lastPipRows.length === 1 ? "" : "s"}`;
    lastPipRows.forEach(row => {
      const tr = document.createElement("tr");
      tr.appendChild(textCell(row.name));
      tr.appendChild(badgeCell(row.mobile));
      tr.appendChild(badgeCell(row.alt));
      tr.appendChild(textCell(row.fname));
      tr.appendChild(textCell(row.address));
      tr.appendChild(textCell(row.alt_address));
      tr.appendChild(textCell(row.email));
      tr.appendChild(badgeCell(row.year_of_registration));
      tr.appendChild(textCell(row.id));
      resultBody.appendChild(tr);
    });
    resultWrap.style.display = "block";
  }

  if (lastPipRows.length) startConfetti(); else stopConfetti();
}

async function runSearch() {
  const params = {};
  let hasAny = false;
  for (const [key, input] of Object.entries(fields)) {
    params[key] = input.value.trim();
    if (params[key]) hasAny = true;
  }
  if (!hasAny) {
    statusEl.textContent = "Enter at least one search field.";
    return;
  }

  searchBtn.disabled = true;
  statusEl.textContent = "";
  resultWrap.style.display = "none";
  startProgress();

  try {
    const res = await fetch("pan_india_pro_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(params)
    });
    const data = await res.json();

    if (!res.ok) {
      stopProgress(null);
      statusEl.textContent = `Error: ${data.error || "could not complete search"}`;
      return;
    }

    stopProgress(data.totalResults ? `${data.totalResults} result${data.totalResults === 1 ? "" : "s"} found` : "No results found");
    renderResult(data);
  } catch (err) {
    stopProgress(null);
    statusEl.textContent = `Could not reach the server: ${err.message}`;
  } finally {
    searchBtn.disabled = false;
  }
}

searchBtn.addEventListener("click", runSearch);
clearBtn.addEventListener("click", () => {
  Object.values(fields).forEach(input => input.value = "");
  statusEl.textContent = "";
  resultWrap.style.display = "none";
  stopProgress(null);
  lastPipRows = [];
  exportBtn.disabled = true;
  stopConfetti();
});

/* Download the current result set as a single .xlsx workbook. */
exportBtn.addEventListener("click", () => {
  if (!lastPipRows.length) { alert("No records to export."); return; }
  const headers = ["Name", "Mobile", "Alt. Mobile", "Father's Name", "Address", "Alt. Address", "Email", "Reg. Year", "Identity"];
  const aoa = [headers, ...lastPipRows.map(row => [
    row.name || "", row.mobile || "", row.alt || "", row.fname || "",
    row.address || "", row.alt_address || "", row.email || "", row.year_of_registration || "", row.id || "",
  ])];
  const ws = XLSX.utils.aoa_to_sheet(aoa);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Results");
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  XLSX.writeFile(wb, `night-out-export-${stamp}.xlsx`);
});

// Bulk Search - same tab/sequential-loop/CSV-export pattern as hp_gas.php.
const tabSingle = document.getElementById("pipTabSingle");
const tabBulk   = document.getElementById("pipTabBulk");
const singleMode = document.getElementById("pipSingleMode");
const bulkMode    = document.getElementById("pipBulkMode");
tabSingle.addEventListener("click", () => {
  tabSingle.classList.add("active");
  tabBulk.classList.remove("active");
  singleMode.style.display = "";
  bulkMode.style.display = "none";
});
tabBulk.addEventListener("click", () => {
  tabBulk.classList.add("active");
  tabSingle.classList.remove("active");
  singleMode.style.display = "none";
  bulkMode.style.display = "";
});

const bulkNumbersBox  = document.getElementById("pipBulkNumbersBox");
const bulkSearchBtn   = document.getElementById("pipBulkSearchBtn");
const bulkClearBtn    = document.getElementById("pipBulkClearBtn");
const bulkExportBtn   = document.getElementById("pipBulkExportBtn");
const bulkStatus      = document.getElementById("pipBulkStatus");
const bulkResultsWrap = document.getElementById("pipBulkResultsWrap");
const bulkProgressWrap  = document.getElementById("pipBulkProgressWrap");
const bulkProgressFill  = document.getElementById("pipBulkProgressFill");
const bulkProgressLabel = document.getElementById("pipBulkProgressLabel");
const bulkProgressEta   = document.getElementById("pipBulkProgressEta");

const BULK_LIMIT = 50;
let bulkResults = []; // [{ mobile, rows, error }]

function parseBulkNumbers(raw) {
  const tokens = raw.split(/[\s,]+/).map(s => s.trim()).filter(s => s.length > 0);
  const merged = [];
  for (let i = 0; i < tokens.length; i++) {
    const cur = tokens[i].replace(/\D+/g, "");
    const next = tokens[i + 1] ? tokens[i + 1].replace(/\D+/g, "") : "";
    if (cur.length === 5 && next.length === 5) {
      merged.push(cur + next);
      i++;
    } else if (cur.length === 10) {
      merged.push(cur);
    }
  }
  return merged.slice(0, BULK_LIMIT);
}

function bulkBadge(state) {
  if (state === "found") return '<span class="pip-bulk-badge pip-bulk-badge-found">Found</span>';
  if (state === "notfound") return '<span class="pip-bulk-badge pip-bulk-badge-notfound">Not found</span>';
  if (state === "error") return '<span class="pip-bulk-badge pip-bulk-badge-error">Error</span>';
  return '<span class="pip-bulk-badge">Searching…</span>';
}

// Same fixed-column rendering as the single-search table above, scoped to
// one bulk item's own container instead of the shared #pipResultWrap.
function renderBulkItemRows(container, rows) {
  container.innerHTML = "";
  if (!rows || !rows.length) {
    container.innerHTML = `<div class="pip-no-results">No results found.</div>`;
    return;
  }
  const wrap = document.createElement("div");
  wrap.className = "pip-result-wrap";
  wrap.style.display = "block";
  const toolbar = document.createElement("div");
  toolbar.className = "pip-result-toolbar";
  toolbar.textContent = `${rows.length} result${rows.length === 1 ? "" : "s"}`;
  wrap.appendChild(toolbar);

  const table = document.createElement("table");
  table.className = "pip-table";
  table.innerHTML = `<thead><tr><th>Name</th><th>Mobile</th><th>Alt. Mobile</th><th>Father's Name</th>
    <th>Address</th><th>Alt. Address</th><th>Email</th><th>Reg. Year</th><th>Identity</th></tr></thead>`;
  const tbody = document.createElement("tbody");
  rows.forEach(row => {
    const tr = document.createElement("tr");
    tr.appendChild(textCell(row.name));
    tr.appendChild(badgeCell(row.mobile));
    tr.appendChild(badgeCell(row.alt));
    tr.appendChild(textCell(row.fname));
    tr.appendChild(textCell(row.address));
    tr.appendChild(textCell(row.alt_address));
    tr.appendChild(textCell(row.email));
    tr.appendChild(badgeCell(row.year_of_registration));
    tr.appendChild(textCell(row.id));
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  wrap.appendChild(table);
  container.appendChild(wrap);
}

async function runBulkSearch() {
  const numbers = parseBulkNumbers(bulkNumbersBox.value);
  if (numbers.length === 0) {
    bulkStatus.textContent = "Enter at least one valid 10-digit mobile number.";
    return;
  }

  bulkSearchBtn.disabled = true;
  bulkExportBtn.disabled = true;
  bulkResults = [];
  bulkResultsWrap.innerHTML = "";
  bulkProgressWrap.style.display = "block";
  bulkProgressFill.style.width = "0%";
  const startedAt = Date.now();

  for (let i = 0; i < numbers.length; i++) {
    const num = numbers[i];
    bulkStatus.textContent = `Searching ${num}… (${i + 1}/${numbers.length})`;
    bulkProgressLabel.textContent = `${i} / ${numbers.length} searched`;

    const item = document.createElement("div");
    item.className = "pip-bulk-item";
    const header = document.createElement("div");
    header.className = "pip-bulk-item-header";
    header.innerHTML = `<span>${num}</span>${bulkBadge("searching")}`;
    item.appendChild(header);
    const bodyBox = document.createElement("div");
    item.appendChild(bodyBox);
    bulkResultsWrap.appendChild(item);

    try {
      const res = await fetch("pan_india_pro_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mobile: num })
      });
      const data = await res.json();

      if (!res.ok) {
        header.innerHTML = `<span>${num}</span>${bulkBadge("error")}`;
        bodyBox.innerHTML = `<div class="pip-no-results">${data.error || "Search failed."}</div>`;
        bulkResults.push({ mobile: num, rows: [], error: data.error || "Search failed." });
      } else {
        const rows = (data.totalResults && data.rows) ? data.rows : [];
        header.innerHTML = `<span>${num}</span>${bulkBadge(rows.length ? "found" : "notfound")}`;
        renderBulkItemRows(bodyBox, rows);
        bulkResults.push({ mobile: num, rows });
      }
    } catch (err) {
      header.innerHTML = `<span>${num}</span>${bulkBadge("error")}`;
      bodyBox.innerHTML = `<div class="pip-no-results">Could not reach the server: ${err.message}</div>`;
      bulkResults.push({ mobile: num, rows: [], error: err.message });
    }

    const elapsed = (Date.now() - startedAt) / 1000;
    const avgPerItem = elapsed / (i + 1);
    bulkProgressFill.style.width = `${Math.round(((i + 1) / numbers.length) * 100)}%`;
    bulkProgressLabel.textContent = `${i + 1} / ${numbers.length} searched`;
    bulkProgressEta.textContent = i + 1 < numbers.length
      ? `~${formatDuration(avgPerItem * (numbers.length - i - 1))} remaining`
      : `Done in ${formatDuration(elapsed)}`;
  }

  bulkStatus.textContent = `Completed ${numbers.length} search${numbers.length === 1 ? "" : "es"}.`;
  bulkSearchBtn.disabled = false;
  bulkExportBtn.disabled = bulkResults.length === 0;
}

// One sheet, every matched row across every searched number, with the
// number actually searched prepended - lets an agent see at a glance which
// input number produced which result row, unlike per-number tabs.
function exportBulkExcel() {
  if (!bulkResults.length) return;
  const headers = ["Searched Number", "Name", "Mobile", "Alt. Mobile", "Father's Name", "Address", "Alt. Address", "Email", "Reg. Year", "Identity"];
  const aoa = [headers];
  bulkResults.forEach(r => {
    if (!r.rows.length) {
      aoa.push([r.mobile, r.error ? `Error: ${r.error}` : "Not found"]);
      return;
    }
    r.rows.forEach(row => aoa.push([
      r.mobile, row.name || "", row.mobile || "", row.alt || "", row.fname || "",
      row.address || "", row.alt_address || "", row.email || "", row.year_of_registration || "", row.id || "",
    ]));
  });
  const ws = XLSX.utils.aoa_to_sheet(aoa);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Results");
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  XLSX.writeFile(wb, `night-out-bulk-${stamp}.xlsx`);
}

bulkSearchBtn.addEventListener("click", runBulkSearch);
bulkExportBtn.addEventListener("click", exportBulkExcel);
bulkClearBtn.addEventListener("click", () => {
  bulkNumbersBox.value = "";
  bulkStatus.textContent = "";
  bulkResultsWrap.innerHTML = "";
  bulkProgressWrap.style.display = "none";
  bulkExportBtn.disabled = true;
  bulkResults = [];
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

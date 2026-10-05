<?php
require __DIR__ . '/includes/auth.php';
requireEagleEyeAccess();
require_once __DIR__ . '/config/db.php';

$basePath = '';
require __DIR__ . '/includes/header.php';
?>

<!-- Confetti overlay - populated/cleared by startConfetti()/stopConfetti()
     (assets/confetti.js), only while a search has actually succeeded. -->
<div class="confetti-container" id="confetti-container"></div>

<div class="page-header" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px">
  <h1 class="page-title" style="margin:0"><i class="bi bi-globe-asia-australia"></i> Advance Pan India</h1>
  <span class="badge badge-neutral" style="margin-left:auto">Unlimited</span>
</div>

<style>
  /* Same tokens/shape as rc_print.php/hp_gas.php's cards. */
  .ee-card{background:var(--c-surface,#fff);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;}
  .ee-card-body{padding:16px 18px;}
  .ee-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;}
  .ee-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#777;margin-bottom:4px;}
  .ee-field input{width:100%;padding:10px 12px;font-size:13px;color:#333;border:1px solid #e0e0e0;border-radius:8px;background:#fff;outline:none;}
  .ee-row{display:flex;align-items:center;gap:12px;margin-top:14px;flex-wrap:wrap;}
  .ee-btn{padding:11px 26px;border-radius:9px;border:none;background:#4f46e5;color:#fff;
    font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;
    transition:all 150ms;box-shadow:0 4px 18px rgba(79,70,229,.3);}
  .ee-btn:hover:not(:disabled){background:#4338ca;transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.45);}
  .ee-btn:disabled{opacity:.65;cursor:wait;transform:none;}
  .ee-btn-secondary{background:#fff;color:#333;border:1px solid #e0e0e0;box-shadow:none;}
  .ee-btn-secondary:hover:not(:disabled){background:#eeeef6;border-color:#4f46e5;transform:none;box-shadow:none;}
  .ee-btn-excel{background:#10b981;color:#fff;box-shadow:0 4px 18px rgba(16,185,129,.3);}
  .ee-btn-excel:hover:not(:disabled){background:#0d9668;transform:translateY(-1px);box-shadow:0 6px 20px rgba(16,185,129,.45);}
  #eeStatus{font-size:12.5px;color:#555;white-space:pre-wrap;word-break:break-word;font-weight:500;}
  .ee-progress-wrap{margin-top:12px;display:none;}
  .ee-progress-track{height:8px;border-radius:6px;background:#eeeef6;overflow:hidden;border:1px solid #e0e0e0;}
  .ee-progress-fill{height:100%;border-radius:6px;background:#4f46e5;width:100%;
    background-image:repeating-linear-gradient(45deg,#4f46e5 0 12px,#4338ca 12px 24px);
    background-size:34px 100%;animation:ee-progress-stripes 1s linear infinite;}
  @keyframes ee-progress-stripes{from{background-position:0 0;}to{background-position:-34px 0;}}
  .ee-progress-meta{display:flex;justify-content:space-between;margin-top:6px;font-size:11.5px;color:#999;}
  .ee-result-wrap{margin-top:16px;display:none;}
  .ee-table-wrap{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:14px;overflow-x:auto;}
  .ee-table-toolbar{padding:10px 16px;background:#4f46e5;color:#fff;font-size:11.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.3px;}
  .ee-table{width:100%;border-collapse:collapse;font-size:11.5px;}
  .ee-table th{background:#eeeef6;color:#555;font-size:10px;font-weight:700;text-transform:uppercase;
    letter-spacing:.3px;padding:6px 8px;text-align:left;white-space:nowrap;border-bottom:1px solid #e0e0e0;}
  .ee-table td{padding:6px 8px;border-bottom:1px solid #eee;vertical-align:top;color:#333;max-width:260px;word-break:break-word;}
  .ee-table tr:nth-child(even) td{background:#f8f8fc;}
  .ee-no-results{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;color:#777;}

  /* Bulk Search (mobile numbers) - same tab/textarea/progress pattern as
     hp_gas.php/indane_gas_pro.php. */
  .ee-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
  .ee-tab{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:999px;
    border:1px solid #e2e2ea;background:#fff;font-size:12.5px;font-weight:600;color:#555;
    cursor:pointer;transition:all 150ms;white-space:nowrap;}
  .ee-tab i{font-size:14px;}
  .ee-tab:hover{border-color:#4f46e5;color:#4f46e5;}
  .ee-tab.active{background:#4f46e5;border-color:#4f46e5;color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.35);}
  .ee-textarea{width:100%;height:110px;padding:9px 14px;font-size:13px;color:#333;
    border:1px solid #e0e0e0;border-radius:9px;background:#fff;resize:vertical;outline:none;}
  .ee-textarea:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.25);}
  .ee-progress-fill.determinate{background-image:none;animation:none;background:#4f46e5;
    width:0%;transition:width .3s ease;}
  .ee-bulk-item{margin-bottom:16px;}
  .ee-bulk-item-header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#eeeef6;
    border-radius:10px 10px 0 0;font-size:12.5px;font-weight:700;color:#333;
    border:1px solid #e0e0e0;border-bottom:none;}
  .ee-bulk-badge{margin-left:auto;font-size:10.5px;padding:2px 10px;border-radius:999px;
    font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:#e0e0ea;color:#555;}
  .ee-bulk-badge-found{background:rgba(16,185,129,.15);color:#0d9668;}
  .ee-bulk-badge-notfound,.ee-bulk-badge-error{background:rgba(248,113,113,.15);color:#dc2626;}
  .ee-bulk-item .ee-table-wrap{margin-bottom:0;border-radius:0;box-shadow:none;border:1px solid #e0e0e0;border-top:none;}
  .ee-bulk-item .ee-no-results{border-radius:0;box-shadow:none;border:1px solid #e0e0e0;border-top:none;}
  .ee-bulk-item .ee-table-wrap:last-child,.ee-bulk-item .ee-no-results{border-radius:0 0 10px 10px;}
</style>

<div class="ee-tabs">
  <button type="button" class="ee-tab active" id="eeTabSingle"><i class="bi bi-search"></i> Single Search</button>
  <button type="button" class="ee-tab" id="eeTabBulk"><i class="bi bi-list-ol"></i> Bulk Search</button>
</div>

<div id="eeSingleMode">
<div class="ee-card">
  <div class="ee-card-body">
    <div class="ee-grid">
      <div class="ee-field"><label>Mobile</label><input type="text" id="eeMobile" placeholder="Enter mobile number"></div>
      <div class="ee-field"><label>Name</label><input type="text" id="eeName" placeholder="Enter name"></div>
      <div class="ee-field"><label>Father's Name</label><input type="text" id="eeFname" placeholder="Enter father's name"></div>
      <div class="ee-field"><label>Email</label><input type="email" id="eeEmail" placeholder="Enter email"></div>
      <div class="ee-field"><label>Address</label><input type="text" id="eeAddress" placeholder="Enter address"></div>
      <div class="ee-field"><label>Identity</label><input type="text" id="eeMasterId" placeholder="Enter identity"></div>
    </div>
    <div class="ee-row">
      <button id="eeSearchBtn" class="ee-btn">Search</button>
      <button id="eeClearBtn" class="ee-btn ee-btn-secondary" type="button">Clear</button>
      <button id="eeExportBtn" class="ee-btn ee-btn-excel" type="button" disabled>
        <i class="bi bi-file-earmark-excel"></i> Download Excel
      </button>
      <span id="eeStatus"></span>
    </div>
    <div class="ee-progress-wrap" id="eeProgressWrap">
      <div class="ee-progress-track"><div class="ee-progress-fill" id="eeProgressFill"></div></div>
      <div class="ee-progress-meta">
        <span id="eeProgressLabel">Searching Advance Pan India…</span>
        <span id="eeProgressElapsed"></span>
      </div>
    </div>
  </div>
</div>

<div class="ee-result-wrap" id="eeResultWrap"></div>
</div>

<!-- Bulk Search: sequential, one mobile number at a time, over the same
     advance_pan_india_api.php single-search endpoint - same pattern as
     hp_gas.php's own Bulk Search. Available to every agent with Advance
     Pan India access, same as Single Search (no separate toggle). Mobile
     numbers only (not the other fields) - matches how every other bulk
     tool here works. -->
<div id="eeBulkMode" style="display:none">
  <div class="ee-card">
    <div class="ee-card-body">
      <textarea id="eeBulkNumbersBox" class="ee-textarea" placeholder="9876543210, 9876543211, ..."></textarea>
      <div class="ee-row">
        <button id="eeBulkSearchBtn" class="ee-btn">Bulk Search</button>
        <button id="eeBulkClearBtn" class="ee-btn ee-btn-secondary" type="button">Clear</button>
        <button id="eeBulkExportBtn" class="ee-btn ee-btn-excel" type="button" disabled>
          <i class="bi bi-file-earmark-excel"></i> Export CSV
        </button>
        <span id="eeBulkStatus"></span>
      </div>
      <div class="ee-progress-wrap" id="eeBulkProgressWrap">
        <div class="ee-progress-track"><div class="ee-progress-fill determinate" id="eeBulkProgressFill"></div></div>
        <div class="ee-progress-meta">
          <span id="eeBulkProgressLabel"></span>
          <span id="eeBulkProgressEta"></span>
        </div>
      </div>
    </div>
  </div>
  <div id="eeBulkResultsWrap" style="margin-top:16px"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const searchBtn  = document.getElementById("eeSearchBtn");
const clearBtn   = document.getElementById("eeClearBtn");
const exportBtn  = document.getElementById("eeExportBtn");
const statusEl   = document.getElementById("eeStatus");
const resultWrap = document.getElementById("eeResultWrap");
let lastEeTables = [];
const progressWrap    = document.getElementById("eeProgressWrap");
const progressLabel   = document.getElementById("eeProgressLabel");
const progressElapsed = document.getElementById("eeProgressElapsed");

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
// includes/eagleeye_client.php, so the bar itself is always indeterminate
// (striped, animating). The countdown is a fixed estimate (this is a plain
// server-rendered site, no browser automation, so typically just a couple
// seconds - longer only on the rare request that also has to clear
// theeagleeye.biz's session-limit panel first) - same idea as LPG's own
// "~Xs remaining", just without real done/total numbers to base it on.
const ESTIMATED_SECONDS = 5;

function startProgress() {
  searchStartedAt = Date.now();
  progressLabel.textContent = "Searching Advance Pan India…";
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
  name: document.getElementById("eeName"),
  fname: document.getElementById("eeFname"),
  mobile: document.getElementById("eeMobile"),
  email: document.getElementById("eeEmail"),
  address: document.getElementById("eeAddress"),
  master_id: document.getElementById("eeMasterId"),
};

// Tables come from theeagleeye.biz's own result tables, read generically
// (whatever headers/columns it renders) - see includes/eagleeye_client.php.
function renderResult(data) {
  resultWrap.innerHTML = "";
  lastEeTables = (data.totalResults && data.tables) ? data.tables : [];
  exportBtn.disabled = !lastEeTables.length;

  if (!data.totalResults || !data.tables || !data.tables.length) {
    resultWrap.innerHTML = `<div class="ee-no-results">No results found.</div>`;
  } else {
    data.tables.forEach((table, tableIndex) => {
      const box = document.createElement("div");
      box.className = "ee-table-wrap";
      const toolbar = document.createElement("div");
      toolbar.className = "ee-table-toolbar";
      toolbar.textContent = `Source ${tableIndex + 1} — ${table.rows.length} result${table.rows.length === 1 ? "" : "s"}`;
      box.appendChild(toolbar);

      const tableEl = document.createElement("table");
      tableEl.className = "ee-table";
      const thead = document.createElement("thead");
      const headRow = document.createElement("tr");
      table.headers.forEach(h => {
        const th = document.createElement("th");
        th.textContent = h;
        headRow.appendChild(th);
      });
      thead.appendChild(headRow);
      tableEl.appendChild(thead);

      const tbody = document.createElement("tbody");
      table.rows.forEach(row => {
        const tr = document.createElement("tr");
        table.headers.forEach(h => {
          const td = document.createElement("td");
          td.textContent = row[h] || "—";
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
      tableEl.appendChild(tbody);
      box.appendChild(tableEl);
      resultWrap.appendChild(box);
    });
  }

  resultWrap.style.display = "block";
  if (lastEeTables.length) startConfetti(); else stopConfetti();
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
    const res = await fetch("advance_pan_india_api.php", {
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
  lastEeTables = [];
  exportBtn.disabled = true;
  stopConfetti();
});

/* Download all currently rendered result tables as a single .xlsx workbook -
   one sheet per source table, matching how theeagleeye.biz itself groups
   results (see "Source N" toolbar labels in renderResult()). */
exportBtn.addEventListener("click", () => {
  if (!lastEeTables.length) { alert("No records to export."); return; }
  const wb = XLSX.utils.book_new();
  lastEeTables.forEach((table, i) => {
    const aoa = [table.headers, ...table.rows.map(row => table.headers.map(h => row[h] || ""))];
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    XLSX.utils.book_append_sheet(wb, ws, `Source ${i + 1}`.slice(0, 31));
  });
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  XLSX.writeFile(wb, `advance-pan-india-export-${stamp}.xlsx`);
});

// Bulk Search - same tab/sequential-loop/CSV-export pattern as hp_gas.php.
const tabSingle = document.getElementById("eeTabSingle");
const tabBulk   = document.getElementById("eeTabBulk");
const singleMode = document.getElementById("eeSingleMode");
const bulkMode    = document.getElementById("eeBulkMode");
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

const bulkNumbersBox  = document.getElementById("eeBulkNumbersBox");
const bulkSearchBtn   = document.getElementById("eeBulkSearchBtn");
const bulkClearBtn    = document.getElementById("eeBulkClearBtn");
const bulkExportBtn   = document.getElementById("eeBulkExportBtn");
const bulkStatus      = document.getElementById("eeBulkStatus");
const bulkResultsWrap = document.getElementById("eeBulkResultsWrap");
const bulkProgressWrap  = document.getElementById("eeBulkProgressWrap");
const bulkProgressFill  = document.getElementById("eeBulkProgressFill");
const bulkProgressLabel = document.getElementById("eeBulkProgressLabel");
const bulkProgressEta   = document.getElementById("eeBulkProgressEta");

const BULK_LIMIT = 50;
let bulkResults = []; // [{ mobile, totalResults, tables, error }]

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
  if (state === "found") return '<span class="ee-bulk-badge ee-bulk-badge-found">Found</span>';
  if (state === "notfound") return '<span class="ee-bulk-badge ee-bulk-badge-notfound">Not found</span>';
  if (state === "error") return '<span class="ee-bulk-badge ee-bulk-badge-error">Error</span>';
  return '<span class="ee-bulk-badge">Searching…</span>';
}

// Renders one bulk item's tables into its own container, same per-table
// structure as the single-search renderResult() above, just scoped to a
// smaller box instead of the full-width result area.
function renderBulkItemTables(container, tables) {
  container.innerHTML = "";
  if (!tables || !tables.length) {
    container.innerHTML = `<div class="ee-no-results">No results found.</div>`;
    return;
  }
  tables.forEach((table, tableIndex) => {
    const box = document.createElement("div");
    box.className = "ee-table-wrap";
    const toolbar = document.createElement("div");
    toolbar.className = "ee-table-toolbar";
    toolbar.textContent = `Source ${tableIndex + 1} — ${table.rows.length} result${table.rows.length === 1 ? "" : "s"}`;
    box.appendChild(toolbar);

    const tableEl = document.createElement("table");
    tableEl.className = "ee-table";
    const thead = document.createElement("thead");
    const headRow = document.createElement("tr");
    table.headers.forEach(h => {
      const th = document.createElement("th");
      th.textContent = h;
      headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    tableEl.appendChild(thead);

    const tbody = document.createElement("tbody");
    table.rows.forEach(row => {
      const tr = document.createElement("tr");
      table.headers.forEach(h => {
        const td = document.createElement("td");
        td.textContent = row[h] || "—";
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    tableEl.appendChild(tbody);
    box.appendChild(tableEl);
    container.appendChild(box);
  });
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
    item.className = "ee-bulk-item";
    const header = document.createElement("div");
    header.className = "ee-bulk-item-header";
    header.innerHTML = `<span>${num}</span>${bulkBadge("searching")}`;
    item.appendChild(header);
    const bodyBox = document.createElement("div");
    item.appendChild(bodyBox);
    bulkResultsWrap.appendChild(item);

    try {
      const res = await fetch("advance_pan_india_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mobile: num })
      });
      const data = await res.json();

      if (!res.ok) {
        header.innerHTML = `<span>${num}</span>${bulkBadge("error")}`;
        bodyBox.innerHTML = `<div class="ee-no-results">${data.error || "Search failed."}</div>`;
        bulkResults.push({ mobile: num, totalResults: 0, tables: [], error: data.error || "Search failed." });
      } else {
        const found = !!(data.totalResults && data.tables && data.tables.length);
        header.innerHTML = `<span>${num}</span>${bulkBadge(found ? "found" : "notfound")}`;
        renderBulkItemTables(bodyBox, found ? data.tables : []);
        bulkResults.push({ mobile: num, totalResults: data.totalResults || 0, tables: found ? data.tables : [] });
      }
    } catch (err) {
      header.innerHTML = `<span>${num}</span>${bulkBadge("error")}`;
      bodyBox.innerHTML = `<div class="ee-no-results">Could not reach the server: ${err.message}</div>`;
      bulkResults.push({ mobile: num, totalResults: 0, tables: [], error: err.message });
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

// One workbook, one sheet per bulk number searched (same "Source N" style
// as the single-search export, just one tab per input number instead of
// per result-table) - a number with multiple source tables gets them
// stacked in the same sheet with their own toolbar row.
function exportBulkExcel() {
  if (!bulkResults.length) return;
  const wb = XLSX.utils.book_new();
  bulkResults.forEach((r, i) => {
    const aoa = [["Mobile Number", r.mobile], ["Status", r.error ? "Error" : (r.totalResults ? `${r.totalResults} found` : "Not found")], []];
    (r.tables || []).forEach((table, tIdx) => {
      aoa.push([`Source ${tIdx + 1}`]);
      aoa.push(table.headers);
      table.rows.forEach(row => aoa.push(table.headers.map(h => row[h] || "")));
      aoa.push([]);
    });
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    XLSX.utils.book_append_sheet(wb, ws, `${i + 1}_${r.mobile}`.slice(0, 31));
  });
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  XLSX.writeFile(wb, `advance-pan-india-bulk-${stamp}.xlsx`);
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

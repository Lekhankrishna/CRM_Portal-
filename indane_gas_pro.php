<?php
require __DIR__ . '/includes/auth.php';
requireIndaneGasProAccess();
require_once __DIR__ . '/config/db.php';

// Unlimited for everyone (explicit instruction, 2026-09-03) - no more
// monthly quota badge, same as every other unmetered tool here.
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$basePath = '';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px">
  <h1 class="page-title" style="margin:0"><i class="bi bi-fire"></i> Indane Gas Pro</h1>
</div>

<style>
  .igp-card{background:var(--c-surface,#fff);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;}
  .igp-card-body{padding:16px 18px;}
  .igp-row{display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap;}
  .igp-btn{padding:11px 26px;border-radius:9px;border:none;background:#4f46e5;color:#fff;
    font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;
    transition:all 150ms;box-shadow:0 4px 18px rgba(79,70,229,.3);}
  .igp-btn:hover:not(:disabled){background:#4338ca;transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.45);}
  .igp-btn:disabled{opacity:.65;cursor:wait;transform:none;}
  .igp-btn-secondary{background:#fff;color:#333;border:1px solid #e0e0e0;box-shadow:none;}
  .igp-btn-secondary:hover:not(:disabled){background:#eeeef6;border-color:#4f46e5;transform:none;box-shadow:none;}
  .igp-btn-export{background:#10b981;box-shadow:0 4px 18px rgba(16,185,129,.3);}
  .igp-btn-export:hover:not(:disabled){background:#0d9668;box-shadow:0 6px 20px rgba(16,185,129,.45);}
  .igp-btn-export:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
  #igpStatus{font-size:12.5px;color:#555;white-space:pre-wrap;word-break:break-word;font-weight:500;}
  .igp-progress-wrap{margin-top:12px;display:none;}
  .igp-progress-track{height:8px;border-radius:6px;background:#eeeef6;overflow:hidden;border:1px solid #e0e0e0;}
  .igp-progress-fill{height:100%;border-radius:6px;background:#4f46e5;width:100%;
    background-image:repeating-linear-gradient(45deg,#4f46e5 0 12px,#4338ca 12px 24px);
    background-size:34px 100%;animation:igp-progress-stripes 1s linear infinite;}
  @keyframes igp-progress-stripes{from{background-position:0 0;}to{background-position:-34px 0;}}
  .igp-progress-meta{display:flex;justify-content:space-between;margin-top:6px;font-size:11.5px;color:#999;}
  .igp-section{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;margin-top:16px;}
  .igp-section-title{padding:10px 16px;background:#4f46e5;color:#fff;font-size:11.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.4px;}
  .igp-section-table{width:100%;border-collapse:collapse;}
  .igp-section-table tr:nth-child(odd){background:#fff;}
  .igp-section-table tr:nth-child(even){background:#f8f8fc;}
  .igp-section-table td{padding:8px 16px;font-size:12.5px;border-bottom:1px solid #eee;vertical-align:top;}
  .igp-section-table tr:last-child td{border-bottom:none;}
  .igp-field-label{width:38%;color:#777;font-weight:600;}
  .igp-field-value{color:#222;font-weight:500;word-break:break-word;}
  .igp-not-found{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;color:#f87171;font-weight:600;margin-top:16px;}

  /* Bulk Search - same tab/textarea/progress pattern as hp_gas.php. */
  .igp-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
  .igp-tab{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:999px;
    border:1px solid #e2e2ea;background:#fff;font-size:12.5px;font-weight:600;color:#555;
    cursor:pointer;transition:all 150ms;white-space:nowrap;}
  .igp-tab i{font-size:14px;}
  .igp-tab:hover{border-color:#4f46e5;color:#4f46e5;}
  .igp-tab.active{background:#4f46e5;border-color:#4f46e5;color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.35);}
  .igp-textarea{width:100%;height:110px;padding:9px 14px;font-size:13px;color:#333;
    border:1px solid #e0e0e0;border-radius:9px;background:#fff;resize:vertical;outline:none;}
  .igp-textarea:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.25);}
  .igp-progress-fill.determinate{background-image:none;animation:none;background:#4f46e5;
    width:0%;transition:width .3s ease;}
  .igp-bulk-item{margin-bottom:16px;}
  .igp-bulk-item-header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#eeeef6;
    border-radius:10px 10px 0 0;font-size:12.5px;font-weight:700;color:#333;
    border:1px solid #e0e0e0;border-bottom:none;}
  .igp-bulk-badge{margin-left:auto;font-size:10.5px;padding:2px 10px;border-radius:999px;
    font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:#e0e0ea;color:#555;}
  .igp-bulk-badge-found{background:rgba(16,185,129,.15);color:#0d9668;}
  .igp-bulk-badge-notfound,.igp-bulk-badge-error{background:rgba(248,113,113,.15);color:#dc2626;}
  .igp-bulk-item .igp-section{margin-top:0;border-radius:0;box-shadow:none;border:1px solid #e0e0e0;border-top:none;}
  .igp-bulk-item .igp-not-found{border-radius:0;box-shadow:none;border:1px solid #e0e0e0;border-top:none;margin-top:0;}
  .igp-bulk-item .igp-section:last-child,.igp-bulk-item .igp-not-found{border-radius:0 0 10px 10px;}
</style>

<div class="igp-tabs">
  <button type="button" class="igp-tab active" id="igpTabSingle"><i class="bi bi-search"></i> Single Search</button>
  <button type="button" class="igp-tab" id="igpTabBulk"><i class="bi bi-list-ol"></i> Bulk Search</button>
</div>

<div id="igpSingleMode">
<div class="igp-card">
  <div class="igp-card-body">
    <input type="text" id="igpNumberBox" placeholder="9876543210" maxlength="10"
           style="width:100%;padding:11px 16px;font-size:13px;color:#333;border:1px solid #e0e0e0;border-radius:9px;background:#fff;outline:none;">
    <div class="igp-row">
      <button id="igpSearchBtn" class="igp-btn">Search</button>
      <button id="igpClearBtn" class="igp-btn igp-btn-secondary" type="button">Clear</button>
      <button id="igpExportBtn" class="igp-btn igp-btn-export" type="button" disabled>
        <i class="bi bi-file-earmark-<?= $isAdmin ? 'excel' : 'pdf' ?>"></i>
        Download <?= $isAdmin ? 'Excel' : 'PDF' ?>
      </button>
      <span id="igpStatus"></span>
    </div>
    <div class="igp-progress-wrap" id="igpProgressWrap">
      <div class="igp-progress-track"><div class="igp-progress-fill"></div></div>
      <div class="igp-progress-meta">
        <span id="igpProgressLabel">Checking IOCL…</span>
        <span id="igpProgressElapsed"></span>
      </div>
    </div>
  </div>
</div>

<div id="igpResultWrap"></div>
</div>

<!-- Bulk Search: sequential, one number at a time, over the same
     indane_gas_pro_api.php single-search endpoint - same pattern/reasoning
     as hp_gas.php's own Bulk Search (one shared IOCL session at a time,
     not raced across parallel requests). Available to every agent with
     Indane Gas Pro access, same as Single Search (no separate toggle). -->
<div id="igpBulkMode" style="display:none">
  <div class="igp-card">
    <div class="igp-card-body">
      <textarea id="igpBulkNumbersBox" class="igp-textarea" placeholder="9876543210, 9876543211, ..."></textarea>
      <div class="igp-row">
        <button id="igpBulkSearchBtn" class="igp-btn">Bulk Search</button>
        <button id="igpBulkClearBtn" class="igp-btn igp-btn-secondary" type="button">Clear</button>
        <button id="igpBulkExportBtn" class="igp-btn igp-btn-export" type="button" disabled>
          <i class="bi bi-file-earmark-excel"></i> Export CSV
        </button>
        <span id="igpBulkStatus"></span>
      </div>
      <div class="igp-progress-wrap" id="igpBulkProgressWrap">
        <div class="igp-progress-track"><div class="igp-progress-fill determinate" id="igpBulkProgressFill"></div></div>
        <div class="igp-progress-meta">
          <span id="igpBulkProgressLabel"></span>
          <span id="igpBulkProgressEta"></span>
        </div>
      </div>
    </div>
  </div>
  <div id="igpBulkResultsWrap" style="margin-top:16px"></div>
</div>

<?php if ($isAdmin): ?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<?php else: ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/4.2.1/jspdf.umd.min.js"></script>
<?php endif; ?>
<script>
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

const searchBtn      = document.getElementById("igpSearchBtn");
const clearBtn       = document.getElementById("igpClearBtn");
const exportBtn      = document.getElementById("igpExportBtn");
const numberBox      = document.getElementById("igpNumberBox");
const statusEl       = document.getElementById("igpStatus");
const resultWrap     = document.getElementById("igpResultWrap");
const progressWrap   = document.getElementById("igpProgressWrap");
const progressLabel  = document.getElementById("igpProgressLabel");
const progressElapsed= document.getElementById("igpProgressElapsed");

let progressTimer = null;
let searchStartedAt = null;
let lastResult = null; // { mobileNumber, found, fields }

function formatDuration(seconds) {
  seconds = Math.max(0, Math.round(seconds));
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

// Checks IOCL server-side (one real page load with its own consumer-lookup
// AJAX round trip) - typically ~4-8s after trimming indane_gas_pro.py's own
// unnecessary fixed waits (2026-09-06, confirmed live: repeated runs landed
// at 4.3-5.6s under normal site load). Kept as an estimate, not a hard cap -
// app.cyfuture.co.in's own AJAX response has been observed taking much
// longer under real site load (up to ~22s in one live test), and the
// backend is intentionally not cut off at 8s, since that would turn a
// slow-but-real answer into a false "not found" - this just sets what the
// countdown displays, same reasoning as tataplay.php's own indeterminate bar.
const ESTIMATED_SECONDS = 8;

function startProgress() {
  searchStartedAt = Date.now();
  progressLabel.textContent = "Checking IOCL…";
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

function buildFieldTable(fields) {
  const table = document.createElement("table");
  table.className = "igp-section-table";
  const tbody = document.createElement("tbody");
  fields.forEach(field => {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td class="igp-field-label"></td><td class="igp-field-value"></td>`;
    tr.querySelector(".igp-field-label").textContent = field.label;
    tr.querySelector(".igp-field-value").textContent = field.value || "—";
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  return table;
}

function renderResult(data) {
  resultWrap.innerHTML = "";

  if (!data.found) {
    resultWrap.innerHTML = `<div class="igp-not-found">Not found for ${data.mobileNumber}.</div>`;
  } else {
    const box = document.createElement("div");
    box.className = "igp-section";
    const title = document.createElement("div");
    title.className = "igp-section-title";
    title.textContent = "Consumer Details";
    box.appendChild(title);
    box.appendChild(buildFieldTable(data.fields || []));
    resultWrap.appendChild(box);
  }

  lastResult = data;
  exportBtn.disabled = !data.found;
}

async function runSearch() {
  const mobileNumber = numberBox.value.replace(/\D/g, "");
  if (mobileNumber.length !== 10) {
    statusEl.textContent = "Enter a valid 10-digit mobile number.";
    return;
  }

  searchBtn.disabled = true;
  exportBtn.disabled = true;
  statusEl.textContent = "";
  resultWrap.innerHTML = "";
  lastResult = null;
  startProgress();

  try {
    const res = await fetch("indane_gas_pro_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mobileNumber })
    });
    const data = await res.json();

    if (res.status === 401) {
      window.location.href = data.loginUrl || "login.php";
      return;
    }

    if (!res.ok) {
      stopProgress(null);
      statusEl.textContent = `Error: ${data.error || "could not complete search"}`;
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

// Agents get a PDF (jsPDF, built client-side and saved directly - same
// approach as pan_india.php's own "Download as PDF", chosen there over the
// browser's print dialog since print-to-PDF isn't a direct download).
// Admins get an Excel file instead (SheetJS, same pattern as
// indane_gas_info.php's own export) - per explicit instruction, 2026-09-03.
function downloadPdf() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit: 'pt', format: 'a4' });
  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const margin = 40;
  let y = margin;

  function ensureSpace(lineHeight) {
    if (y + lineHeight > pageHeight - margin) { doc.addPage(); y = margin; }
  }

  doc.setFontSize(16);
  doc.text('Indane Gas Pro - Consumer Details', margin, y);
  y += 22;
  doc.setFontSize(10);
  doc.setTextColor(90);
  doc.text(`Mobile Number: ${lastResult.mobileNumber}  |  ${new Date().toLocaleString()}`, margin, y);
  y += 24;
  doc.setTextColor(0);

  (lastResult.fields || []).forEach(field => {
    ensureSpace(18);
    doc.setFont(undefined, 'bold');
    doc.setFontSize(10);
    doc.text(`${field.label}:`, margin, y);
    doc.setFont(undefined, 'normal');
    doc.text(field.value || '—', margin + 160, y);
    y += 18;
  });

  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  doc.save(`indane-gas-pro-${lastResult.mobileNumber}-${stamp}.pdf`);
}

function downloadExcel() {
  const aoa = [["Field", "Value"]];
  (lastResult.fields || []).forEach(field => {
    aoa.push([field.label, field.value || ""]);
  });

  const ws = XLSX.utils.aoa_to_sheet(aoa);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Result");
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  XLSX.writeFile(wb, `indane-gas-pro-${lastResult.mobileNumber}-${stamp}.xlsx`);
}

searchBtn.addEventListener("click", runSearch);
numberBox.addEventListener("keydown", (e) => {
  if (e.key === "Enter" && !searchBtn.disabled) runSearch();
});
clearBtn.addEventListener("click", () => {
  numberBox.value = "";
  statusEl.textContent = "";
  resultWrap.innerHTML = "";
  lastResult = null;
  exportBtn.disabled = true;
  stopProgress(null);
});
exportBtn.addEventListener("click", () => {
  if (!lastResult || !lastResult.found) return;
  if (IS_ADMIN) downloadExcel(); else downloadPdf();
});

// Bulk Search - same tab/sequential-loop/CSV-export pattern as hp_gas.php.
const tabSingle = document.getElementById("igpTabSingle");
const tabBulk   = document.getElementById("igpTabBulk");
const singleMode = document.getElementById("igpSingleMode");
const bulkMode    = document.getElementById("igpBulkMode");
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

const bulkNumbersBox  = document.getElementById("igpBulkNumbersBox");
const bulkSearchBtn   = document.getElementById("igpBulkSearchBtn");
const bulkClearBtn    = document.getElementById("igpBulkClearBtn");
const bulkExportBtn   = document.getElementById("igpBulkExportBtn");
const bulkStatus      = document.getElementById("igpBulkStatus");
const bulkResultsWrap = document.getElementById("igpBulkResultsWrap");
const bulkProgressWrap  = document.getElementById("igpBulkProgressWrap");
const bulkProgressFill  = document.getElementById("igpBulkProgressFill");
const bulkProgressLabel = document.getElementById("igpBulkProgressLabel");
const bulkProgressEta   = document.getElementById("igpBulkProgressEta");

const BULK_LIMIT = IS_ADMIN ? 50 : 10;
let bulkResults = []; // [{ mobileNumber, found, fields, error }]

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
  if (state === "found") return '<span class="igp-bulk-badge igp-bulk-badge-found">Found</span>';
  if (state === "notfound") return '<span class="igp-bulk-badge igp-bulk-badge-notfound">Not found</span>';
  if (state === "error") return '<span class="igp-bulk-badge igp-bulk-badge-error">Error</span>';
  return '<span class="igp-bulk-badge">Searching…</span>';
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
    item.className = "igp-bulk-item";
    const header = document.createElement("div");
    header.className = "igp-bulk-item-header";
    header.innerHTML = `<span>${num}</span>${bulkBadge("searching")}`;
    item.appendChild(header);
    const bodyBox = document.createElement("div");
    item.appendChild(bodyBox);
    bulkResultsWrap.appendChild(item);

    try {
      const res = await fetch("indane_gas_pro_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mobileNumber: num })
      });
      const data = await res.json();

      if (res.status === 401) {
        window.location.href = data.loginUrl || "login.php";
        return;
      }

      if (!res.ok) {
        header.innerHTML = `<span>${num}</span>${bulkBadge("error")}`;
        bodyBox.innerHTML = `<div class="igp-not-found">${data.error || "Search failed."}</div>`;
        bulkResults.push({ mobileNumber: num, found: false, fields: [], error: data.error || "Search failed." });
      } else {
        header.innerHTML = `<span>${num}</span>${bulkBadge(data.found ? "found" : "notfound")}`;
        if (!data.found) {
          bodyBox.innerHTML = `<div class="igp-not-found">Not found for ${num}.</div>`;
        } else {
          bodyBox.appendChild(buildFieldTable(data.fields || []));
        }
        bulkResults.push({ mobileNumber: num, found: !!data.found, fields: data.fields || [] });
      }
    } catch (err) {
      header.innerHTML = `<span>${num}</span>${bulkBadge("error")}`;
      bodyBox.innerHTML = `<div class="igp-not-found">Could not reach the server: ${err.message}</div>`;
      bulkResults.push({ mobileNumber: num, found: false, fields: [], error: err.message });
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

function csvEscape(value) {
  const s = (value ?? "").toString();
  return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

// Columns are the union of every field label seen across all results, in
// first-seen order, so every row lines up even if a particular number's
// result is missing a field another one has.
function exportBulkCsv() {
  if (!bulkResults.length) return;
  const columns = [];
  const columnSet = new Set();
  bulkResults.forEach(r => {
    (r.fields || []).forEach(field => {
      if (!columnSet.has(field.label)) { columnSet.add(field.label); columns.push(field.label); }
    });
  });

  const headers = ["Mobile Number", "Status", ...columns];
  const lines = [headers.join(",")];
  bulkResults.forEach(r => {
    const valueMap = {};
    (r.fields || []).forEach(field => { valueMap[field.label] = field.value || ""; });
    const row = [
      r.mobileNumber,
      r.error ? "Error" : (r.found ? "Found" : "Not found"),
      ...columns.map(c => valueMap[c] || "")
    ];
    lines.push(row.map(csvEscape).join(","));
  });

  const blob = new Blob(["﻿" + lines.join("\r\n")], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const stamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
  const a = document.createElement("a");
  a.href = url;
  a.download = `indane_gas_pro_bulk_search_${stamp}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

bulkSearchBtn.addEventListener("click", runBulkSearch);
bulkExportBtn.addEventListener("click", exportBulkCsv);
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

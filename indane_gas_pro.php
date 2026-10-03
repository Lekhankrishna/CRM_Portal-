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
</style>

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
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

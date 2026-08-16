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
  .lm-result-wrap{margin-top:16px;display:none;}
  .lm-result-count{font-size:12px;color:#777;font-weight:600;margin-bottom:10px;}
  .lm-record{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;margin-bottom:14px;}
  .lm-record-header{padding:12px 16px;background:#4f46e5;color:#fff;display:flex;align-items:center;gap:10px;}
  .lm-record-name{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
  .lm-record-status{margin-left:auto;font-size:10.5px;padding:2px 10px;border-radius:999px;
    font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:rgba(255,255,255,.2);}
  .lm-record-table{width:100%;border-collapse:collapse;}
  .lm-record-table tr:nth-child(odd){background:#fff;}
  .lm-record-table tr:nth-child(even){background:#f8f8fc;}
  .lm-record-table td{padding:8px 16px;font-size:12.5px;border-bottom:1px solid #eee;vertical-align:top;}
  .lm-record-table tr:last-child td{border-bottom:none;}
  .lm-field-label{width:38%;color:#777;font-weight:600;}
  .lm-field-value{color:#222;font-weight:500;word-break:break-word;}
  .lm-not-found{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;color:#f87171;font-weight:600;}
  .lm-tool-select{width:100%;padding:11px 16px;font-size:13px;color:#333;border:1px solid #e0e0e0;
    border-radius:9px;background:#fff;outline:none;margin-bottom:10px;cursor:pointer;}
  .lm-tool-select:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.15);}
</style>

<div class="lm-card">
  <div class="lm-card-body">
    <p class="lm-hint">Pick a tool, then enter the matching value to search locateme.services' database.</p>
    <select id="lmToolSelect" class="lm-tool-select">
      <?php foreach (LOCATEME_TOOLS as $slug => $tool): ?>
        <option value="<?= htmlspecialchars($slug) ?>" data-placeholder="<?= htmlspecialchars($tool['placeholder']) ?>"
                <?= $slug === 'mobile-info' ? 'selected' : '' ?>><?= htmlspecialchars($tool['label']) ?></option>
      <?php endforeach; ?>
    </select>
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

<div class="lm-result-wrap" id="lmResultWrap"></div>

<script>
const searchBtn      = document.getElementById("lmSearchBtn");
const clearBtn       = document.getElementById("lmClearBtn");
const toolSelect     = document.getElementById("lmToolSelect");
const queryBox       = document.getElementById("lmQueryBox");
const statusEl       = document.getElementById("lmStatus");
const resultWrap     = document.getElementById("lmResultWrap");
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

// Placeholder swaps to match whatever the selected tool actually expects
// (mobile number, Aadhaar number, vehicle number, email, IFSC code, ...) -
// see includes/locateme_tools.php for the full list, mirrored server-side
// in Gas/lpg_web/locate_tools.py's TOOL_REGISTRY.
function updatePlaceholder() {
  const opt = toolSelect.options[toolSelect.selectedIndex];
  queryBox.placeholder = opt ? opt.dataset.placeholder : "";
}
toolSelect.addEventListener("change", updatePlaceholder);
updatePlaceholder();

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

// Records come from Gas/lpg_web/locate_tools.py scraping locateme.services'
// own result cards generically (label/value field pairs under a
// name+status header) - rendered as-is here rather than assuming fixed
// field names, since whatever fields locateme.services shows for a given
// query is what gets displayed. Some queries (e.g. a mobile number that's
// changed hands/been ported) can return more than one record - each gets
// its own card.
function renderResult(data) {
  resultWrap.innerHTML = "";

  if (!data.found) {
    resultWrap.innerHTML = `<div class="lm-not-found">Not found for ${data.query}.</div>`;
  } else if (Array.isArray(data.records) && data.records.length) {
    const count = document.createElement("div");
    count.className = "lm-result-count";
    count.textContent = `${data.records.length} record${data.records.length === 1 ? "" : "s"} found`;
    resultWrap.appendChild(count);

    data.records.forEach(record => {
      const box = document.createElement("div");
      box.className = "lm-record";
      const header = document.createElement("div");
      header.className = "lm-record-header";
      header.innerHTML = `<span class="lm-record-name"></span><span class="lm-record-status"></span>`;
      header.querySelector(".lm-record-name").textContent = record.name || "Unknown";
      header.querySelector(".lm-record-status").textContent = record.status || "";
      box.appendChild(header);

      const table = document.createElement("table");
      table.className = "lm-record-table";
      const tbody = document.createElement("tbody");
      (record.fields || []).forEach(field => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td class="lm-field-label"></td><td class="lm-field-value"></td>`;
        tr.querySelector(".lm-field-label").textContent = field.label;
        tr.querySelector(".lm-field-value").textContent = field.value || "—";
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);
      box.appendChild(table);
      resultWrap.appendChild(box);
    });
  } else {
    // Fallback if locateme.services' DOM structure doesn't match the
    // generic card scraper for this particular tool (e.g. whatsapp-dp,
    // which returns an image rather than label/value fields) - see
    // locate_tools.py's module docstring.
    const box = document.createElement("div");
    box.className = "lm-record";
    box.innerHTML = `<div class="lm-record-header"><span class="lm-record-name">Result for ${data.query}</span></div>
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
  const tool = toolSelect.value;
  const query = queryBox.value.trim();
  if (!query) {
    statusEl.textContent = "Enter a value to search.";
    return;
  }

  searchBtn.disabled = true;
  statusEl.textContent = "";
  resultWrap.style.display = "none";
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
  stopProgress(null);
  stopConfetti();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

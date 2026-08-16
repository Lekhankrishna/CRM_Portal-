<?php
require __DIR__ . '/includes/auth.php';
requireLocateMeAccess(); // requireLogin() + a 403 for logged-in users without the "Locate Me Access" permission (Admin > Agents)

$basePath = '';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px">
  <h1 class="page-title" style="margin:0"><i class="bi bi-geo-alt-fill"></i> Locate Me</h1>
  <a href="https://locateme.services/dashboard" target="_blank" rel="noopener noreferrer"
     class="btn btn-sm btn-secondary" style="margin-left:auto">
    <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
  </a>
</div>

<style>
  /* Self-contained, like rc_print.php/hp_gas.php's own inline styles - fills
     the remaining viewport height below the app topbar/page header so the
     embedded dashboard behaves like a native full-height page instead of a
     small boxed-in widget. */
  .locate-me-frame-wrap{
    background:var(--c-surface,#fff);
    border-radius:14px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
    overflow:hidden;
    height:calc(100vh - 170px);
    min-height:480px;
  }
  .locate-me-frame{width:100%;height:100%;border:0;display:block;}
</style>

<div class="locate-me-frame-wrap">
  <iframe class="locate-me-frame" src="https://locateme.services/dashboard" title="Locate Me" loading="lazy" referrerpolicy="no-referrer"></iframe>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

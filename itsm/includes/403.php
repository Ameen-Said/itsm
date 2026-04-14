<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>403 — Access Denied</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body{font-family:'DM Sans',system-ui,sans-serif;min-height:100vh;background:linear-gradient(135deg,#0a0f1e,#1a0a2e);display:flex;align-items:center;justify-content:center;padding:20px}
    .err-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:48px 40px;text-align:center;max-width:440px;backdrop-filter:blur(16px)}
    .err-icon{font-size:64px;color:#ef4444;margin-bottom:20px}
    .err-code{font-size:80px;font-weight:700;color:#fff;line-height:1;margin-bottom:8px}
    .err-msg{color:rgba(255,255,255,.55);font-size:16px;margin-bottom:32px}
  </style>
</head>
<body>
<div class="err-card">
  <div class="err-icon"><i class="bi bi-shield-x"></i></div>
  <div class="err-code">403</div>
  <p class="err-msg">You don't have permission to access this resource.</p>
  <a href="javascript:history.back()" class="btn btn-outline-light me-2"><i class="bi bi-arrow-left me-1"></i>Go Back</a>
  <a href="/itsm/pages/dashboard.php" class="btn btn-primary"><i class="bi bi-house me-1"></i>Dashboard</a>
</div>
</body>
</html>

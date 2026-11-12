<?php
require __DIR__ . '/config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['username'] ?? '') === ADMIN_USER && ($_POST['password'] ?? '') === ADMIN_PASS) {
        $_SESSION['login'] = true;
        $_SESSION['uid'] = 1; // 单医生账号固定 uid=1
        header('Location: index.php'); exit;
    }
    $err = '账号或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - 有医帮</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-card">
  <h1>有医帮</h1>
  <p class="sub">医生 AI 助理</p>
  <?php if ($err): ?><p class="err"><?= $err ?></p><?php endif; ?>
  <form method="post">
    <input name="username" placeholder="账号" required autofocus>
    <input name="password" type="password" placeholder="密码" required>
    <button type="submit">登 录</button>
  </form>
  <div style="margin-top:14px;padding-top:12px;border-top:1px dashed #e5e5e5;text-align:center">
    <span style="font-size:12px;color:#99a1ad">我是患者：</span>
    <a href="patient_query.php" style="font-size:14px;color:#00b578;font-weight:600;text-decoration:none">🔎 查询我的就诊记录 →</a>
    <p style="font-size:11px;color:#bbb;margin-top:4px">凭 姓名+电话+身份证号 查询，仅显示最近 7 日</p>
  </div>
</div>
<p class="footer">本系统输出仅供参考，不构成医疗建议。</p>
</body>
</html>

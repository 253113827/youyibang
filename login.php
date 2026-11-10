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
</div>
<p class="footer">本系统输出仅供参考，不构成医疗建议。</p>
</body>
</html>

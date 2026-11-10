<?php
require __DIR__ . '/config.php';
need_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare('INSERT INTO cases (user_id, patient_name, gender, age, chief_complaint) VALUES (?,?,?,?,?)');
    $stmt->execute([$_SESSION['uid'] ?? 1, trim($_POST['patient_name'] ?? ''), $_POST['gender'] ?? '未知', trim($_POST['age'] ?? ''), trim($_POST['chief_complaint'] ?? '')]);
    $cid = (int)db()->lastInsertId();
    // 预置系统提示（十问引导）
    $sys = "你是有医帮的中医问诊助理。请根据医生与患者的对话，配合预设十问（问寒热、问汗、问头身、问胸腹、问饮食口味、问睡眠、问二便等）协助整理病情。回答简明、面向医生。";
    db()->prepare('INSERT INTO messages (case_id, role, content) VALUES (?,?,?)')->execute([$cid, 'system', $sys]);
    header('Location: case.php?id=' . $cid); exit;
}

$rows = db()->query('SELECT * FROM cases ORDER BY id DESC LIMIT 200')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>有医帮 - 医案列表</title>
<link rel="stylesheet" href="assets/style.css">
<style>.navbar{display:flex;gap:4px;align-items:center;background:#1f6f43;color:#fff;padding:0 12px;height:44px}
.navbar .logo{font-weight:700;margin-right:14px}
.navbar a,.navbar span.mod{color:#d6efe0;text-decoration:none;padding:6px 10px;border-radius:6px;font-size:13px}
.navbar a.act{background:#2e9159;color:#fff}
.navbar .mod{opacity:.45;cursor:not-allowed}
.navbar .right{margin-left:auto}</style>
</head>
<body>
<nav class="navbar">
  <span class="logo">有医帮</span>
  <a href="reception.php">门诊</a>
  <a class="act" href="index.php">医案问诊</a>
  <span class="mod">预约</span><span class="mod">收费</span><span class="mod">药房</span><span class="mod">库存</span><span class="mod">统计</span>
  <span class="right"><a href="logout.php">退出</a></span>
</nav>
<header class="top">
  <span class="logo">医案列表</span>
</header>
<main>
  <details class="newcase">
    <summary>＋ 新建医案</summary>
    <form method="post" class="card">
      <label>患者姓名 <input name="patient_name" required></label>
      <label>性别
        <select name="gender"><option>未知</option><option>男</option><option>女</option></select>
      </label>
      <label>年龄 <input name="age" inputmode="numeric"></label>
      <label>主诉简述 <textarea name="chief_complaint" rows="2"></textarea></label>
      <button type="submit">创建医案</button>
    </form>
  </details>

  <div class="case-list">
  <?php foreach ($rows as $r): ?>
    <a class="case-item" href="case.php?id=<?= (int)$r['id'] ?>">
      <b><?= htmlspecialchars($r['patient_name']) ?></b>
      <span><?= $r['gender'] ?>/<?= htmlspecialchars($r['age']) ?>岁</span>
      <small><?= htmlspecialchars($r['chief_complaint']) ?></small>
      <time><?= $r['created_at'] ?></time>
    </a>
  <?php endforeach; if (!$rows) echo '<p class="empty">暂无医案，请先新建。</p>'; ?>
  </div>
</main>
<p class="footer">本系统输出仅供参考，不构成医疗建议。</p>
</body>
</html>

<?php
require __DIR__ . '/config.php';
need_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? 'create') === 'create') {
    $stmt = db()->prepare('INSERT INTO cases (user_id, patient_name, gender, age, chief_complaint) VALUES (?,?,?,?,?)');
    $stmt->execute([$_SESSION['uid'] ?? 1, trim($_POST['patient_name'] ?? ''), $_POST['gender'] ?? '未知', trim($_POST['age'] ?? ''), trim($_POST['chief_complaint'] ?? '')]);
    $cid = (int)db()->lastInsertId();
    // 预置系统提示（十问引导）
    $sys = "你是有医帮的中医问诊助理。请根据医生与患者的对话，配合预设十问（问寒热、问汗、问头身、问胸腹、问饮食口味、问睡眠、问二便等）协助整理病情。回答简明、面向医生。";
    db()->prepare('INSERT INTO messages (case_id, role, content) VALUES (?,?,?)')->execute([$cid, 'system', $sys]);
    header('Location: case.php?id=' . $cid); exit;
}

/* 编辑医案 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'update') {
    $cid = (int)($_POST['case_id'] ?? 0);
    $own = db()->prepare('SELECT user_id FROM cases WHERE id=?'); $own->execute([$cid]);
    if (($own->fetch()['user_id'] ?? 0) == ($_SESSION['uid'] ?? 1)) {
        db()->prepare('UPDATE cases SET patient_name=?, gender=?, age=?, chief_complaint=? WHERE id=?')
            ->execute([trim($_POST['patient_name'] ?? ''), $_POST['gender'] ?? '未知', trim($_POST['age'] ?? ''), trim($_POST['chief_complaint'] ?? ''), $cid]);
    }
    header('Location: index.php'); exit;
}
/* 删除医案（软删除） */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'delete') {
    $cid = (int)($_POST['case_id'] ?? 0);
    $own = db()->prepare('SELECT user_id FROM cases WHERE id=?'); $own->execute([$cid]);
    if (($own->fetch()['user_id'] ?? 0) == ($_SESSION['uid'] ?? 1)) {
        db()->prepare('UPDATE cases SET is_deleted=1 WHERE id=?')->execute([$cid]);
    }
    header('Location: index.php'); exit;
}

$rows = db()->query('SELECT * FROM cases WHERE is_deleted=0 ORDER BY id DESC LIMIT 200')->fetchAll();
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId) foreach ($rows as $r) if ($r['id'] == $editId) $editRow = $r;
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
.navbar .right{margin-left:auto}
.case-item{display:flex;align-items:center;gap:10px}
.case-item .ops{margin-left:auto;display:flex;gap:4px;align-items:center}
.case-item .ops a{text-decoration:none;font-size:14px}
.case-item .ops button{font-size:14px}</style>
</head>
<body>
<nav class="navbar">
  <span class="logo">有医帮</span>
  <a href="reception.php">门诊</a>
  <a class="act" href="index.php">医案问诊</a>
  <a href="patient_query.php">🔎 患者查询</a>
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

  <?php if ($editRow): ?>
  <details class="newcase" id="editcase" open>
    <summary>✏️ 编辑医案：<?= htmlspecialchars($editRow['patient_name']) ?></summary>
    <form method="post" class="card">
      <input type="hidden" name="do" value="update"><input type="hidden" name="case_id" value="<?= (int)$editRow['id'] ?>">
      <label>患者姓名 <input name="patient_name" required value="<?= htmlspecialchars($editRow['patient_name']) ?>"></label>
      <label>性别
        <select name="gender"><?php foreach (['未知','男','女'] as $g) echo '<option' . ($editRow['gender'] === $g ? ' selected' : '') . '>' . $g . '</option>'; ?></select>
      </label>
      <label>年龄 <input name="age" inputmode="numeric" value="<?= htmlspecialchars($editRow['age']) ?>"></label>
      <label>主诉简述 <textarea name="chief_complaint" rows="2"><?= htmlspecialchars($editRow['chief_complaint']) ?></textarea></label>
      <button type="submit">保存修改</button>
      <a href="index.php" style="margin-left:8px">取消</a>
    </form>
  </details>
  <?php endif; ?>

  <div class="case-list">
  <?php foreach ($rows as $r): ?>
    <div class="case-item">
      <a href="case.php?id=<?= (int)$r['id'] ?>" style="flex:1;min-width:220px;color:inherit;text-decoration:none">
        <b><?= htmlspecialchars($r['patient_name']) ?></b>
        <span><?= $r['gender'] ?>/<?= htmlspecialchars($r['age']) ?>岁</span><br>
        <small><?= htmlspecialchars($r['chief_complaint']) ?></small>
      </a>
      <time><?= $r['created_at'] ?></time>
      <span class="ops">
        <a href="index.php?edit=<?= (int)$r['id'] ?>#editcase" title="编辑">✏️</a>
        <form method="post" style="display:inline;margin:0" onsubmit="return confirm('确定删除医案「<?= htmlspecialchars($r['patient_name']) ?>」吗？数据保留但不再显示。')">
          <input type="hidden" name="do" value="delete"><input type="hidden" name="case_id" value="<?= (int)$r['id'] ?>">
          <button type="submit" style="border:0;background:none;cursor:pointer" title="删除">🗑</button>
        </form>
      </span>
    </div>
  <?php endforeach; if (!$rows) echo '<p class="empty">暂无医案，请先新建。</p>'; ?>
  </div>
</main>
<p class="footer">本系统输出仅供参考，不构成医疗建议。</p>
</body>
</html>

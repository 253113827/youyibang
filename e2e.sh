#!/bin/bash
# 有医帮 E2E 联调脚本（服务器上执行，验证完自动清理）
set -x
cd /tmp/yyb_test
S=https://127.0.0.1
J="-H Content-Type:application/json"
CK="-b cookie.txt -c cookie.txt -H Host:youyibang.com -k"

# 0. 启动 mock
nohup python3 mock_llm.py > mock.log 2>&1 &
echo $! > mock.pid
sleep 1
kill -0 $(cat mock.pid) && echo "MOCK_UP"

# 1. 临时把 mock 端点置顶
python3 - <<'PY'
p='/www/wwwroot/youyibang.com/config.php'
s=open(p).read()
if '13140' not in s:
    s=s.replace('$LLM_ENDPOINTS = [', "$LLM_ENDPOINTS = [\n    ['base' => 'http://127.0.0.1:13140/v1', 'key' => 'ollama', 'model' => 'mock'],", 1)
    open(p,'w').write(s)
PY
grep -n "13140" /www/wwwroot/youyibang.com/config.php || exit 9

# 2. 登录
curl -s $CK -d "username=admin&password=youyibang2026" $S/login.php -o /dev/null -w "LOGIN %{http_code}\n"

# 3. 建案
CID=$(curl -s $CK -d "patient_name=测试患者&gender=男&age=45&chief_complaint=胃脘胀痛" -L $S/index.php -o /dev/null -w "%{url_effective}" | grep -o 'id=[0-9]*' | cut -d= -f2)
echo "CASE_ID=$CID"

# 4. 发消息（AI 回复走 mock）
curl -s $CK -d "case_id=$CID&text=胃脘胀痛三天，反酸口苦" $S/api.php?act=send; echo

# 5. 生成病历草稿（mock 返回缺剂量版）
curl -s $CK -d "case_id=$CID" $S/api.php?act=record | head -c 300; echo

# 6. validate：应报 缺少剂量 error + 十八反 warning
curl -s $CK $S/api.php?act=validate\&case_id=$CID; echo

# 7. sign 应被拒 409
curl -s $CK -d "case_id=$CID&signer_name=测试医生&sign_image=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==" -w "\nSIGN1 %{http_code}\n" $S/api.php?act=sign

# 8. 修正草稿（record_save）
cat > fix.md <<'EOF'
# 中医病案

## 一、主诉
胃脘胀痛伴反酸三天

## 二、现病史
患者三天前进食辛辣后出现胃脘胀痛，伴反酸口苦，未服药。

## 三、四诊摘要
- 望诊：面色偏黄，舌象：红，苔黄腻
- 闻诊：未采集
- 问诊：纳差、口苦、眠可、二便调
- 切诊：脉象：滑数

## 五、辨证诊断
脾胃湿热证

## 七、处方
- 方名：连朴饮化裁
- 黄连 3g（清炒）
- 厚朴 9g（姜制）
- 甘草 6g（生用）
- 甘遂 1.5g（醋制，研末冲服）

## 九、免责声明
仅供执业医师参考。
EOF
curl -s $CK --data-urlencode "case_id=$CID" --data-urlencode "content_md@fix.md" $S/api.php?act=record_save; echo

# 9. validate：无 error，含十八反 warning
curl -s $CK $S/api.php?act=validate\&case_id=$CID; echo

# 10. 签名成功
curl -s $CK -d "case_id=$CID&signer_name=测试医生&sign_image=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==" -w "\nSIGN2 %{http_code}\n" $S/api.php?act=sign

# 11. 签名后 record_save 应被拒 409
curl -s $CK --data-urlencode "case_id=$CID" --data-urlencode "content_md=tamper" -w "\nSAVE_LOCKED %{http_code}\n" $S/api.php?act=record_save

# 12. commit_emr
curl -s $CK -d "case_id=$CID" $S/api.php?act=commit_emr; echo

# 13. verify_emr
curl -s $CK $S/api.php?act=verify_emr\&case_id=$CID; echo

# 14. 撤回→改→再签→再归档（验证第二版本哈希链）
curl -s $CK -d "case_id=$CID" $S/api.php?act=revoke_sign; echo
sed 's/甘草 6g（生用）/甘草 7g（生用）/' fix.md > fix2.md
curl -s $CK --data-urlencode "case_id=$CID" --data-urlencode "content_md@fix2.md" $S/api.php?act=record_save; echo
curl -s $CK -d "case_id=$CID&signer_name=测试医生&sign_image=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==" $S/api.php?act=sign; echo
curl -s $CK -d "case_id=$CID" $S/api.php?act=commit_emr; echo
curl -s $CK $S/api.php?act=verify_emr\&case_id=$CID; echo

# 15. export 200 且含签名图/哈希
curl -s $CK -w "\nEXPORT %{http_code}\n" $S/api.php?act=export\&case_id=$CID -o export.html
grep -c "uploads/signs/" export.html
grep -c "content_hash\|content_hash" export.html >/dev/null; grep -o "前序哈希" export.html | head -1

# 16. 回归：poll
curl -s $CK "$S/api.php?act=poll&case_id=$CID&last_msg=0&last_img=0&rec_ver=0" | head -c 200; echo

# 清理
kill $(cat mock.pid)
# 清理：从 config.php 删除 mock 行（保留改造后版本，勿用改造前备份覆盖！）
python3 - <<'PY'
p='/www/wwwroot/youyibang.com/config.php'
lines=[l for l in open(p) if '13140' not in l]
open(p,'w').writelines(lines)
PY
php -l /www/wwwroot/youyibang.com/config.php | tail -1
grep -c "EMR_SEED" /www/wwwroot/youyibang.com/config.php && echo "EMR_SEED_KEPT"

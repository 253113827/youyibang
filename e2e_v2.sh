#!/bin/bash
# 有医帮 v2 接诊工作台 E2E（服务器执行，验证后自动清理测试数据）
S=https://127.0.0.1
CK="-b /tmp/yyb_v2_cookie.txt -c /tmp/yyb_v2_cookie.txt -H Host:youyibang.com -k"
J="Content-Type:application/x-www-form-urlencoded"
cd /tmp/yyb_v2

echo "== 1. 登录 =="
curl -s $CK -d "username=admin&password=youyibang2026" $S/login.php -o /dev/null -w "LOGIN %{http_code}\n"

echo "== 2. create_patient =="
RESP=$(curl -s $CK -d "name=测试患者V2&gender=女&age=30&phone=13800000000&allergy=青霉素&reg_fee=10" "$S/api.php?act=create_patient")
echo "$RESP"
VID=$(echo "$RESP" | grep -o '"visit_id":[0-9]*' | cut -d: -f2)

echo "== 3. save_opd（含 tcm_checklist + 体格检查三项 + 2味西药步进数据）=="
TCM='{"selected":{"寒热:畏寒":true,"寒热:发热":true,"二便:便秘":true,"舌色:红":true,"苔色:黄":true,"苔质:腻":true,"脉象:滑":true,"脉象:数":true,"面色:黄":true}}'
RX='[{"type":"western","drug_id":1,"name":"阿莫西林胶囊","spec":"0.25g×24粒","usage":"口服","freq":"q8h","dose":2,"dose_unit":"粒","days":7,"qty":2,"qty_unit":"盒","price":12.8},
{"type":"western","drug_id":2,"name":"布洛芬缓释胶囊","spec":"0.3g×20粒","usage":"口服","freq":"q12h","dose":1,"dose_unit":"粒","days":3,"qty":1,"qty_unit":"盒","price":15.6}]'
curl -s $CK --data-urlencode "visit_id=$VID" --data-urlencode "chief_complaint=畏寒发热2天" \
 --data-urlencode "present_illness=患者2天前受凉后畏寒发热，便秘。" \
 --data-urlencode "allergy_history=青霉素过敏" \
 --data-urlencode "physical_exam=体温 37.8C  血压 105/90mmhg  心率 120bpm" \
 --data-urlencode "diagnosis=急性上呼吸道感染" \
 --data-urlencode "auxiliary_exam=[{\"name\":\"血常规\",\"price\":25,\"times\":1}]" \
 --data-urlencode "treatments=[{\"name\":\"针灸治疗\",\"price\":60,\"times\":1}]" \
 --data-urlencode "tcm_checklist=$TCM" \
 --data-urlencode "rx_items=$RX" \
 "$S/api.php?act=save_opd"; echo

echo "== 4. get_opd 回读（重点 tcm_checklist）=="
GET=$(curl -s $CK "$S/api.php?act=get_opd&visit_id=$VID")
echo "$GET" | python3 -c "
import json,sys
j=json.load(sys.stdin)
r=j['record']
print('tcm_checklist:', r.get('tcm_checklist'))
print('physical_exam:', r.get('physical_exam'))
rx=json.load(open('/dev/null')) if False else j['prescriptions']
w=rx['western']['items']
print('western[0]:', w[0]['name'], w[0]['freq'], w[0]['dose'], w[0]['dose_unit'], w[0]['qty'], w[0]['qty_unit'])
print('western_count:', len(w))
assert 'tcm_checklist' in r and json.loads(r['tcm_checklist'])['selected']['脉象:滑']==True, 'TCM FAIL'
assert w[0]['freq']=='q8h' and w[0]['dose_unit']=='粒' and w[0]['qty_unit']=='盒', 'STEPPER FAIL'
print('GET_OPD_PASS')
"

echo "== 5. search_drug =="
curl -s $CK "$S/api.php?act=search_drug&kw=%E9%98%BF%E8%8E%AB" | head -c 200; echo

echo "== 6. 模板保存/读取 =="
curl -s $CK --data-urlencode "name=测试模板V2" --data-urlencode "items=$RX" "$S/api.php?act=save_rx_template"; echo
curl -s $CK "$S/api.php?act=get_templates" | head -c 200; echo

echo "== 7. print_opd（应 200 且含分项小计）=="
curl -s $CK -w "\nPRINT %{http_code}\n" "$S/api.php?act=print_opd&visit_id=$VID" -o /tmp/yyb_v2/print.html
grep -o "分项小计" /tmp/yyb_v2/print.html; grep -o "合计金额：¥[0-9.]*" /tmp/yyb_v2/print.html

echo "== 8. ai_dx（上游离线应优雅降级）=="
curl -s $CK -d "visit_id=$VID" "$S/api.php?act=ai_dx" | head -c 200; echo

echo "== 9. search_diag 咳嗽 =="
curl -s $CK "$S/api.php?act=search_diag&kw=%E5%92%B3%E5%97%BD" | head -c 200; echo

echo "== 10. 回归：case.php/m_reception.php 200 =="
curl -s $CK -o /dev/null -w "case.php %{http_code}\n" $S/case.php
curl -s $CK -o /dev/null -w "m_reception.php %{http_code}\n" $S/m_reception.php
curl -s $CK -o /dev/null -w "reception.php %{http_code}\n" $S/reception.php

echo "== 11. reception.php HTML 断言 =="
curl -s $CK $S/reception.php -o /tmp/yyb_v2/rec.html
for kw in "中医四诊勾选" "class=\"chip" "webkitSpeechRecognition" "speech.js" "西药处方" "输液处方" "中药处方" "stepDose" "editzone" ">保存<" ">取消<" ">打印<" "脉象" "舌色" "面色"; do
  printf "%s: %s\n" "$kw" "$(grep -c "$kw" /tmp/yyb_v2/rec.html)"
done
echo "chip 总数: $(grep -o 'class="chip' /tmp/yyb_v2/rec.html | wc -l)"
echo "问诊组行数: $(grep -o 'class="tcmrow' /tmp/yyb_v2/rec.html | wc -l)"

echo "== 12. 医案回归：poll/commit_emr/verify_emr =="
# 需要一个医案：走 index.php 建案
CID=$(curl -s $CK -d "patient_name=回归医案V2&gender=男&age=40&chief_complaint=咳嗽" -L $S/index.php -o /dev/null -w "%{url_effective}" | grep -o 'id=[0-9]*' | cut -d= -f2)
echo "CASE_ID=$CID"
curl -s $CK "$S/api.php?act=poll&case_id=$CID&last_msg=0&last_img=0&rec_ver=0" | head -c 120; echo
curl -s $CK --data-urlencode "case_id=$CID" --data-urlencode "content_md=# 中医病案
## 一、主诉
咳嗽三天
## 九、免责声明
测试。" "$S/api.php?act=record_save" | head -c 80; echo
curl -s $CK -d "case_id=$CID" "$S/api.php?act=commit_emr" | head -c 160; echo
curl -s $CK "$S/api.php?act=verify_emr&case_id=$CID"; echo

echo "== 13. finish_visit =="
curl -s $CK -d "visit_id=$VID" "$S/api.php?act=finish_visit"; echo

echo "== 14. 清理测试数据 =="
mysql -uyouyibang -pxiaomao youyibang 2>/dev/null <<'SQL'
DELETE FROM emr_records WHERE case_id IN (SELECT id FROM cases WHERE patient_name IN ('测试患者V2','回归医案V2'));
DELETE FROM records WHERE case_id IN (SELECT id FROM cases WHERE patient_name IN ('测试患者V2','回归医案V2'));
DELETE FROM messages WHERE case_id IN (SELECT id FROM cases WHERE patient_name IN ('测试患者V2','回归医案V2'));
DELETE FROM images WHERE case_id IN (SELECT id FROM cases WHERE patient_name IN ('测试患者V2','回归医案V2'));
DELETE FROM cases WHERE patient_name IN ('测试患者V2','回归医案V2');
DELETE FROM prescriptions WHERE visit_id IN (SELECT id FROM visits WHERE patient_id IN (SELECT id FROM patients WHERE name='测试患者V2'));
DELETE FROM opd_records WHERE visit_id IN (SELECT id FROM visits WHERE patient_id IN (SELECT id FROM patients WHERE name='测试患者V2'));
DELETE FROM visits WHERE patient_id IN (SELECT id FROM patients WHERE name='测试患者V2');
DELETE FROM patients WHERE name='测试患者V2';
DELETE FROM rx_templates WHERE name='测试模板V2';
SQL
mysql -uyouyibang -pxiaomao youyibang -N -e "SELECT CONCAT('patients=',(SELECT COUNT(*) FROM patients),' visits=',(SELECT COUNT(*) FROM visits),' opd=',(SELECT COUNT(*) FROM opd_records),' rx=',(SELECT COUNT(*) FROM prescriptions),' tpl=',(SELECT COUNT(*) FROM rx_templates),' cases=',(SELECT COUNT(*) FROM cases),' diag=',(SELECT COUNT(*) FROM diag_dict))" 2>/dev/null
rm -f /tmp/yyb_v2_cookie.txt
echo "E2E_DONE"

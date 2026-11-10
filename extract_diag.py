#!/usr/bin/env python3
"""从 OpenHIS md_yzs.sql dump 抽取 xt_zd 诊断字典 → diag_dict.sql
数据来源：https://gitee.com/tntlinking-opensource/openhis-clinic （GPL-3.0）
抽取字段：zdCode(3) zdmc(4) icd10(5) zdlx(15, TCM=中医病名/WM=西医)
输出：diag_dict(id 自增, icd_code, name, name_tcm, category)
策略：同一 zdCode 去重，取 zt=1 有效记录；TCM 记录填 name_tcm，WM 记录填 name+icd_code。
"""
import re, sys

SRC = '/Users/sxh/WorkBuddy/2026-11-11-01-34-16/youyibang/data/md_yzs.sql'
OUT = '/Users/sxh/WorkBuddy/2026-11-11-01-34-16/youyibang/diag_dict.sql'

def split_values(s):
    """解析 VALUES(...) 内逗号分隔的 SQL 字面量，支持引号转义与 NULL"""
    vals, buf, i, inq = [], [], 0, False
    while i < len(s):
        c = s[i]
        if inq:
            if c == '\\':
                buf.append(s[i+1]); i += 2; continue
            if c == "'":
                if i + 1 < len(s) and s[i+1] == "'":
                    buf.append("'"); i += 2; continue
                inq = False; i += 1; continue
            buf.append(c); i += 1
        else:
            if c == "'": inq = True; i += 1
            elif c == ',': vals.append(''.join(buf)); buf = []; i += 1
            else: buf.append(c); i += 1
    vals.append(''.join(buf))
    return vals

rows = {}  # zdCode -> dict
pat = re.compile(r"INSERT INTO `xt_zd` VALUES \((.*)\);")
n = 0
with open(SRC, encoding='utf-8', errors='replace') as f:
    for line in f:
        m = pat.search(line)
        if not m: continue
        n += 1
        v = split_values(m.group(1))
        if len(v) < 20: continue
        code, name, icd, zt, zdlx = v[2].strip(), v[3].strip(), v[4].strip(), v[13].strip(), v[14].strip()
        if zt not in ('1', '0'): zt = '1'
        if zt == '0' or not code or not name: continue
        r = rows.setdefault(code, {'icd': '', 'name': '', 'tcm': '', 'cat': ''})
        if zdlx == 'TCM':
            r['tcm'] = name; r['cat'] = 'TCM'
        else:
            if not r['name']: r['name'] = name
            if icd and not r['icd']: r['icd'] = icd
            if not r['cat']: r['cat'] = 'WM'
            if zdlx: r['cat'] = r['cat'] if r['cat'] != 'WM' else zdlx

def esc(s): return s.replace("\\", "\\\\").replace("'", "''")

with open(OUT, 'w', encoding='utf-8') as f:
    f.write("-- diag_dict 诊断字典：抽取自 OpenHIS xt_zd（GPL-3.0，来源 gitee.com/tntlinking-opensource/openhis-clinic DB/md_yzs.sql）\n")
    f.write("USE youyibang;\nDELETE FROM diag_dict WHERE id > 0;\n")
    buf = []
    for code, r in rows.items():
        buf.append(f"('{esc(code)}','{esc(r['name'])}','{esc(r['tcm'])}','{esc(r['icd'])}','{esc(r['cat'])}')")
        if len(buf) == 500:
            f.write('INSERT INTO diag_dict (zd_code,name,name_tcm,icd_code,category) VALUES ' + ','.join(buf) + ';\n'); buf = []
    if buf: f.write('INSERT INTO diag_dict (zd_code,name,name_tcm,icd_code,category) VALUES ' + ','.join(buf) + ';\n')
print(f"INSERT lines parsed: {n}, unique codes: {len(rows)}")

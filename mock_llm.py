#!/usr/bin/env python3
"""临时 mock OpenAI 兼容服务（联调用，验证完删除）。端口 13140，支持 stream 与非流式。"""
import json, threading
from http.server import BaseHTTPRequestHandler, HTTPServer

DRAFT_BAD = """# 中医病案

## 一、主诉
胃脘胀痛伴反酸三天

## 二、现病史
患者三天前进食辛辣后出现胃脘胀痛。

## 三、四诊摘要
- 望诊：面色偏黄，舌象：红，苔黄腻
- 闻诊：未采集
- 问诊：纳差、口苦、眠可、二便调
- 切诊：脉象：滑数

## 五、辨证诊断
脾胃湿热证

## 七、处方
方名：连朴饮化裁
甘草、甘遂

## 九、免责声明
仅供执业医师参考。
"""

DRAFT_OK = DRAFT_BAD.replace("- 甘草、甘遂", "- 黄连 3g（清炒）\n- 厚朴 9g（姜制）\n- 甘草 6g（生用）\n- 甘遂 1.5g（醋制，研末冲服）")

call_count = {"n": 0}

class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def do_POST(self):
        ln = int(self.headers.get("Content-Length", 0))
        body = json.loads(self.rfile.read(ln) or b"{}")
        msgs = body.get("messages", [])
        text = json.dumps(msgs, ensure_ascii=False)
        is_record = "中医病案整理助手" in text
        if is_record:
            call_count["n"] += 1
            content = DRAFT_BAD if call_count["n"] == 1 else DRAFT_OK
        else:
            content = "（mock 对话回复）"
        if body.get("stream"):
            self.send_response(200)
            self.send_header("Content-Type", "text/event-stream")
            self.end_headers()
            for ch in content:
                chunk = {"choices": [{"delta": {"content": ch}}]}
                self.wfile.write(b"data: " + json.dumps(chunk, ensure_ascii=False).encode() + b"\n\n")
            self.wfile.write(b"data: [DONE]\n\n")
        else:
            resp = {"choices": [{"message": {"role": "assistant", "content": content}}]}
            data = json.dumps(resp, ensure_ascii=False).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)

HTTPServer(("127.0.0.1", 13140), H).serve_forever()

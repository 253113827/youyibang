#!/usr/bin/env python3
"""有医帮 本地 ASR 服务：OpenAI 兼容 /v1/audio/transcriptions（faster-whisper）
- ASR_MODEL 环境变量：模型规格（small/base/tiny，默认 small）
- ASR_PORT/ASR_HOST：监听地址
- 端点为同步 def（线程池执行），转写期间事件循环不阻塞；ASR_LOCK 保证同时只转一片，忙时丢弃
"""
import io
import os
import threading
import numpy as np
from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.responses import JSONResponse
import faster_whisper

MODEL_SIZE = os.environ.get("ASR_MODEL", "small")
app = FastAPI()
print(f"loading faster-whisper {MODEL_SIZE} ...", flush=True)
model = faster_whisper.WhisperModel(MODEL_SIZE, device="cpu", compute_type="int8")
print("model ready", flush=True)

ASR_LOCK = threading.Lock()

def _do_transcribe(data: bytes) -> str:
    try:
        import av
        container = av.open(io.BytesIO(data))
        stream = next(s for s in container.streams if s.type == "audio")
        resampler = av.AudioResampler(format="s16", layout="mono", rate=16000)
        pcm = []
        for frame in container.decode(stream):
            for rf in resampler.resample(frame):
                pcm.append(rf.to_ndarray())
        if not pcm:
            raise ValueError("no audio frames")
        audio_arr = np.concatenate([p.flatten() for p in pcm]).astype(np.float32) / 32768.0
    except Exception as e:
        magic = data[:16].hex()
        raise HTTPException(400, f"decode audio failed: {e} | size={len(data)} magic={magic}")
    segments, info = model.transcribe(audio_arr, language="zh", vad_filter=True,
                                      initial_prompt="以下是普通话简体中文门诊医患对话。",
                                      condition_on_previous_text=False,
                                      no_speech_threshold=0.6,
                                      log_prob_threshold=-1.0)
    return "".join(s.text for s in segments).strip()

# 同步 def：FastAPI 自动放线程池，不阻塞事件循环
@app.post("/v1/audio/transcriptions")
def transcriptions(audio: UploadFile = File(...), model_name: str = Form("whisper-1")):
    data = audio.file.read()
    if not data:
        raise HTTPException(400, "empty audio")
    if not ASR_LOCK.acquire(blocking=False):
        # 上一片还在转写：丢弃本片（前端会继续送后续分片），避免堆积雪崩
        return JSONResponse({"text": "", "note": "busy"})
    try:
        text = _do_transcribe(data)
    finally:
        ASR_LOCK.release()
    return JSONResponse({"text": text})

@app.get("/v1/models")
def models():
    return {"object": "list", "data": [{"id": "whisper-1", "object": "model", "owned_by": "youyibang-local"}]}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host=os.environ.get("ASR_HOST", "127.0.0.1"), port=int(os.environ.get("ASR_PORT", "8765")), log_level="warning")

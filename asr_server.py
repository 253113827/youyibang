#!/usr/bin/env python3
"""有医帮 本地 ASR 服务：OpenAI 兼容 /v1/audio/transcriptions（faster-whisper，small 模型）
启动：/Users/sxh/WorkBuddy/2026-11-11-11-41-30/.asr_venv/bin/python asr_server.py
"""
import io
import numpy as np
from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.responses import JSONResponse
import faster_whisper

MODEL_SIZE = "small"
app = FastAPI()
print(f"loading faster-whisper {MODEL_SIZE} ...", flush=True)
model = faster_whisper.WhisperModel(MODEL_SIZE, device="cpu", compute_type="int8")
print("model ready", flush=True)

@app.post("/v1/audio/transcriptions")
async def transcriptions(audio: UploadFile = File(...), model_name: str = Form("whisper-1")):
    data = await audio.read()
    if not data:
        raise HTTPException(400, "empty audio")
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
        raise HTTPException(400, f"decode audio failed: {e}")
    segments, info = model.transcribe(audio_arr, language="zh", vad_filter=True,
                                      initial_prompt="以下是普通话简体中文门诊对话。")
    text = "".join(s.text for s in segments).strip()
    return JSONResponse({"text": text, "language": info.language})

@app.get("/v1/models")
def models():
    return {"object": "list", "data": [{"id": "whisper-1", "object": "model", "owned_by": "youyibang-local"}]}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8765, log_level="warning")

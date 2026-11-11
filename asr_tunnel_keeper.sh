#!/bin/bash
# 有医帮 ASR 隧道守护（经 FRP 通道）：M5 MAX 的 R2T2 服务 → 服务器 18781
KEY=/Users/sxh/.ssh/id_ed25519_wangcai
while true; do
  if ! ssh -p 2224 -i $KEY -o StrictHostKeyChecking=no -o BatchMode=yes beiheng@43.160.196.55 'true' 2>/dev/null; then
    echo "[$(date +%H:%M:%S)] M5 unreachable via FRP, retry in 30s"
    sleep 30; continue
  fi
  echo "[$(date +%H:%M:%S)] starting ASR tunnel via FRP"
  ssh -p 2224 -N -R 127.0.0.1:18781:127.0.0.1:18780 \
    -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes -o ConnectTimeout=15 -i $KEY beiheng@43.160.196.55
  echo "[$(date +%H:%M:%S)] ASR tunnel exited, reconnect in 5s"
  sleep 5
done

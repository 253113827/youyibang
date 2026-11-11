#!/bin/bash
# 有医帮隧道守护：SSH 反向隧道断线自动重连
# 隧道1：服务器 18770 → 内网 M5 网关 10.0.0.128:19170 (LLM glm-5.3-flash)
# 隧道2：服务器 18781 → beiheng M5 MAX 10.0.0.100:18780 (R2T2 实时 ASR)
KEY=/Users/sxh/.ssh/id_ed25519_wangcai
while true; do
  if ! ssh -i $KEY -o StrictHostKeyChecking=no -o BatchMode=yes root@43.160.196.55 'true' 2>/dev/null; then
    echo "[$(date +%H:%M:%S)] server unreachable, retry in 30s"
    sleep 30; continue
  fi
  echo "[$(date +%H:%M:%S)] starting tunnels"
  ssh -N -R 127.0.0.1:18770:10.0.0.128:19170 -R 127.0.0.1:18781:10.0.0.100:18780 \
    -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes -o ConnectTimeout=15 -i $KEY root@43.160.196.55
  echo "[$(date +%H:%M:%S)] tunnels exited, reconnect in 5s"
  sleep 5
done

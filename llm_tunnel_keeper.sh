#!/bin/bash
# 有医帮 LLM 隧道守护：SSH 反向隧道断线自动重连
# 用法: nohup /Users/sxh/WorkBuddy/2026-11-11-11-41-30/llm_tunnel_keeper.sh >/tmp/yyb_tunnel.log 2>&1 &
KEY=/Users/sxh/.ssh/id_ed25519_wangcai
while true; do
  if ! ssh -i $KEY -o StrictHostKeyChecking=no -o BatchMode=yes root@43.160.196.55 'true' 2>/dev/null; then
    echo "[$(date +%H:%M:%S)] server unreachable, retry in 30s"
    sleep 30; continue
  fi
  echo "[$(date +%H:%M:%S)] starting tunnel"
  ssh -N -R 127.0.0.1:18770:10.0.0.128:19170 \
    -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes -o ConnectTimeout=15 -i $KEY root@43.160.196.55
  echo "[$(date +%H:%M:%S)] tunnel exited, reconnect in 5s"
  sleep 5
done

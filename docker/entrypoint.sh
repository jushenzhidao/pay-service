#!/usr/bin/env bash
# 容器启动时修正挂载目录权限，然后交给 Apache。
#
# 为什么需要这个脚本：
#   Linux 上 bind mount 的 uid/gid 与权限位由宿主机决定，原样透传进容器。
#   若宿主机文件是 640 或属主非 www-data，Apache（uid 33）读不到 .htaccess，
#   会返回 403 并报 "Server unable to read htaccess file, denying access to be safe"。
#   bind mount 是同一个 inode，容器内 chmod/chown 可以穿透到宿主机，因此能在此修正。
#
# 幂等：每次启动都跑，已正确的文件不会被改坏。
# 可通过 EPAY_FIX_PERMS=0 关闭（例如宿主机权限已由外部统一管理）。

set -euo pipefail

WEBROOT=/var/www/html

fix_perms() {
  # 空目录说明没挂载源码，直接跳过（镜像本身不含业务代码）
  if [ -z "$(ls -A "$WEBROOT" 2>/dev/null)" ]; then
    echo "[entrypoint] $WEBROOT 为空，未挂载源码，跳过权限修正"
    return 0
  fi

  echo "[entrypoint] 修正 $WEBROOT 权限（目录 755 / 文件 644）"

  # 只在确实需要时才改，避免每次启动都遍历写入。
  # -not -perm 让已合规的条目被跳过，大幅减少 inode 写操作。
  # 排除 .git：里面有大量文件且不由 Apache 提供服务。
  find "$WEBROOT" -path "$WEBROOT/.git" -prune -o \
       -type d -not -perm 755 -print0 2>/dev/null |
    xargs -0 -r chmod 755 2>/dev/null || true

  find "$WEBROOT" -path "$WEBROOT/.git" -prune -o \
       -type f -not -perm 644 -print0 2>/dev/null |
    xargs -0 -r chmod 644 2>/dev/null || true

  # 属主归 www-data。失败不阻断启动：只读挂载或 userns-remap 场景下
  # chown 会被拒绝，但此时权限位往往已足够 Apache 读取。
  if ! chown -R www-data:www-data "$WEBROOT" 2>/dev/null; then
    echo "[entrypoint] 警告：chown 失败（可能是只读挂载或 user namespace 限制），继续启动"
  fi

  # 明确校验目标文件可读，读不到就提前报错，比等 Apache 返回 403 更好定位
  if [ -f "$WEBROOT/.htaccess" ]; then
    if su -s /bin/bash www-data -c "test -r $WEBROOT/.htaccess"; then
      echo "[entrypoint] .htaccess 对 www-data 可读"
    else
      echo "[entrypoint] 错误：www-data 仍无法读取 .htaccess" >&2
      echo "[entrypoint] 请在宿主机执行：chmod 644 .htaccess 并确认各级父目录含 o+x" >&2
      echo "[entrypoint] 排查命令：namei -l <项目路径>/.htaccess" >&2
    fi
  fi
}

if [ "${EPAY_FIX_PERMS:-1}" = "1" ]; then
  fix_perms
else
  echo "[entrypoint] EPAY_FIX_PERMS=0，跳过权限修正"
fi

# 交回原镜像的 entrypoint，保持 php:apache 的既有行为（信号处理、Apache 变量展开等）
exec docker-php-entrypoint "$@"

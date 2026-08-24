#!/usr/bin/env bash
# 首次初始化并推送本仓库。
#
# 为什么不用 `git add * .env*`：
#   * 是 shell 通配符，不匹配点开头的文件（.github/、.gitignore 都会漏掉）；
#   且 .env* 无匹配项时会让整条 git add 失败退出，导致暂存区为空，
#   随后 commit 报 "nothing added to commit"、push 报 "src refspec main does not match any"。
#   正确做法是用 git 自己的 -A，它遵循 .gitignore 且覆盖隐藏文件。

set -euo pipefail

cd "$(dirname "$0")"

REMOTE_URL="git@github.com:jushenzhidao/pay-service.git"
BRANCH="main"

# 幂等：仓库/remote 不存在时才创建
[ -d .git ] || git init
git remote get-url origin >/dev/null 2>&1 || git remote add origin "$REMOTE_URL"

git symbolic-ref HEAD "refs/heads/$BRANCH"

git add -A

if git diff --cached --quiet; then
  echo "没有需要提交的变更。"
else
  git commit -m "init"
fi

# 不使用 -f：强推会覆盖远端历史，此处仅正常推送并建立追踪
git push -u origin "$BRANCH"

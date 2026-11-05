#!/usr/bin/env bash
# PostToolUse hook: after a Bash call that ran `git commit`, push the current branch to origin.
cmd=$(node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{try{process.stdout.write(JSON.parse(s).tool_input.command||"")}catch{}})')

echo "$cmd" | grep -qE '(^|[;&|[:space:]])git( -C [^ ]+)? commit' || exit 0
echo "$cmd" | grep -q -- '--dry-run' && exit 0

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0
branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null) || exit 0
[ "$branch" = "HEAD" ] && exit 0

if out=$(git push -u origin "$branch" 2>&1); then
  echo "{\"systemMessage\":\"Auto-pushed $branch to origin\"}"
else
  echo "Auto-push of $branch to origin failed: $out" >&2
  exit 2
fi

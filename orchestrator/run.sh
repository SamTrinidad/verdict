#!/usr/bin/env bash
# Wrapper: use the Python 3.12 install that has claude-agent-sdk
PYTHON="/c/Users/samtr/AppData/Local/Programs/Python/Python312/python.exe"
export PYTHONIOENCODING=utf-8
exec "$PYTHON" "$(dirname "$0")/orchestrator.py" "$@"

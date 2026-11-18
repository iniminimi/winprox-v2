@echo off
cd /d C:\winprox
git rm -f --ignore-unmatch scripts\_commit_push.bat scripts\_finish_push.bat 2>nul
del /q scripts\_finish_push.bat 2>nul
del /q scripts\_commit_push.bat 2>nul
git add docs\release-notes\published\2026-11-18.md docs\release-notes\draft\2026-11-18.md
git add -A
git rebase --continue
if errorlevel 1 exit /b 1
git push origin main
git status --short
git log -3 --oneline

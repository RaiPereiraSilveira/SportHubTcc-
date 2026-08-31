@echo off
setlocal enabledelayedexpansion
rem ===========================================================================
rem  SPORTHUB - rotina de backup (SH-51) - Windows / XAMPP
rem
rem  Copia o banco `olimpiasp` e a pasta `uploads/` para uma pasta datada,
rem  descarta o que passou do prazo de retencao e escreve um relatorio.
rem
rem  Por que uploads/ junto do banco: os documentos de credenciamento e as
rem  fotos de perfil NAO estao no banco. Restaurar so o SQL devolve um sistema
rem  que aponta para arquivos que nao existem mais.
rem
rem  Uso manual:
rem      scripts\backup.bat
rem
rem  Agendamento diario (as 02h00), no Prompt como administrador:
rem      schtasks /create /tn "SportHub Backup" /tr "C:\xampp\htdocs\sporthub_tcc1\scripts\backup.bat" /sc daily /st 02:00
rem
rem  Conferir:   schtasks /query /tn "SportHub Backup"
rem  Remover:    schtasks /delete /tn "SportHub Backup" /f
rem ===========================================================================

rem ── Configuracao ─────────────────────────────────────────────────────────
set "PROJETO=%~dp0.."
set "DESTINO=C:\backups\sporthub"
set "MYSQL_BIN=C:\xampp\mysql\bin"
set "DB_NOME=olimpiasp"
set "DB_USUARIO=root"
set "DB_SENHA="
set "RETENCAO_DIAS=30"

rem ── Carimbo de data AAAA-MM-DD_HHMM, sem depender do formato regional ────
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value 2^>nul') do set "LDT=%%I"
if "%LDT%"=="" (
    echo [ERRO] Nao foi possivel ler a data do sistema.
    exit /b 1
)
set "CARIMBO=%LDT:~0,4%-%LDT:~4,2%-%LDT:~6,2%_%LDT:~8,2%%LDT:~10,2%"
set "PASTA=%DESTINO%\%CARIMBO%"

echo.
echo === SportHub - backup %CARIMBO% ===
if not exist "%DESTINO%" mkdir "%DESTINO%"
mkdir "%PASTA%" 2>nul

rem ── 1. Banco de dados ────────────────────────────────────────────────────
echo [1/4] Exportando o banco %DB_NOME%...
if defined DB_SENHA (
    "%MYSQL_BIN%\mysqldump.exe" -u %DB_USUARIO% -p%DB_SENHA% --single-transaction --routines --events --default-character-set=utf8mb4 %DB_NOME% > "%PASTA%\%DB_NOME%.sql"
) else (
    "%MYSQL_BIN%\mysqldump.exe" -u %DB_USUARIO% --single-transaction --routines --events --default-character-set=utf8mb4 %DB_NOME% > "%PASTA%\%DB_NOME%.sql"
)
if errorlevel 1 (
    echo [ERRO] mysqldump falhou. Backup abortado.
    echo %CARIMBO% ERRO mysqldump >> "%DESTINO%\backup.log"
    exit /b 1
)

rem ── 2. Arquivos enviados ─────────────────────────────────────────────────
echo [2/4] Copiando uploads/ e img/times/...
robocopy "%PROJETO%\uploads" "%PASTA%\uploads" /E /NFL /NDL /NJH /NJS /NP >nul
robocopy "%PROJETO%\img\times" "%PASTA%\img_times" /E /NFL /NDL /NJH /NJS /NP >nul

rem ── 3. Configuracao local ────────────────────────────────────────────────
rem  config.local.php guarda senha de banco e de SMTP: vai para o backup
rem  porque sem ele a restauracao nao sobe, mas a pasta de destino precisa
rem  ter acesso restrito.
if exist "%PROJETO%\includes\config.local.php" (
    copy /y "%PROJETO%\includes\config.local.php" "%PASTA%\config.local.php" >nul
)
if exist "%PROJETO%\logs\segredo_feed.txt" (
    copy /y "%PROJETO%\logs\segredo_feed.txt" "%PASTA%\segredo_feed.txt" >nul
)

rem ── 4. Retencao ──────────────────────────────────────────────────────────
echo [3/4] Removendo backups com mais de %RETENCAO_DIAS% dias...
forfiles /p "%DESTINO%" /d -%RETENCAO_DIAS% /c "cmd /c if @isdir==TRUE rd /s /q @path" 2>nul

rem ── Relatorio ────────────────────────────────────────────────────────────
echo [4/4] Conferindo...
for %%F in ("%PASTA%\%DB_NOME%.sql") do set "TAMANHO=%%~zF"
if "%TAMANHO%"=="0" (
    echo [ERRO] O dump saiu vazio.
    echo %CARIMBO% ERRO dump vazio >> "%DESTINO%\backup.log"
    exit /b 1
)
echo %CARIMBO% OK  dump=%TAMANHO% bytes  destino=%PASTA% >> "%DESTINO%\backup.log"
echo.
echo Concluido: %PASTA%  (dump de %TAMANHO% bytes)
echo Historico em %DESTINO%\backup.log
echo.
echo LEMBRETE: backup que nunca foi restaurado nao e backup. Teste a
echo restauracao uma vez por bimestre com scripts\restaurar.bat.
endlocal

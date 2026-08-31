@echo off
setlocal
rem ===========================================================================
rem  SPORTHUB - restauracao de backup (SH-51) - Windows / XAMPP
rem
rem  Backup que nunca foi restaurado nao e backup: e um arquivo grande com
rem  esperanca dentro. Este script existe para que o teste de restauracao seja
rem  um comando, e nao um exercicio de memoria em dia de emergencia.
rem
rem  Uso:
rem      scripts\restaurar.bat C:\backups\sporthub\2026-08-26_0200
rem
rem  Para ENSAIAR sem tocar na base de producao, restaure em outro banco:
rem      scripts\restaurar.bat C:\backups\sporthub\2026-08-26_0200 olimpiasp_teste
rem ===========================================================================

set "ORIGEM=%~1"
set "DB_NOME=%~2"
if "%DB_NOME%"=="" set "DB_NOME=olimpiasp"

set "PROJETO=%~dp0.."
set "MYSQL_BIN=C:\xampp\mysql\bin"
set "DB_USUARIO=root"
set "DB_SENHA="

if "%ORIGEM%"=="" (
    echo Uso: scripts\restaurar.bat ^<pasta-do-backup^> [banco-destino]
    echo.
    echo Backups disponiveis:
    dir /b /ad "C:\backups\sporthub" 2>nul
    exit /b 1
)
if not exist "%ORIGEM%\olimpiasp.sql" (
    echo [ERRO] Nao encontrei %ORIGEM%\olimpiasp.sql
    exit /b 1
)

echo.
echo === RESTAURACAO ===
echo Origem : %ORIGEM%
echo Destino: banco %DB_NOME%
echo.
echo Isto APAGA e recria o banco %DB_NOME%.
set /p CONFIRMA="Digite RESTAURAR para continuar: "
if not "%CONFIRMA%"=="RESTAURAR" (
    echo Cancelado.
    exit /b 1
)

echo [1/3] Recriando o banco...
if defined DB_SENHA (
    "%MYSQL_BIN%\mysql.exe" -u %DB_USUARIO% -p%DB_SENHA% -e "DROP DATABASE IF EXISTS %DB_NOME%; CREATE DATABASE %DB_NOME% DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    "%MYSQL_BIN%\mysql.exe" -u %DB_USUARIO% -p%DB_SENHA% --default-character-set=utf8mb4 %DB_NOME% < "%ORIGEM%\olimpiasp.sql"
) else (
    "%MYSQL_BIN%\mysql.exe" -u %DB_USUARIO% -e "DROP DATABASE IF EXISTS %DB_NOME%; CREATE DATABASE %DB_NOME% DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    "%MYSQL_BIN%\mysql.exe" -u %DB_USUARIO% --default-character-set=utf8mb4 %DB_NOME% < "%ORIGEM%\olimpiasp.sql"
)
if errorlevel 1 (
    echo [ERRO] A importacao falhou.
    exit /b 1
)

echo [2/3] Devolvendo os arquivos enviados...
if exist "%ORIGEM%\uploads"   robocopy "%ORIGEM%\uploads"   "%PROJETO%\uploads"   /E /NFL /NDL /NJH /NJS /NP >nul
if exist "%ORIGEM%\img_times" robocopy "%ORIGEM%\img_times" "%PROJETO%\img\times" /E /NFL /NDL /NJH /NJS /NP >nul

echo [3/3] Conferindo...
if defined DB_SENHA (
    "%MYSQL_BIN%\mysql.exe" -u %DB_USUARIO% -p%DB_SENHA% -e "SELECT (SELECT COUNT(*) FROM usuarios) AS usuarios, (SELECT COUNT(*) FROM times) AS times, (SELECT COUNT(*) FROM jogos) AS jogos;" %DB_NOME%
) else (
    "%MYSQL_BIN%\mysql.exe" -u %DB_USUARIO% -e "SELECT (SELECT COUNT(*) FROM usuarios) AS usuarios, (SELECT COUNT(*) FROM times) AS times, (SELECT COUNT(*) FROM jogos) AS jogos;" %DB_NOME%
)

echo.
echo Restauracao concluida em %DB_NOME%.
echo Se restaurou em banco de teste, aponte SH_DB_NOME em config.local.php
echo para conferir pelo site sem mexer na producao.
endlocal

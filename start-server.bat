@echo off
cd /d "E:\ONBOSS DIGITAL\SNAPHUBB\snaphubb-pages"
"E:\ARQUIVOS\php-8.3.29-nts-Win32-vs16-x64\php.exe" -c "E:\ARQUIVOS\php-8.3.29-nts-Win32-vs16-x64\php_custom.ini" artisan serve --host=127.0.0.1 --port=8005
pause

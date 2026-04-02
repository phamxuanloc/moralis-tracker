@echo off
echo Installing dependencies...
wsl -d Ubuntu-22.04 -e bash -c "cd /home/locpx/adgine-all/tracking-address && composer install --no-interaction"

echo.
echo Running PHPUnit tests...
wsl -d Ubuntu-22.04 -e bash -c "cd /home/locpx/adgine-all/tracking-address && vendor/bin/phpunit"

echo.
echo Tests completed!
pause

@echo off
echo [⏳] جاري رفع التعديلات للسيرفر...
git add .
git commit -m "quick update"
git push
echo [🚀] تم الرفع بنجاح!
pause
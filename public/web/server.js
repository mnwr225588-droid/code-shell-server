/**
 * ====================================================
 * سيرفر التطوير المحلي لموقع Code Shell Web
 * ====================================================
 * الهدف: تشغيل الموقع عبر HTTP بدلاً من file:// لتجنب قيود CORS في المتصفح.
 * 
 * طريقة التشغيل:
 *   node server.js
 * 
 * ثم افتح المتصفح على:
 *   http://localhost:5500
 * 
 * لماذا نحتاج هذا؟
 * المتصفحات الحديثة (Chrome, Edge, Firefox) تمنع طلبات fetch() من بروتوكول file://
 * إلى أي سيرفر خارجي (حتى لو CORS مفعّل). تشغيل الموقع عبر HTTP يحل هذه المشكلة.
 * ====================================================
 */

const http = require('http');
const fs = require('fs');
const path = require('path');

// المنفذ المحلي — يمكن تغييره حسب الحاجة
const PORT = 5500;

// أنواع الملفات المدعومة (MIME Types)
const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.mp4': 'video/mp4',
  '.webm': 'video/webm',
  '.webp': 'image/webp',
};

// إنشاء السيرفر المحلي
const server = http.createServer((req, res) => {
  // تحديد مسار الملف المطلوب
  let filePath = path.join(__dirname, req.url === '/' ? 'index.html' : req.url);
  
  // إزالة query string إن وُجد
  filePath = filePath.split('?')[0];

  const ext = path.extname(filePath).toLowerCase();
  const contentType = MIME_TYPES[ext] || 'application/octet-stream';

  // قراءة وإرسال الملف
  fs.readFile(filePath, (err, content) => {
    if (err) {
      if (err.code === 'ENOENT') {
        // إذا الملف غير موجود، حاول إرجاع index.html (SPA fallback)
        res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end('<h1 style="font-family:Tahoma;direction:rtl;text-align:center;margin-top:50px;">404 — الصفحة غير موجودة</h1>');
      } else {
        res.writeHead(500);
        res.end('Server Error: ' + err.code);
      }
    } else {
      res.writeHead(200, { 'Content-Type': contentType });
      res.end(content);
    }
  });
});

server.listen(PORT, () => {
  console.log('');
  console.log('  ╔══════════════════════════════════════════════════╗');
  console.log('  ║   🚀 Code Shell Web — سيرفر التطوير المحلي     ║');
  console.log('  ╠══════════════════════════════════════════════════╣');
  console.log(`  ║   🌐 http://localhost:${PORT}                     ║`);
  console.log('  ║   📂 ' + __dirname);
  console.log('  ║   ⏹  اضغط Ctrl+C للإيقاف                      ║');
  console.log('  ╚══════════════════════════════════════════════════╝');
  console.log('');
});

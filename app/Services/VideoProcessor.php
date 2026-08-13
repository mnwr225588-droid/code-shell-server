<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * معالج ملفات الفيديو — يضمن أن كل MP4 يُخزَّن جاهزاً للبث (Fast-Start).
 *
 * لماذا؟ ملفات الهاتف/الكاميرات غالباً تخزن فهرس العينات moov في نهاية الملف؛
 * المشغلات عندها تشغّل أول ثوانٍ ثم تتوقف وتتقطع وكأنها مشكلة إنترنت رغم أن
 * الاتصال سليم. نقل moov إلى بداية الملف (مع تصليح جداول offsets) يجعل
 * التشغيل والتقديم مستمراً على جميع المشغلات (أندرويد/آيفون/ويب) — بدون
 * إعادة ترميز أي أنه لا يفقد جودة ولا يطيل وقت الرفع.
 */
class VideoProcessor
{
    /**
     * ينشئ نسخة Fast-Start من ملف فيديو محلي.
     * يعيد مسار النسخة الجاهزة للبث (أو المسار الأصلي إذا تعذر التحويل).
     *
     * @param string $localPath مسار ملف الفيديو المحلي
     * @param string $fallbackName اسم الملف للنسخة الناتجة
     */
    public function fastStart(string $localPath, string $fallbackName): string
    {
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            Log::warning('VideoProcessor: تحويل Fast-Start متاح لـ mp4 فقط، تم تخطي ' . $ext);
            return $localPath;
        }

        if ($this->alreadyFastStart($localPath)) {
            Log::info('VideoProcessor: الملف Fast-Start مسبقاً — لا حاجة للتحويل');
            return $localPath;
        }

        $outPath = tempnam(sys_get_temp_dir(), 'faststart_');
        if ($outPath === false) {
            return $localPath;
        }
        $outPath .= '.mp4';

        // 1) ffmpeg إن وُجد على السيرفر
        if ($this->binaryExists('ffmpeg')) {
            try {
                $process = new Process([
                    'ffmpeg', '-y', '-i', $localPath,
                    '-c', 'copy', '-movflags', '+faststart', $outPath,
                ]);
                $process->setTimeout(300);
                $process->run();
                if ($process->isSuccessful() && is_file($outPath) && filesize($outPath) > 0) {
                    Log::info('VideoProcessor: ffmpeg faststart OK');
                    return $outPath;
                }
                Log::warning('VideoProcessor: ffmpeg failed: ' . substr($process->getErrorOutput(), 0, 300));
            } catch (\Throwable $e) {
                Log::warning('VideoProcessor: ffmpeg error: ' . $e->getMessage());
            }
        }

        // 2) qtfaststart عبر بايثون إن وُجد (بديل بدون تثبيت ffmpeg)
        $python = $this->pythonBinary();
        if ($python !== null) {
            try {
                $process = new Process([
                    $python, '-m', 'qtfaststart', $localPath, $outPath,
                ]);
                $process->setTimeout(300);
                $process->run();
                if ($process->isSuccessful() && is_file($outPath) && filesize($outPath) > 0) {
                    Log::info('VideoProcessor: qtfaststart (python) OK');
                    return $outPath;
                }
                $out = $process->getOutput() . $process->getErrorOutput();
                if (stripos($out, 'already be setup for streaming') !== false) {
                    Log::info('VideoProcessor: الملف Fast-Start مسبقاً (qtfaststart)');
                    @unlink($outPath);
                    return $localPath;
                }
                Log::warning('VideoProcessor: qtfaststart failed: ' . substr($out, 0, 300));
            } catch (\Throwable $e) {
                Log::warning('VideoProcessor: qtfaststart error: ' . $e->getMessage());
            }
        }

        @unlink($outPath);
        Log::warning('VideoProcessor: لا يوجد ffmpeg/qtfaststart — سيُخزَّن الفيديو كما هو');
        return $localPath;
    }

    private function binaryExists(string $name): bool
    {
        try {
            $process = new Process(['command', '-v', $name]);
            $process->run();
            if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
                return true;
            }
            $process = new Process(['where.exe', $name]);
            $process->run();
            return $process->isSuccessful() && trim($process->getOutput()) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    private function pythonBinary(): ?string
    {
        foreach (['python3', 'python'] as $bin) {
            try {
                $process = new Process([$bin, '-c', 'import qtfaststart']);
                $process->run();
                if ($process->isSuccessful()) {
                    return $bin;
                }
            } catch (\Throwable) {
                // جرّب التالي
            }
        }
        return null;
    }

    /**
     * يفحص ترتيب وحدات (atoms) الأغلفة: إذا وُجد moov قبل mdat فالملف Fast-Start مسبقاً.
     */
    private function alreadyFastStart(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $atoms = 0;
        try {
            while (!feof($fh)) {
                $head = fread($fh, 8);
                if ($head === false || strlen($head) < 8) {
                    break;
                }
                $data = @unpack('Nsize/a4type', $head);
                if ($data === false) {
                    break;
                }
                $size = $data['size'];
                $type = $data['type'];
                if ($type === 'moov') {
                    return true;
                }
                if ($type === 'mdat') {
                    return false;
                }
                if ($size < 8) {
                    break;
                }
                fseek($fh, $size - 8, SEEK_CUR);
                $atoms++;
                if ($atoms > 500) {
                    break;
                }
            }
        } finally {
            fclose($fh);
        }
        return false;
    }
}
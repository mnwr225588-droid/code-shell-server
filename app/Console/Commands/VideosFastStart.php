<?php

namespace App\Console\Commands;

use App\Services\VideoProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VideosFastStart extends Command
{
    protected $signature = 'videos:faststart {--dry : يعرض الملفات فقط دون تعديل}';

    protected $description = 'يعالج كل فيديوهات R2 إلى Fast-Start (moov في البداية) للبث السلس';

    public function handle(): int
    {
        $disk = Storage::disk('r2');
        $files = $disk->allFiles('lessons/videos');
        $processor = new VideoProcessor();
        $fixed = 0;
        $skipped = 0;
        $failed = 0;

        $this->info('found ' . count($files) . ' video object(s)');

        foreach ($files as $key) {
            if (! str_ends_with(strtolower($key), '.mp4')) {
                $skipped++;
                continue;
            }

            $this->line('processing: ' . $key);

            if ($this->option('dry')) {
                continue;
            }

            $tmpIn = tempnam(sys_get_temp_dir(), 'fs_in_') . '.mp4';
            $tmpOut = null;
            try {
                $src = $disk->readStream($key);
                $dst = fopen($tmpIn, 'wb');
                if ($src === false || $dst === false) {
                    throw new \RuntimeException('cannot open stream');
                }
                stream_copy_to_stream($src, $dst);
                fclose($dst);
                if (is_resource($src)) {
                    fclose($src);
                }

                $tmpOut = $processor->fastStart($tmpIn, basename($key));
                if ($tmpOut === $tmpIn) {
                    $this->info('  already fast-start (no change needed)');
                    $skipped++;
                    continue;
                }

                $disk->put($key, fopen($tmpOut, 'rb'), ['ContentType' => 'video/mp4']);
                $fixed++;
                $this->info('  fast-start OK');
            } catch (\Throwable $e) {
                $failed++;
                $this->error('  failed: ' . $e->getMessage());
            } finally {
                @unlink($tmpIn);
                if ($tmpOut !== null && $tmpOut !== $tmpIn) {
                    @unlink($tmpOut);
                }
            }
        }

        $this->info("done: fixed=$fixed skipped=$skipped failed=$failed");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
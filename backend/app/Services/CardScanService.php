<?php
namespace App\Services;

use App\Models\CardScan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CardScanService
{
    public function root(): string
    {
        $root = rtrim(config('card_scans.root'), '/\\');
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Private scan storage unavailable');
        }
        return $root;
    }

    public function locked(string $name, callable $callback, bool $wait=true)
    {
        $handle = fopen($this->root().'/'.$name.'.lock', 'c');
        if (!$handle || !flock($handle, LOCK_EX | ($wait ? 0 : LOCK_NB))) abort(409,'Η ανάλυση εκτελείται. Δοκίμασε ξανά όταν ολοκληρωθεί.');
        try { return $callback(); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function path(CardScan $scan, string $file): string
    {
        if (!Str::isUuid($scan->id) || !preg_match('/^[0-2]-(front|back|angled)\.png$/D', $file)) abort(404);
        return $this->root().'/'.$scan->id.'/'.$file;
    }

    public function create(int $userId, array $data, array $files): CardScan
    {
        return $this->locked('intake', function () use ($userId, $data, $files) {
            $existing = CardScan::where('user_id', $userId)->where('request_key', $data['request_key'])->first();
            if ($existing) return $existing;
            $this->prune();
            $today = CardScan::where('created_at', '>=', now()->startOfDay());
            if ((clone $today)->count() >= config('card_scans.daily_limit') ||
                (clone $today)->where('user_id', $userId)->count() >= config('card_scans.user_daily_limit')) {
                abort(429, 'Έφτασε το ημερήσιο όριο scans της beta. Δοκίμασε αύριο.');
            }
            if (CardScan::where('user_id',$userId)->whereIn('state',['queued','processing'])->exists()) {
                abort(409, 'Περίμενε να ολοκληρωθεί το προηγούμενο scan.');
            }
            $scan = new CardScan([
                'id'=>(string) Str::uuid(), 'user_id'=>$userId, 'request_key'=>$data['request_key'],
                'category'=>$data['category'], 'training_opt_in'=>$data['training_opt_in'],
                'policy_version'=>config('card_scans.policy_version'), 'state'=>'queued',
                'expires_at'=>now()->addDays($data['training_opt_in'] ? 30 : 1),
                'provenance'=>['model'=>'Cardora-1','release'=>config('card_scans.model_revision'),
                    'acceptance_method'=>isset($data['terms_accepted'])?'scan_submission':null,
                    'image_preparation'=>$data['image_preparation'] ?? 'legacy-normalized',
                    'crop_review_required'=>true],
            ]);
            $folder=$this->root().'/'.$scan->id;
            mkdir($folder,0700);
            $images=[]; $bytes=0;
            try {
                foreach ($files as $role=>$file) {
                    $info=@getimagesize($file->getRealPath());
                    if (!$info || min($info[0],$info[1]) < 128 || $info[0]*$info[1] > 16000000 ||
                        !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) {
                        throw ValidationException::withMessages(['front'=>'Χρειάζεται JPG/PNG/WebP από 128 pixels έως 16 megapixels.']);
                    }
                    $image=@imagecreatefromstring(file_get_contents($file->getRealPath()));
                    if (!$image) throw new \RuntimeException('Image decode failed');
                    // Apply JPEG orientation before discarding EXIF metadata.
                    $exif = $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')
                        ? @exif_read_data($file->getRealPath()) : [];
                    $orientation=(int)($exif['Orientation'] ?? 1);
                    if (in_array($orientation,[2,4,5,7],true)) imageflip($image,IMG_FLIP_HORIZONTAL);
                    $angle=match($orientation) {3,4=>180,5,6=>-90,7,8=>90,default=>0};
                    if ($angle) { $rotated=imagerotate($image,$angle,0); imagedestroy($image); $image=$rotated; }
                    $scale=min(1,1800/max(imagesx($image),imagesy($image)));
                    $w=(int)round(imagesx($image)*$scale); $h=(int)round(imagesy($image)*$scale);
                    $clean=imagecreatetruecolor($w,$h);
                    imagefilledrectangle($clean,0,0,$w,$h,imagecolorallocate($clean,255,255,255));
                    imagecopyresampled($clean,$image,0,0,0,0,$w,$h,imagesx($image),imagesy($image));
                    $name=count($images).'-'.$role.'.png';
                    $path=$this->path($scan,$name);
                    if (!imagepng($clean,$path)) throw new \RuntimeException('Image write failed');
                    imagedestroy($image); imagedestroy($clean); chmod($path,0600);
                    $bytes+=filesize($path);
                    $images[]=['file'=>$name,'role'=>$role,'width'=>$w,'height'=>$h,'sha256'=>hash_file('sha256',$path)];
                }
                if (count($images)>1 && $images[0]['sha256']===$images[1]['sha256']) {
                    throw ValidationException::withMessages(['back'=>'Χρειάζονται διαφορετικές φωτογραφίες.']);
                }
                if (CardScan::sum('bytes')+$bytes > config('card_scans.max_bytes')) abort(503,'Η συλλογή είναι προσωρινά πλήρης.');
                $scan->images=$images; $scan->bytes=$bytes; $scan->save();
            } catch (\Throwable $e) {
                foreach (glob($folder.'/*.png') ?: [] as $file) unlink($file);
                rmdir($folder); throw $e;
            }
            return $scan;
        });
    }

    public function kick(): void
    {
        if (!config('card_scans.spawn_worker') || !config('card_scans.enabled')) return;
        // Fixed executable/command, no user input; on-demand process exits when queue drains.
        $command='/usr/bin/nohup '.escapeshellarg(config('card_scans.php_binary')).' '.
            escapeshellarg(base_path('artisan')).' cardora:process-scans > /dev/null 2>&1 < /dev/null &';
        exec($command);
    }

    public function process(CardScan $scan): void
    {
        $scan->update(['state'=>'processing','started_at'=>now()]);
        try {
            $deadline=time()+330; $ready=false;
            // Only an actual queued scan wakes the worker. No idle keep-alive traffic.
            while (time()<$deadline) {
                try {
                    $health=Http::withToken(config('card_scans.runpod_key'))
                        ->withHeaders(['X-API-Key'=>config('card_scans.app_key')])
                        ->connectTimeout(10)->timeout(30)->withoutRedirecting()
                        ->get(config('card_scans.endpoint').'/health/ready');
                    if (in_array($health->status(),[401,403],true)) throw new \LogicException('Inference credentials rejected');
                    if ($health->successful() && $health->json('inference')==='ready') { $ready=true;break; }
                } catch (\Illuminate\Http\Client\ConnectionException $e) { /* bounded cold start wait */ }
                sleep(3);
            }
            if (!$ready) throw new \RuntimeException('Cold start unavailable');
            $request=Http::withToken(config('card_scans.runpod_key'))
                ->withHeaders(['X-API-Key'=>config('card_scans.app_key')])
                ->connectTimeout(20)->timeout(150)->withoutRedirecting();
            foreach ($scan->images as $image) {
                $path=$this->path($scan,$image['file']);
                if (hash_file('sha256',$path)!==$image['sha256']) throw new \RuntimeException('Integrity mismatch');
                $request=$request->attach($image['role']==='angled'?'printing_image':'files',
                    file_get_contents($path),$image['file'],['Content-Type'=>'image/png']);
            }
            // No automatic inference retry: a timed-out remote request may still be billed.
            $response=$request->post(config('card_scans.endpoint').'/api/analyze',['game_category'=>$scan->category]);
            if (!$response->successful() || !is_array($response->json('result'))) {
                throw new \RuntimeException('Inference unavailable');
            }
            $result=$response->json('result');
            $identity=[];
            foreach (['name','set_name','collector_number','language'] as $key) {
                $identity[$key]=is_string($result['identity'][$key] ?? null) ? mb_substr($result['identity'][$key],0,300) : null;
            }
            $safe=['identity'=>$identity,'outcome'=>'completed'];
            foreach (['status','identity_status','printing_status','parallel','finish','print_variant','edition','subset'] as $key) {
                $safe[$key]=is_string($result[$key] ?? null) ? mb_substr($result[$key],0,300) : null;
            }
            $scan->update(['state'=>'completed','prediction'=>$safe]);
        } catch (\Throwable $e) {
            $scan->update(['state'=>'failed','error_code'=>'inference_unavailable']);
        } finally {
            if (!$scan->training_opt_in) $this->removeImages($scan);
        }
    }

    public function removeImages(CardScan $scan): void
    {
        foreach ($scan->images as $image) {
            $path=$this->path($scan,$image['file']);
            if (is_file($path)) unlink($path);
        }
        $folder=$this->root().'/'.$scan->id;
        if (is_dir($folder)) rmdir($folder);
        $scan->update(['images'=>[],'bytes'=>0,'clearance'=>null]);
    }

    public function prune(): void
    {
        foreach (glob($this->root().'/export-*.zip') ?: [] as $export) {
            if (filemtime($export)<time()-3600) unlink($export);
        }
        // Expiry never erases an in-flight upload before the worker finishes.
        CardScan::where('state','processing')->where('started_at','<',now()->subMinutes(15))
            ->update(['state'=>'failed','error_code'=>'worker_interrupted']);
        foreach (CardScan::where('expires_at','<',now())->where('state','!=','processing')->cursor() as $scan) {
            $this->removeImages($scan); $scan->delete();
        }
        file_put_contents($this->root().'/retention-last-run.json',json_encode(['at'=>now()->toIso8601String()]),LOCK_EX);
    }
}

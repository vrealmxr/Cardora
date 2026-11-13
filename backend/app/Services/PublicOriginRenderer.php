<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\PublicProfileController;
use App\Models\User;

class PublicOriginRenderer
{
    public const ROOT = '/home/u912666299/cardora-private/public-renderer';
    public const NODE = '/home/u912666299/.nvm/versions/node/v22.23.1/bin/node';

    public function envelope(Request $request, string $digest, ?string $secret = null): array
    {
        abort_unless(PublicRenderSignature::verifiedVisitor($request, $secret) !== null, 403);
        $encoded = $request->header('X-Cardora-Render-Request', '');
        abort_unless(strlen($encoded) <= 7000, 413);
        $raw = base64_decode($encoded, true);
        abort_unless(is_string($raw) && hash_equals($digest, hash('sha256', $raw)), 403);
        $input = json_decode($raw, true);
        abort_unless(is_array($input) && ($input['version'] ?? null) === 1
            && in_array($input['mode'] ?? null, ['production', 'preview'], true)
            && preg_match('/^[a-f0-9]{16}$/D', $input['release'] ?? '')
            && is_string($input['path'] ?? null) && strlen($input['path']) <= 4096
            && str_starts_with($input['path'], '/') && !str_starts_with($input['path'], '//')
            && !preg_match('/[\x00-\x20\x7f\\\\#]/', $input['path']), 422);
        $locale = preg_match('#^/en(?:/|\?|$)#', $input['path']) ? 'en' : 'el';
        abort_unless($request->query('locale', 'el') === $locale, 422);
        $input['locale'] = $locale;
        return $input;
    }

    public function data(array $input): array
    {
        $locale = $input['locale'];
        app()->setLocale($locale);
        $path = rawurldecode((string) parse_url($input['path'], PHP_URL_PATH));
        $input['data'] = Cache::remember('public-origin-dto-v1:'.$locale, 30,
            fn () => app(PublicPageDataService::class)->snapshot($locale));
        $input['seo'] = app(SeoController::class)()->getData(true)['data'];
        if (preg_match('#^/(?:el|en)/proion/([^/]+)$#D', $path, $match)) {
            // Never cache the visibility or state of an individual listing.
            $input['listing'] = app(PublicListingService::class)->findPublic($match[1], $locale);
        }
        if (preg_match('#^/(?:el|en)/sylloges/([^/]+)$#D', $path, $match)) {
            $profile = User::query()->where('handle', $match[1])->first();
            $input['profile'] = null;
            if ($profile && $profile->profile_visibility !== 'private') {
                $anonymous = Request::create('/api/profiles/'.rawurlencode($match[1]), 'GET', ['locale' => $locale]);
                $anonymous->setUserResolver(fn () => null);
                $input['profile'] = app(PublicProfileController::class)->show($anonymous, $profile)->response($anonymous)->getData(true)['data'];
            }
        }
        if (preg_match('#^/sitemap(?:-\d+)?\.xml$#D', $path)) {
            $input['listingPaths'] = app(PublicListingService::class)->publicPaths();
        }
        return $input;
    }

    public function render(array $input): array
    {
        $directory = self::ROOT.'/releases/'.$input['release'];
        $manifest = is_file($directory.'/release.json') ? json_decode(file_get_contents($directory.'/release.json'), true) : null;
        abort_unless(($manifest['release'] ?? null) === $input['release'], 503);
        // Bound work on shared hosting. Locks are released even if PHP exits.
        $lock = null;
        for ($slot = 0; $slot < 4; $slot++) {
            $candidate = fopen(self::ROOT.'/slot-'.$slot.'.lock', 'c');
            if ($candidate && flock($candidate, LOCK_EX | LOCK_NB)) { $lock = $candidate; break; }
            if ($candidate) fclose($candidate);
        }
        abort_unless($lock, 503);
        $process = null; $pipes = [];
        try {
            $payload = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            abort_unless(strlen($payload) <= 8 * 1024 * 1024, 503);
            $process = proc_open([self::NODE, '--disable-wasm-trap-handler', '--max-old-space-size=128', $directory.'/origin-render-cli.mjs'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $directory,
                ['PATH' => dirname(self::NODE), 'NODE_ENV' => 'production']);
            if (!is_resource($process)) throw new \RuntimeException('Renderer unavailable');
            foreach ($pipes as $pipe) stream_set_blocking($pipe, false);
            $offset = 0; $output = ''; $diagnostic = ''; $deadline = microtime(true) + 8; $exit = null;
            do {
                if (isset($pipes[0])) {
                    $written = fwrite($pipes[0], substr($payload, $offset, 16384));
                    if ($written === false) throw new \RuntimeException('Renderer input failed');
                    $offset += $written;
                    if ($offset === strlen($payload)) { fclose($pipes[0]); unset($pipes[0]); }
                }
                $output .= stream_get_contents($pipes[1]);
                $diagnostic = substr($diagnostic.stream_get_contents($pipes[2]), 0, 2048);
                if (strlen($output) > 12 * 1024 * 1024) throw new \RuntimeException('Renderer output limit');
                $state = proc_get_status($process);
                if (!$state['running']) { $exit = $state['exitcode']; $output .= stream_get_contents($pipes[1]); break; }
                usleep(1000);
            } while (microtime(true) < $deadline);
            if ($exit !== 0) {
                file_put_contents(self::ROOT.'/last-process-error.log', json_encode(['exit'=>$exit, 'stderr'=>$diagnostic, 'inputWritten'=>$offset]).PHP_EOL, LOCK_EX);
                chmod(self::ROOT.'/last-process-error.log',0600);
                throw new \RuntimeException('Renderer failed or timed out');
            }
            $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($result) || !in_array($result['status'] ?? null, [200, 404], true)
                || !is_string($result['body'] ?? null)) throw new \RuntimeException('Invalid renderer response');
            return $result;
        } finally {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($process)) { if (proc_get_status($process)['running']) proc_terminate($process, 9); proc_close($process); }
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}

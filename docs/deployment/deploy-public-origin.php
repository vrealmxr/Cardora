<?php
if (PHP_SAPI !== 'cli') exit(1);
$root = '/home/u912666299/domains/cardora.gr/public_html/backend';
$manifest = json_decode(file_get_contents(__DIR__.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run','--apply','--rollback'], true) || count($manifest) !== 4) throw new RuntimeException('Invalid deployment');
$allowed = ['app/Services/PublicRenderSignature.php','routes/public_pages_api.php','app/Services/PublicOriginRenderer.php','app/Http/Controllers/Api/PublicHtmlController.php'];
foreach ($manifest as $row) {
    if (!in_array($row['path'],$allowed,true) || basename($row['file']) !== $row['file']) throw new RuntimeException('Unexpected target');
    $target = $root.'/'.$row['path']; $current = is_file($target) ? hash_file('sha256',$target) : null;
    if ($current !== ($mode === '--rollback' ? $row['after'] : $row['before'])) throw new RuntimeException('Baseline changed: '.$row['path']);
    if (hash_file('sha256',__DIR__.'/'.$row['file']) !== $row['after']) throw new RuntimeException('Staged file changed');
    if ($mode !== '--rollback') {
        passthru('/opt/alt/php82/usr/bin/php -l '.escapeshellarg(__DIR__.'/'.$row['file']), $code);
        if ($code !== 0) throw new RuntimeException('Syntax failed');
        if (file_exists(__DIR__.'/'.$row['file'].'.before')) throw new RuntimeException('Backup already exists');
    } elseif ($row['before'] !== null && hash_file('sha256',__DIR__.'/'.$row['file'].'.before') !== $row['before']) throw new RuntimeException('Invalid backup');
}
if ($mode === '--dry-run') { echo "All four file guards passed.\n"; exit; }
foreach ($manifest as $row) {
    $target = $root.'/'.$row['path'];
    if ($mode === '--apply') {
        if ($row['before'] !== null) { copy($target,__DIR__.'/'.$row['file'].'.before'); chmod(__DIR__.'/'.$row['file'].'.before',0600); }
        $staged = $target.'.origin-stage'; copy(__DIR__.'/'.$row['file'],$staged); chmod($staged,is_file($target) ? fileperms($target)&0777 : 0644); rename($staged,$target);
    } elseif ($row['before'] !== null) {
        $staged = $target.'.origin-rollback'; copy(__DIR__.'/'.$row['file'].'.before',$staged); chmod($staged,fileperms($target)&0777); rename($staged,$target);
    }
    // New inactive classes are retained on rollback for in-flight requests; restored routes remove access.
}
chdir($root);
passthru('/opt/alt/php82/usr/bin/php artisan route:cache', $code);
if ($code !== 0) throw new RuntimeException('Route cache refresh failed; restore guarded files');
echo $mode." complete.\n";

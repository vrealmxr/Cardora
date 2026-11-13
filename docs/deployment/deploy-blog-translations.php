<?php
// One-off reviewed content addition. Default is a read-only dry run.
if (PHP_SAPI !== 'cli') exit(1);
$mode=$argv[1] ?? '--dry-run';
if (!in_array($mode,['--dry-run','--apply','--rollback'],true)) throw new RuntimeException('Unknown mode');
foreach (['blog-translation-manifest.json'=>'45edb77ea3394a8c73ebe8a863d312ee47d26c9dbc0602181f66c3aee66494c1',
    'blog-translation-patch.php'=>'7b6f2821c34d79c6e6dea9209d289d1fc28a5c108818ad7b61d395fc514f8a36'] as $file=>$hash) {
    if (!is_file(__DIR__.'/'.$file) || !hash_equals($hash,hash_file('sha256',__DIR__.'/'.$file))) throw new RuntimeException('Unreviewed deployment file');
}
$root='/home/u912666299/domains/cardora.gr/public_html/backend';
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/blog-translation-patch.php';
$backup=__DIR__.'/blog-content.before.json';
$rollback=$mode==='--rollback';
$file=$rollback ? $backup : __DIR__.'/blog-translation-manifest.json';
$manifest=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if (count($manifest)!==10) throw new RuntimeException('Unexpected article count');
$db=Illuminate\Support\Facades\DB::connection();
$result=$db->transaction(function() use ($db,$manifest,$mode,$rollback,$backup) {
    $plans=prepareBlogTranslationRows($db,$manifest,$rollback);
    if ($mode==='--apply') {
        $handle=fopen($backup,'x');
        if (!$handle) throw new RuntimeException('Backup already exists; inspect before reapplying');
        chmod($backup,0600);
        $saved=array_map(function($plan) { unset($plan['beforeRow'],$plan['afterContent']); return $plan; },$plans);
        $bytes=json_encode($saved,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (fwrite($handle,$bytes)!==strlen($bytes) || !fflush($handle)) throw new RuntimeException('Backup write failed');
        fclose($handle);
    }
    if ($mode!=='--dry-run') applyBlogTranslationRows($db,$plans);
    return array_map(fn($plan)=>['id'=>$plan['id'],'slug'=>$plan['slug'],'beforeSha256'=>$plan['beforeSha256'],'afterSha256'=>$plan['afterSha256']],$plans);
});
echo json_encode(['mode'=>$mode,'articles'=>$result,'changedFields'=>$mode==='--dry-run'?[]:['content.translations.en']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;

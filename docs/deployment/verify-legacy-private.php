<?php
declare(strict_types=1);
require '/home/u912666299/domains/cardora.gr/public_html/backend/vendor/autoload.php';
$app=require '/home/u912666299/domains/cardora.gr/public_html/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$row=App\Models\VerificationDocument::firstOrFail();
$out=['checked_at'=>gmdate(DATE_ATOM),'private_records'=>App\Models\VerificationDocument::where('storage_disk','legacy_verification')->count(),'http'=>[],'controller'=>[]];
foreach(['https://cardora.gr/storage/','https://api.cardora.gr/storage/','https://www.cardora.gr/storage/'] as $base) {
    $ch=curl_init($base.$row->storage_path);curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'Cardora-Privacy-Audit/1.0']);
    curl_exec($ch);$out['http'][]=['base'=>$base,'status'=>curl_getinfo($ch,CURLINFO_HTTP_CODE)];curl_close($ch);
}
$ch=curl_init('https://cardora.gr/admin/private-verification-document/'.$row->id);
curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'Cardora-Privacy-Audit/1.0']);
curl_exec($ch);$out['anonymous_download_status']=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
foreach(['guest','member','seo_editor','admin'] as $role) {
    $request=Illuminate\Http\Request::create('/');
    $user=$role==='guest'?null:new App\Models\User(['is_admin'=>$role==='admin','is_seo_editor'=>$role==='seo_editor']);
    $request->setUserResolver(fn()=>$user);
    try {
        $response=(new App\Http\Controllers\Web\LegacyVerificationDocumentController)($request,(string)$row->id);
        $out['controller'][$role]=['status'=>$response->getStatusCode(),'attachment'=>str_starts_with($response->headers->get('Content-Disposition',''),'attachment;'),'no_store'=>str_contains($response->headers->get('Cache-Control',''),'no-store'),'file_exists'=>$response->getFile()->isFile()];
    } catch(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {$out['controller'][$role]=['status'=>$e->getStatusCode()];}
}
$manifest=json_decode(file_get_contents('/home/u912666299/cardora-private/seo-legacy-privacy-20261025/private-migration-manifest.json'),true);
$private=config('filesystems.disks.legacy_verification.root');$public=storage_path('app/public');$ok=0;$exposed=0;
foreach($manifest['hashes'] as $path=>$hash) {$ok+=(int)(is_file($private.'/'.$path)&&hash_file('sha256',$private.'/'.$path)===$hash);$exposed+=(int)is_file($public.'/'.$path);}
$out['preserved_hashes']=$ok;$out['original_public_files_remaining']=$exposed;
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;

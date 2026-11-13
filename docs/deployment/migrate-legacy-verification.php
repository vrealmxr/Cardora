<?php
declare(strict_types=1);
$base='/home/u912666299/domains/cardora.gr/public_html/backend';
require $base.'/vendor/autoload.php';
$app=require $base.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source=realpath($base.'/storage/app/public');
$destination='/home/u912666299/cardora-private/legacy-verification';
if(config('filesystems.disks.legacy_verification.root')!==$destination || is_dir($destination))throw new RuntimeException('Unexpected destination/configuration');
$names=['verification-identity','verification-bank','verification-address'];
$rows=Illuminate\Support\Facades\DB::table('verification_documents')->get(['id','storage_disk','storage_path']);
if($rows->count()!==66)throw new RuntimeException('Document inventory changed');
foreach($rows as $row) {
    if($row->storage_disk!=='public' || !preg_match('#^uploads/verification-(identity|bank|address)/[A-Za-z0-9/_.-]+$#',$row->storage_path) || str_contains($row->storage_path,'..'))throw new RuntimeException('Unexpected document reference');
    $path=realpath($source.'/'.$row->storage_path);
    if(!$path || !str_starts_with($path,$source.'/uploads/') || !is_file($path))throw new RuntimeException('Missing or escaped document');
}
$hashes=[];
foreach($names as $name) {
    $dir=realpath($source.'/uploads/'.$name);
    if($dir!==$source.'/uploads/'.$name)throw new RuntimeException('Unexpected source folder');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $file) {
        if($file->isLink())throw new RuntimeException('Symlink not allowed');
        if($file->isFile())$hashes[substr($file->getPathname(),strlen($source)+1)]=hash_file('sha256',$file->getPathname());
    }
}
$record=__DIR__.'/private-migration-manifest.json';
if(file_exists($record))throw new RuntimeException('Manifest exists');
file_put_contents($record,json_encode(['before'=>$rows,'hashes'=>$hashes],JSON_PRETTY_PRINT));chmod($record,0600);
mkdir($destination,0700);mkdir($destination.'/uploads',0700);$moved=[];
try {
    Illuminate\Support\Facades\DB::transaction(function()use($source,$destination,$names,$rows,&$moved){
        $locked=Illuminate\Support\Facades\DB::table('verification_documents')->lockForUpdate()->get(['id','storage_disk','storage_path']);
        if(json_encode($locked)!==json_encode($rows))throw new RuntimeException('References changed');
        foreach($names as $name) {
            if(!rename($source.'/uploads/'.$name,$destination.'/uploads/'.$name))throw new RuntimeException('Move failed');
            $moved[]=$name;
        }
        $count=Illuminate\Support\Facades\DB::table('verification_documents')->whereIn('id',$rows->pluck('id'))->where('storage_disk','public')->update(['storage_disk'=>'legacy_verification']);
        if($count!==66)throw new RuntimeException('Unexpected updated count');
    });
} catch(Throwable $e) {
    foreach(array_reverse($moved) as $name) rename($destination.'/uploads/'.$name,$source.'/uploads/'.$name);
    throw $e;
}
foreach($hashes as $path=>$hash)if(hash_file('sha256',$destination.'/'.$path)!==$hash)throw new RuntimeException('Integrity mismatch');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $entry)chmod($entry->getPathname(),$entry->isDir()?0700:0600);
echo json_encode(['records_migrated'=>66,'files_preserved'=>count($hashes),'all_hashes_verified'=>true,'outside_web_root'=>true],JSON_PRETTY_PRINT).PHP_EOL;

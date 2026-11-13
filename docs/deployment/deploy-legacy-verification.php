<?php
declare(strict_types=1);
$base='/home/u912666299/domains/cardora.gr/public_html/backend';
$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
foreach($manifest as $item) {
    $target=$base.'/'.$item['target'];$payload=__DIR__.'/'.$item['source'];
    if (($item['before']===null ? file_exists($target) : hash_file('sha256',$target)!==$item['before']) || hash_file('sha256',$payload)!==$item['after']) throw new RuntimeException('Source changed; stop');
    exec('/opt/alt/php82/usr/bin/php -l '.escapeshellarg($payload).' 2>&1',$messages,$code);
    if($code!==0) throw new RuntimeException('Payload syntax error');
}
if(is_dir(__DIR__.'/before')) throw new RuntimeException('Previous deployment exists; inspect');
mkdir(__DIR__.'/before',0700);
// Install controller before references to it.
foreach(array_reverse($manifest) as $item) {
    $target=$base.'/'.$item['target'];
    if($item['before']!==null) {copy($target,__DIR__.'/before/'.$item['source']);chmod(__DIR__.'/before/'.$item['source'],0600);}
    $tmp=$target.'.privacy-tmp';if(file_exists($tmp))throw new RuntimeException('Temporary target exists');
    if(!copy(__DIR__.'/'.$item['source'],$tmp))throw new RuntimeException('Copy failed');
    chmod($tmp,is_file($target)?fileperms($target)&0777:0644);
    if(!rename($tmp,$target))throw new RuntimeException('Atomic rename failed');
}
echo "Installed four reviewed privacy files. Rebuild configuration/routes before migration.\n";

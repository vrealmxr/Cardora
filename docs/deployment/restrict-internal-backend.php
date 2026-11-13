<?php
declare(strict_types=1);
$target='/home/u912666299/domains/cardora.gr/public_html/.htaccess';
$old=file_get_contents($target);
if(hash('sha256',$old)!=='047bda598b7cb695db55265235d0c4b0fcc0a001fcefa1012ad061722d040fde')throw new RuntimeException('Root rules changed; inspect');
$needle="    RewriteBase /\n";
if(substr_count($old,$needle)!==1)throw new RuntimeException('Unexpected rule layout');
$new=str_replace($needle,$needle."\n    # Laravel internals are filesystem-only; public API enters via root index.php.\n    RewriteRule ^backend(?:/|$) - [F,L]\n",$old);
$backup=__DIR__.'/root.htaccess.before-private-guard';
if(file_exists($backup))throw new RuntimeException('Backup already exists');
file_put_contents($backup,$old);chmod($backup,0600);
$tmp=$target.'.private-guard-tmp';if(file_exists($tmp))throw new RuntimeException('Temporary file exists');
file_put_contents($tmp,$new);chmod($tmp,fileperms($target)&0777);
if(!rename($tmp,$target))throw new RuntimeException('Replacement failed');
echo 'Restricted internal backend URL subtree. SHA256 '.hash('sha256',$new).PHP_EOL;

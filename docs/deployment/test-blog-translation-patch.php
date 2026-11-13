<?php
require 'D:/CardoraGit/Cardora/backend/vendor/autoload.php';
require __DIR__.'/blog-translation-patch.php';
$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
$db = $capsule->getConnection();
$db->getSchemaBuilder()->create('blog_posts', function ($table) {
    $table->integer('id')->primary(); $table->string('slug'); $table->string('status');
    $table->text('content'); $table->string('title'); $table->string('published_at'); $table->string('updated_at');
});
$baseline = json_decode(ltrim(file_get_contents('D:/Cardora/RuntimeValidation/seo-content-20261023/blog-locales-baseline.json'), "\xEF\xBB\xBF"),true,512,JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents('D:/Cardora/RuntimeValidation/seo-content-20261023/blog-translation-manifest.json'),true,512,JSON_THROW_ON_ERROR);
foreach ($baseline as $i=>$row) {
    $raw = json_encode($row['content'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $db->table('blog_posts')->insert(['id'=>$row['id'],'slug'=>$row['slug'],'status'=>'published','content'=>$raw,
        'title'=>'Original title','published_at'=>'2026-04-01','updated_at'=>'2026-04-02']);
    $manifest[$i]['rawContentSha256']=hash('sha256',$raw);
}
$original=$db->table('blog_posts')->orderBy('id')->get()->toJson();
$checks=0;
$check=function($ok,$message) use (&$checks) { if (!$ok) throw new RuntimeException($message); $checks++; };
$plans=$db->transaction(fn()=>prepareBlogTranslationRows($db,$manifest));
$check($db->table('blog_posts')->orderBy('id')->get()->toJson()===$original,'Dry run changed data');
$db->transaction(fn()=>applyBlogTranslationRows($db,$plans));
foreach ($plans as $plan) {
    $before=json_decode($plan['beforeContent'],true); $after=json_decode($db->table('blog_posts')->find($plan['id'])->content,true);
    $check(isset($after['translations']['en']), 'Missing English'); unset($after['translations']['en']);
    $check($after===$before,'Changed original content/source');
}
try { $db->transaction(fn()=>prepareBlogTranslationRows($db,$manifest)); throw new LogicException('Reapply accepted'); }
catch (RuntimeException $e) { $check(true,'Reapply refused'); }
$db->transaction(function() use ($db,$plans) { applyBlogTranslationRows($db,prepareBlogTranslationRows($db,$plans,true)); });
$check($db->table('blog_posts')->orderBy('id')->get()->toJson()===$original,'Rollback did not restore exact rows');
$bad=$manifest; $bad[9]['rawContentSha256']=str_repeat('0',64);
try { $db->transaction(function() use ($db,$bad) { applyBlogTranslationRows($db,prepareBlogTranslationRows($db,$bad)); }); throw new LogicException('Stale article accepted'); }
catch (RuntimeException $e) { $check($db->table('blog_posts')->orderBy('id')->get()->toJson()===$original,'Partial write after stale article'); }
$bad=$manifest; $bad[0]['en']['sections'][0]['paragraphs']=[];
try { $db->transaction(fn()=>prepareBlogTranslationRows($db,$bad)); throw new LogicException('Missing paragraph accepted'); }
catch (RuntimeException $e) { $check(true,'Incomplete content refused'); }
$db->transaction(fn()=>applyBlogTranslationRows($db,$plans));
$db->table('blog_posts')->where('id',$plans[0]['id'])->update(['content'=>'{"edited":true}']);
try { $db->transaction(fn()=>prepareBlogTranslationRows($db,$plans,true)); throw new LogicException('Edited rollback accepted'); }
catch (RuntimeException $e) { $check(true,'Edited rollback refused'); }
echo json_encode(['checks'=>$checks,'articles'=>count($plans),'database'=>'memory-only SQLite','result'=>'pass']).PHP_EOL;

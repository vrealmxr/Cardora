<?php
declare(strict_types=1);
$base='/home/u912666299/domains/cardora.gr/public_html/backend';
require $base.'/vendor/autoload.php';
$app=require $base.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// This process uses the real deployed service with an entirely separate RAM database.
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
    'cache.default'=>'array','logging.default'=>'stderr','mail.default'=>'array','queue.default'=>'sync',
    'didit.enabled'=>true,'didit.api_key'=>'fixture-only','didit.base_url'=>'https://didit.invalid']);
Illuminate\Support\Facades\DB::purge('sqlite');
if(Illuminate\Support\Facades\DB::connection()->getDriverName()!=='sqlite'||Illuminate\Support\Facades\DB::connection()->getDatabaseName()!==':memory:')throw new RuntimeException('Unsafe test connection');
Illuminate\Support\Facades\Http::preventStrayRequests();
foreach(['2014_10_12_000000_create_users_table.php','2026_03_31_112439_add_admin_fields_to_users_table.php','2026_03_31_220000_add_locale_to_users_table.php','2026_03_31_230000_add_public_profile_fields_to_users_table.php','2026_07_01_120100_add_shipping_origin_to_users_table.php','2026_03_31_120000_create_verification_submissions_table.php','2026_03_31_120001_create_verification_documents_table.php','2026_04_03_190100_create_seller_payout_accounts_table.php','2026_09_27_050000_create_didit_verification_tables.php'] as $name)(require $base.'/database/migrations/'.$name)->up();
$checks=[];
function freshHttp(array $responses):void { Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory); Illuminate\Support\Facades\Http::preventStrayRequests(); Illuminate\Support\Facades\Http::fake($responses); }
function check($condition,string $name):void {global $checks;if(!$condition)throw new RuntimeException('Failed: '.$name);$checks[]=$name;}
$id=Illuminate\Support\Facades\DB::table('users')->insertGetId(['name'=>'Retention fixture','email'=>'retention@example.invalid','password'=>'unusable','is_verified_seller'=>true,'trust_status'=>'trusted','created_at'=>now(),'updated_at'=>now()]);
$old=App\Models\DiditSession::create(['user_id'=>$id,'session_id'=>'fixture-old','workflow_id'=>'fixture','environment'=>'sandbox','status'=>'Approved','verification_url'=>'https://example.invalid/fixture','notice_version'=>'fixture','consent_locale'=>'el','consented_at'=>now()->subYears(2),'retention_due_at'=>now()->subMinute()]);
$identity=App\Models\IdentityVerification::create(['user_id'=>$id,'source'=>'didit','status'=>'Approved','current_session_id'=>'fixture-old','expires_at'=>$old->retention_due_at,'verified_at'=>now()->subYear()]);
freshHttp(['*'=>Illuminate\Support\Facades\Http::sequence()->push([],503)->push(['face_retention_outcome'=>'deleted'],200)]);
$service=app(App\Services\DiditRetentionService::class);
check(Illuminate\Support\Facades\Artisan::call('didit:expire',['--delete'=>true])===1,'provider failure returns failure');
check(!$old->fresh()->session_deleted_at,'failed delete not recorded as complete');
check(!App\Models\User::find($id)->is_verified_seller,'expired account loses badge');
check($identity->fresh()->status==='Kyc Expired','expired status enforced');
Illuminate\Support\Facades\Artisan::call('didit:expire',['--delete'=>true]);
check(Illuminate\Support\Facades\Http::recorded()->count()===1,'retry throttled during first hour');
Illuminate\Support\Carbon::setTestNow(now()->addHour()->addSecond());
check(Illuminate\Support\Facades\Artisan::call('didit:expire',['--delete'=>true])===0,'retry succeeds after one hour');
check((bool)$old->fresh()->session_deleted_at&&$old->fresh()->verification_url==='','confirmed deletion clears verification URL');
check(Illuminate\Support\Facades\Http::recorded(fn($request)=>$request->method()==='DELETE'&&$request['retain_face_embeddings']===false)->count()===2,'biometric retention disabled in delete request');
Illuminate\Support\Facades\Artisan::call('didit:expire',['--delete'=>true]);check(Illuminate\Support\Facades\Http::recorded()->count()===2,'completed deletion is idempotent');
foreach([['retained_with_user',false],['deleted',true],['none',true],['ineligible_no_vendor_user',true]] as [$outcome,$expected]) {
    $session=$old->replicate(['session_deleted_at','deletion_attempted_at','deletion_requested_at']);$session->session_id='fixture-'.$outcome;$session->save();
    freshHttp(['*'=>Illuminate\Support\Facades\Http::response(['face_retention_outcome'=>$outcome],200)]);
    check($service->deleteExpiredSession($session)===$expected,'200 outcome '.$outcome.' handled explicitly');
}
$future=$old->replicate(['session_deleted_at','deletion_attempted_at','deletion_requested_at']);$future->session_id='fixture-new';$future->retention_due_at=now()->addYear();$future->save();
$identity->refresh()->update(['status'=>'Approved','current_session_id'=>$future->session_id,'expires_at'=>$future->retention_due_at,'verified_at'=>now()]);
Illuminate\Support\Facades\DB::table('users')->where('id',$id)->update(['is_verified_seller'=>true,'trust_status'=>'trusted']);
$retry=$old->replicate(['session_deleted_at','deletion_attempted_at','deletion_requested_at']);$retry->session_id='fixture-older';$retry->save();
freshHttp(['*'=>Illuminate\Support\Facades\Http::response([],204)]);
check($service->deleteExpiredSession($retry),'204 confirms session deletion');
check($identity->fresh()->status==='Approved'&&App\Models\User::find($id)->is_verified_seller,'old session deletion preserves renewed approval');
check(!$future->fresh()->session_deleted_at,'new session is untouched');
Illuminate\Support\Carbon::setTestNow();
echo json_encode(['database'=>':memory:','external_http'=>'all intercepted, stray requests prohibited','checks'=>count($checks),'passed'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;

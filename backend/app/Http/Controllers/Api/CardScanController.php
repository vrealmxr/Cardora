<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardScan;
use App\Services\CardScanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardScanController extends Controller
{
    public function mine(Request $request) {
        return response()->json(CardScan::where('user_id',$request->user()->id)->where('state','!=','deleted')
            ->where('expires_at','>',now())->latest()->take(50)->get()->map(fn($scan)=>$this->payload($scan)))
            ->header('Cache-Control','private, no-store');
    }
    public function config(CardScanService $service)
    {
        // Also perform expiry cleanup on scanner visits; this never contacts RunPod.
        $service->locked('intake',fn()=>$service->prune());
        return response()->json(['enabled'=>config('card_scans.enabled'),'categories'=>config('card_scans.categories'),
            'policy_version'=>config('card_scans.policy_version'),'user_daily_limit'=>config('card_scans.user_daily_limit')])
            ->header('Cache-Control','no-store');
    }
    public function store(Request $request, CardScanService $service)
    {
        abort_unless(config('card_scans.enabled'),503,'Το scanner δεν είναι ακόμη διαθέσιμο.');
        $data=$request->validate([
            'category'=>['required',Rule::in(array_keys(config('card_scans.categories')))],
            'request_key'=>'required|uuid', 'terms_accepted'=>'required|accepted',
            'image_preparation'=>['required',Rule::in(['auto-card-crop-v2','scan-only-v2'])],
            'policy_version'=>['required',Rule::in([config('card_scans.policy_version')])],
            'front'=>'required|file|mimes:jpeg,jpg,png,webp|max:10240',
            'back'=>'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'angled'=>'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);
        if (in_array($data['category'],['starwars','nba','soccer','euroleague'],true)) $request->validate(['back'=>'required|file']);
        $files=['front'=>$request->file('front')];
        foreach (['back','angled'] as $role) if ($request->hasFile($role)) $files[$role]=$request->file($role);
        // Legacy storage flag denotes eligibility for review, not GDPR consent.
        $data['training_opt_in']=$data['image_preparation']==='auto-card-crop-v2';
        $scan=$service->create($request->user()->id,$data,$files);
        $service->kick();
        return response()->json($this->payload($scan),202)->header('Cache-Control','no-store');
    }
    public function show(Request $request, string $id, CardScanService $service)
    {
        $scan=CardScan::where('user_id',$request->user()->id)->where('state','!=','deleted')->where('expires_at','>',now())->findOrFail($id);
        if ($scan->state==='queued') $service->kick();
        return response()->json($this->payload($scan))->header('Cache-Control','private, no-store');
    }
    public function destroy(Request $request, string $id, CardScanService $service)
    {
        $scan=CardScan::where('user_id',$request->user()->id)->findOrFail($id);
        return $service->locked('worker',function () use ($scan,$service) {
            return $service->locked('intake',function () use ($scan,$service) {
                $scan->refresh(); $service->removeImages($scan);
                // Keep a one-day quota tombstone so deletion cannot bypass GPU spending limits.
                $scan->update(['state'=>'deleted','prediction'=>null,'review'=>null,'training_opt_in'=>false,'expires_at'=>now()->addDay()]);
                return response()->json(['deleted'=>true]);
            });
        },false);
    }
    private function payload(CardScan $scan): array
    {
        return ['id'=>$scan->id,'state'=>$scan->state,'result'=>$scan->prediction,
            'error'=>$scan->state==='failed'?'Δεν ολοκληρώθηκε η αναγνώριση. Δοκίμασε αργότερα.':null,
            'training_opt_in'=>$scan->training_opt_in,'expires_at'=>$scan->expires_at->toIso8601String()];
    }
}

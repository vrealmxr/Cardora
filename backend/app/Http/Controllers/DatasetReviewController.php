<?php
namespace App\Http\Controllers;

use App\Models\CardScan;
use App\Services\CardScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DatasetReviewController extends Controller
{
    public function home() { return response()->file(resource_path('dataset-review/index.html')); }
    public function asset(string $file) {
        abort_unless(in_array($file,['review.css','review.js'],true),404);
        return response()->file(resource_path('dataset-review/'.$file),['Content-Type'=>$file==='review.js'?'text/javascript':'text/css']);
    }
    public function session() {
        return response()->json(['token'=>csrf_token(),
            'categories'=>collect(config('card_scans.categories'))->map(fn($label,$key)=>compact('key','label'))->values(),
            'root'=>'Ιδιωτική συλλογή Cardora','retention_days'=>30,'max_bytes'=>config('card_scans.max_bytes')]);
    }
    private function query() { return CardScan::where('training_opt_in',true)->where('state','completed')->where('expires_at','>',now()); }
    private function get(string $id): CardScan { return $this->query()->findOrFail($id); }
    private function record(CardScan $scan): array {
        return ['id'=>$scan->id,'category'=>$scan->category,'status'=>$scan->review_status,
            'revision'=>(string)$scan->revision,'created'=>$scan->created_at->timestamp,'duplicates'=>0,
            'review'=>$scan->review,'clearance'=>$scan->clearance,
            'metadata'=>['images'=>$scan->images,'prediction'=>$scan->prediction,'provenance'=>$scan->provenance]];
    }
    public function index(Request $request) {
        $request->validate(['status'=>['nullable',Rule::in(['all','pending','approved','rejected','ready'])],'q'=>'nullable|string|max:200','page'=>'nullable|integer|min:1']);
        $query=$this->query(); $counts=['all'=>(clone $query)->count()];
        foreach (['pending','approved','rejected'] as $status) $counts[$status]=(clone $query)->where('review_status',$status)->count();
        $counts['ready']=(clone $query)->where('review_status','approved')->where('clearance->card_only_verified',true)->count();
        $status=$request->input('status','all');
        if ($status==='ready') $query->where('review_status','approved')->where('clearance->card_only_verified',true);
        elseif ($status!=='all') $query->where('review_status',$status);
        if ($term=$request->input('q')) {
            $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$term).'%';
            $query->where(fn($q)=>$q->where('prediction','like',$term)->orWhere('review','like',$term)->orWhere('id','like',$term));
        }
        $page=(int)$request->input('page',1);$total=(clone $query)->count();
        $items=$query->latest()->skip(($page-1)*30)->take(30)->get()->map(function ($s) {
            $labels=$s->review['labels'] ?? $s->prediction['identity'] ?? [];
            return ['id'=>$s->id,'category'=>$labels['category'] ?? $s->category,'status'=>$s->review_status,
                'ready'=>$s->review_status==='approved' && ($s->clearance['card_only_verified'] ?? false)===true,'created'=>$s->created_at->timestamp,
                'name'=>$labels['name'] ?? null,'set_name'=>$labels['set_name'] ?? null,'collector_number'=>$labels['collector_number'] ?? null];
        });
        return response()->json(compact('items','total','page','counts'));
    }
    public function show(string $id) { return response()->json($this->record($this->get($id))); }
    public function image(string $id, int $index, CardScanService $service) {
        $scan=$this->get($id); $image=$scan->images[$index] ?? null; abort_unless($image,404);
        $path=$service->path($scan,$image['file']);
        abort_unless(is_file($path) && hash_file('sha256',$path)===$image['sha256'],409);
        return response()->file($path,['Content-Type'=>'image/png']);
    }
    public function review(Request $request, string $id) {
        $data=$request->validate(['revision'=>'required|integer|min:0','action'=>['required',Rule::in(['approve','reject'])],
            'reviewer'=>'required|string|max:120','evidence'=>'required|string|max:1500',
            'labels'=>'nullable|array:category,name,set_name,collector_number,language,parallel,finish,serial_number']);
        if ($data['action']==='approve') $request->validate([
            'labels.category'=>['required',Rule::in(array_keys(config('card_scans.categories')))],
            'labels.name'=>'required|string|max:300','labels.set_name'=>'required|string|max:300',
            'labels.collector_number'=>'required|string|max:300',
            'labels.language'=>'nullable|string|max:300','labels.parallel'=>'nullable|string|max:300',
            'labels.finish'=>'nullable|string|max:300','labels.serial_number'=>'nullable|string|max:300']);
        return DB::transaction(function () use ($request,$data,$id) {
            $scan=$this->query()->lockForUpdate()->findOrFail($id);
            abort_unless((string)$scan->revision===(string)$data['revision'],409,'Η εγγραφή άλλαξε. Άνοιξέ την ξανά.');
            $scan->update(['review_status'=>$data['action']==='approve'?'approved':'rejected',
                'review'=>['reviewer'=>$data['reviewer'],'admin_id'=>$request->user()->id,'at'=>time(),
                    'labels'=>$data['action']==='approve'?$data['labels']:null,'evidence'=>$data['evidence']],
                'clearance'=>null,'revision'=>$scan->revision+1]);
            return response()->json($this->record($scan));
        });
    }
    public function clearance(Request $request, string $id) {
        $data=$request->validate(['revision'=>'required|integer|min:0','reviewer'=>'required|string|max:120','basis'=>'required|string|max:1500','card_only_verified'=>'required|accepted']);
        return DB::transaction(function () use ($request,$data,$id) {
            $scan=$this->query()->lockForUpdate()->findOrFail($id);
            abort_unless($scan->review_status==='approved' && (string)$scan->revision===(string)$data['revision'],409);
            $scan->update(['clearance'=>['reviewer'=>$data['reviewer'],'basis'=>$data['basis'],'card_only_verified'=>true,'admin_id'=>$request->user()->id,'at'=>time()],
                'revision'=>$scan->revision+1]);
            return response()->json($this->record($scan));
        });
    }
    public function export(CardScanService $service) {
        return $service->locked('intake',function () use ($service) {
            $scans=$this->query()->where('review_status','approved')->where('clearance->card_only_verified',true)->get();
            abort_if($scans->isEmpty(),422,'Δεν υπάρχουν εγκεκριμένα δείγματα.');
            abort_if($scans->sum('bytes')>200*1024*1024,422,'Το export ξεπερνά τα 200 MB. Χρειάζεται εξαγωγή από διαχειριστή στον server.');
            $id=(string)Str::uuid(); $path=$service->root().'/export-'.$id.'.zip';
            $zip=new \ZipArchive();
            abort_unless($zip->open($path,\ZipArchive::CREATE|\ZipArchive::EXCL)===true,503);
            try {
                $records=[];
                foreach ($scans as $scan) {
                    $labels=$scan->review['labels'];$images=[];
                    foreach ($scan->images as $image) {
                        $source=$service->path($scan,$image['file']);
                        abort_unless(hash_file('sha256',$source)===$image['sha256'],409);
                        $image['file']='images/'.$scan->id.'/'.$image['file'];
                        $zip->addFile($source,$image['file']);$images[]=$image;
                    }
                    $records[]=['id'=>$scan->id,'category'=>$labels['category'] ?? $scan->category,'labels'=>$labels,
                        'images'=>$images,'provenance'=>$scan->provenance,'split'=>'unassigned',
                        'identity_group'=>hash('sha256',json_encode([$labels['category'] ?? $scan->category,
                            mb_strtolower(trim($labels['name'])),mb_strtolower(trim($labels['set_name'])),mb_strtolower(trim($labels['collector_number']))])),
                        'usage_policy'=>$scan->policy_version];
                }
                $zip->addFromString('manifest.json',json_encode(['schema'=>'cardora-reviewed-public-v1','created'=>time(),
                    'requires_leakage_review'=>true,'records'=>$records],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
                $zip->close();chmod($path,0600);
                return response()->download($path,'cardora-dataset-'.$id.'.zip',['Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
            } catch (\Throwable $e) { $zip->close(); if (is_file($path)) unlink($path);throw $e; }
        });
    }
}

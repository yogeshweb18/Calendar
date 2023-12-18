<?php

namespace Acme\Calendar\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Axistrustee\ComplianceOverview\Models\ComplianceCovenant;
use Axistrustee\ComplianceOverview\Models\ComplianceInstance;
use Axistrustee\ComplianceOverview\Http\Controllers\ComplianceController;
use Axistrustee\Covenants\Http\Controllers\CovenantController;
use DB;
use Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;
//use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class CalendarController extends Controller
{
    //

    public function fetchData(Request $request)
    {

       /* $tracking_data = DB::select("
            SELECT
                ci.id, ci.covenantId, ci.status, ci.activateDate as trackingDate, ci.is_child, ci.is_fail, ci.reminderBefore, ci.reminderInterval, ci.dueDate,
                compliances_covenants.complianceId, compliances_covenants.type, compliances_covenants.subType,
                compliances.clcode, compliances.docName, compliances.startDate, compliances.endDate 
            FROM
                compliances_covenants_instances as ci 
                LEFT JOIN compliances_covenants ON ci.covenantId = compliances_covenants.id
                LEFT JOIN compliances ON compliances_covenants.complianceId = compliances.id WHERE compliances.userId = ".$current_user."
        ");*/


        $user_object = \Auth::user();
        //print_r($user_object->role->role);die;
        $current_user = $user_object->id;
        $user_role = $user_object->role->role;
        $organization_id = $user_object->organization_id;
        $viewOnly = 0;
        $today = date("Y-m-d");
        $query = DB::table('compliances_covenants_instances as ci');
        $query->join('compliances_covenants as cc', 'ci.covenantId', '=', 'cc.id');
        $query->join('compliances as c', 'ci.complianceId', '=', 'c.id');
        $query->join('clients', 'c.clientReference', '=', 'clients.id');
        $query->whereRaw('1 = 1');

        if($user_role == config('global.roles.ADMIN') || $user_role == config('global.roles.AUDITOR')) {
            $viewOnly = 1;
            $key = 'c.organization_id';
            $value = $organization_id;
            $query->where($key,$value);
        }
        else if($user_role == config('global.roles.CSOG_MAKER') || $user_role == config('global.roles.CCU_MAKER')) {
            $key = 'c.userId';
            $value = $current_user;
            $query->where($key,$value);
        }
        else if($user_role == config('global.roles.CCU_MAKER')) {
            $key = 'c.organization_id';
            $value = $organization_id;
            $query->where($key,$value);
        }
        else if($user_role == config('global.roles.CCU_CHECKER') || $user_role == config('global.roles.CSOG_CHECKER')) {
            $viewOnly = 1;
            $key = 'c.organization_id';
            $value = $organization_id;
            $query->where($key,$value);
        }
        else if($user_role == config('global.roles.SUPER_ADMIN')) {
            $viewOnly = 1;
        }
        $query->where('cc.covenantStatus','Approved');
        $tracking_data = $query->get(['ci.id','cc.complianceId','ci.covenantId','c.clcode','clients.name','cc.type','cc.subType','cc.frequency','ci.activateDate as trackingDate','ci.is_child', 'ci.is_fail', 'ci.reminderBefore', 'ci.reminderInterval','ci.dueDate','cc.targetValue','ci.resolution_value','ci.status','cc.description','cc.comments','c.inconsistencyTreatment','c.secured','c.clcode','c.docName',DB::raw('DATE_FORMAT(c.startDate, "%d-%m-%Y") as startDate'),DB::raw('DATE_FORMAT(c.endDate, "%d-%m-%Y") as endDate'),'c.mailCC','ci.uploads']);

        $CovenantController = new CovenantController();
        foreach ($tracking_data as $key=>$cc) {
            $status = $CovenantController->get_instance_status($cc->status);
            $tracking_data[$key]->statusDisplay = $status;
            if(empty($cc->uploads) || $cc->uploads == []) {
                $tracking_data[$key]->uploads = '-';
            }
            else {
                $tracking_data[$key]->uploads = json_decode($tracking_data[$key]->uploads,true);
            }
        }


        $result['viewOnly'] = $viewOnly;
        $result['tracking_data'] = $tracking_data;
        echo json_encode($result);
        die;
    }

   /* public function encryptKey(Request $request) {
       $publicKey = $request->get('publicKey');//client public key
        $publicKey = base64_decode($publicKey);
        $pubKey = file_get_contents(base_path()."/rsa_2048_pub.pem");//server's public key
        $res=openssl_get_publickey($pubKey);
        $keyData = openssl_pkey_get_details($res);

        $serverPublicKey = $keyData['key'];
        $serverPublicKey = base64_encode($serverPublicKey);
        $serverPublicKey = base64_decode($serverPublicKey);

        $sharedKey = 'FUKyw2BKb9rsRTpvUsP1X1zeuSISxwQ6';

        /*$pubKey = file_get_contents(base_path()."/rsa_2048_priv.pem");//server's public key
        $res=openssl_get_privatekey($pubKey);
        $keyData = openssl_pkey_get_details($res);*/
        //print_r(openssl_pkey_get_public($pubKey));die;
        /*openssl_public_encrypt(
            $sharedKey,
            $encrypted_data,
            openssl_pkey_get_public($pubKey)
        );
        //print_r(openssl_error_string());
        //print_r(base64_encode($encrypted_data));

        $privKey = file_get_contents(base_path()."/rsa_2048_priv.pem");//server's public key
        $res=openssl_get_privatekey($privKey);
        $keyData = openssl_pkey_get_details($res);

        openssl_private_decrypt(
            $encrypted_data,
            $decrypted_data,
            openssl_pkey_get_private($privKey)
        );
        print_r(openssl_error_string());
        print_r($decrypted_data);
    }*/

    public function encryptKey(Request $request) {
        $publicKey = $request->get('publicKey');//client public key
        $publicKey = base64_decode($publicKey);
        $pubKey = file_get_contents(base_path()."/rsa_2048_pub.pem");//server's public key
        $res=openssl_get_publickey($pubKey);
        $keyData = openssl_pkey_get_details($res);

        $serverPublicKey = $keyData['key'];//server's public key

        $uuid = Str::uuid();

        DB::table('encrypt_store')
        ->insert([
            'uuid' => $uuid,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        return response()->json([
            'encryptedKey' => base64_encode($serverPublicKey),
            'uuid' => $uuid, // uuid string
        ]);
    }

    public function submitResult(Request $request) {
        /*$uuid = $request->uuid;
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $sharedKey = DB::select("
            SELECT * from encrypt_store WHERE uuid = '$uuid' and expires_at > '$now'
        ");

        if(!$sharedKey) return response()->json(['error' => 'invalid sharedKey'], 422);

        $encryptedData = $request->input('mailCC');
        $encryptedEmail = base64_decode($encryptedData);
        $encryptedSharedKey = $request->input('encryptKey');
        $encryptedKey = base64_decode($encryptedSharedKey);
        $privKey = file_get_contents(base_path()."/rsa_2048_priv.pem");

        $decrypted_key='';
        $decryptedEmail='';

        openssl_private_decrypt(
            $encryptedKey,
            $decrypted_key,
            openssl_pkey_get_private($privKey)
        );

        if($decrypted_key) {
            $iv = mb_substr($encryptedEmail,0, 16, '8bit');
            $encrypted = mb_substr($encryptedEmail, 16, null, '8bit');
            $decryptedEmail = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $decrypted_key,
                OPENSSL_RAW_DATA,
                $iv
            );
        }

        var_dump($decryptedEmail);
die;*/
        $user_object = \Auth::user();
        //print_r($user_object->role->role);die;
        $current_user_id = $user_object->id;
        $status = $request->status;

        $request->validate([
            'resolution' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]+$/'],
            'comments' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]+$/'],
        ]);

        $resolution = $request->resolution;
        $comments = $request->comments;
        $instanceId = $request->instanceId;
        $covenantId = $request->covenantId;
        $resolutionStatus = $request->resolutionStatus;
        $isChild = $request->is_child;
        $isFail = ($request->is_fail != 'null') ? $request->is_fail : 0;
        $dueDate = $request->dueDate;;
        $result = [];
        $result['is_defined_failed'] = 0;
        $result['tracking_on'] = 1;
        $fileUris = array();

        if($request->hasFile('files')) {
            foreach($request->file('files') as $file) {
                
                $name = time().'_'.$file->getClientOriginalName();
                $path = Storage::disk('s3')->put($name, file_get_contents($file));
                $path = Storage::disk('s3')->url($path);

                array_push($fileUris, $name);
            }
        }

        $instance = ComplianceInstance::find($instanceId);
        $instance->status = $resolutionStatus;
        $instance->update([
            'resolution_value' => $resolution,
            'approvalStatus' => 'Not Sent',
            "uploads"=> json_encode($fileUris),
            'comments'=>$comments,
            'resolver' => $current_user_id
        ]);

        if($resolutionStatus == 'pass' && $isChild != 1) {
            $result['tracking_on'] = 0;
        }

        if($resolutionStatus == 'fail') {
            $subType = $request->subType;
            if($isFail == 0) {
                $covenant_guide = DB::table('standard_covenants')
                ->where('sub_type',$subType)
                ->get(['failed_covenant'])
                ->first();
//print_r($covenant_guide);die;
                if(!empty($covenant_guide->failed_covenant)){
                    $result['is_defined_failed'] =  1;
                    $failed_covenant = json_decode($covenant_guide->failed_covenant,true);
                    $result['failed_covenant'] = $failed_covenant[0];
                }
            }

            if($isChild == 1) {
                $parent_instance = DB::select("
                SELECT
                    id as associated_id 
                FROM
                    compliances_covenants_instances 
                    where covenantId =".$covenantId." AND is_child = 0 AND dueDate > ".$dueDate." ORDER BY dueDate asc limit 1
                ");

                $parent_instance_update = ComplianceInstance::FindOrFail($parent_instance[0]->associated_id);
                $parent_instance_update->setAttribute('status', 'fail');
                $parent_instance_update->update();

                $instance->update([
                    'associated_instance' => $parent_instance[0]->associated_id
                ]);
            }
            
        }
        $result['status'] = true;

        return json_encode($result);
        die;

    }

    public function fetchCovenant(Request $request)
    {
        $standard_covenants = DB::table('standard_covenants')
            ->select('type')
            ->groupBy('type')
            ->get();

        $covenant_data = [];
        $i = 0;
        foreach ($standard_covenants as $covenant) {
            $covenant_data[$i]['type'] = $covenant->type;
            $i++;
        }

        echo json_encode($covenant_data);
        die;
    }

    public function notifyIfFail(Request $request)
    {
        $covenant_id = $request->input('covenantId');
        try{
            $covenant = ComplianceCovenant::FindOrFail($covenant_id);
            $target_value = str_replace(' ', '', $covenant->targetValue);
            $resolution = str_replace(' ', '', $request->input('resolution'));
            //echo $covenant->maintained_as;die;
            switch($covenant->maintained_as) {
                case('Percentage not less than'):
                case('Percentage not less'):
                    if($resolution < $target_value) {
                        return false;
                    }
                    return true;
                break;
                case('Percentage not exceeding than'):
                case('Percentage not exceeding'):
                    if($resolution > $target_value) {
                        return false;
                    }
                    return true;
                break;

                case('Ratio not exceeding than'):
                case('Ratio not exceeding'):
                case('Ratio Not exceeding'):
                    $target_value_arr = explode(":",$target_value);
                    $resolution_arr = explode(":",$resolution);
                    $target_value_value = 0;
                    $resolution_value = 0;
                    $status = true;
                    if(!empty($target_value_arr)) {
                        $target_value_value = $target_value_arr[0]/$target_value_arr[1];
                    }
                    if(!empty($resolution_arr)) {
                        $resolution_value = $resolution_arr[0]/$resolution_arr[1];
                    }
                    if($resolution_value > $target_value_value) {

                        return false;
                    }
                    else 
                        return true;
                    die;
                break;

                case('Ratio not less than'):
                case('Ratio not less'):
                    $target_value_arr = explode(":",$target_value);
                    $resolution_arr = explode(":",$resolution);
                    $target_value_value = 0;
                    $resolution_value = 0;
                    $status = true;
                    if(!empty($target_value_arr)) {
                        $target_value_value = $target_value_arr[0]/$target_value_arr[1];
                    }
                    if(!empty($resolution_arr)) {
                        $resolution_value = $resolution_arr[0]/$resolution_arr[1];
                    }
                    if($resolution_value > $target_value_value) {

                        return false;
                    }
                    else 
                        return true;
                    die;
                break;

                default:
                        return true;
               }
        }
        catch(\Exception $e){
                return $e->getMessage();
            }
        die;
    }

    public function saveFailCovenant(Request $request) {
        //print_r($request->all());die;
        $instanceData = [];
        $activateBefore = 30;
        $instanceData['dueDate'] = $request->input('dueDate');
        $instanceData['failed_covenant'] = $request->input('failed_covenant');
        $ts1 = strtotime('-'.$activateBefore.' days',strtotime($instanceData['dueDate']));
        $activateDate = date('Y-m-d',$ts1);
        $applicableMonth = date('F', strtotime($instanceData['dueDate']));
        $instanceData['activateDate'] = $activateDate;
        $instanceData['complianceId'] = $request->input('complianceId');
        $instanceData['covenantId'] = $request->input('covenantId');
        $instanceData['instanceNo'] = 1;
        $instanceData['is_fail'] = 1;
        $instanceData['applicableMonth'] = $applicableMonth;
        $instanceData['associated_instance'] = $request->input('instanceId');
        $instanceData['reminderBefore'] = $request->input('reminderBefore');
        $instanceData['reminderInterval'] = $request->input('reminderInterval');
        $instanceData['fail_label'] = $instanceData['failed_covenant']['label'];
        foreach ($instanceData['failed_covenant']['parameters'] as $key => $value) {
            $instanceData[$value['key']] = $value['value'];
        }
        $ComplianceController = new ComplianceController();
        $complianceInstance = new ComplianceInstance($instanceData);
        $complianceInstance->status = 'Pending';
        DB::beginTransaction();
        try{
            $complianceInstance->save();
            $complianceInstanceId = $complianceInstance->id;

            $ComplianceController->addReminder($complianceInstanceId,$instanceData['dueDate'],$instanceData['reminderBefore'],$instanceData['reminderInterval']);
            DB::commit();
            return true;
        }
        catch(\Exception $e){
            DB::rollBack();
            return $e->getMessage();
        }
        
    }
}

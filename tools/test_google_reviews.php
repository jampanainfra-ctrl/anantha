<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$temporary=sys_get_temp_dir().'/anantha-google-test-'.bin2hex(random_bytes(8)).'.php';
putenv('ANANTHA_GOOGLE_SETTINGS_PATH='.$temporary);
require_once __DIR__.'/../includes/config.php';
$checks=0;
function google_check(bool $condition,string $label): void{global $checks;if(!$condition)throw new RuntimeException('FAILED: '.$label);$checks++;}
try{
    google_check(str_contains(google_api_failure_message(['error'=>['details'=>[['reason'=>'SERVICE_DISABLED']]]],403),'Enable Places API (New)'),'disabled API produces a specific setup instruction');
    google_check(str_contains(google_api_failure_message(['error'=>['message'=>'Billing must be enabled.']],403),'Billing must be enabled.'),'Google rejection reason is retained for diagnosis');
    google_check(str_contains(google_api_failure_message([],403),'HTTP 403'),'non-JSON rejection has a useful fallback');
    google_check(!google_reviews_configured(),'missing key reports disconnected');
    google_check(google_reviews_data()['reviews']===[],'disconnected integration returns no invented reviews');
    google_check(str_starts_with(GOOGLE_REVIEW_URL,'https://maps.app.goo.gl/'),'venue listing link available without a key');
    $key='test_only_key_not_a_real_credential_12345';
    save_google_reviews_settings(['api_key'=>$key,'place_id'=>'ChIJ-test-listing']);
    google_check(google_reviews_settings()['api_key']===$key,'private connection saved');
    save_google_reviews_settings(['api_key'=>'','place_id'=>'ChIJ-test-listing-2']);
    google_check(google_reviews_settings()['api_key']===$key,'blank key preserves existing credential');
    foreach([['api_key'=>"bad\r\nkey",'place_id'=>'ChIJ-test-listing'],['api_key'=>$key,'place_id'=>'https://example.test/place'],['api_key'=>$key,'place_id'=>' ChIJ-test-listing']] as $input){
        $rejected=false;try{save_google_reviews_settings($input);}catch(RuntimeException $e){$rejected=true;}
        google_check($rejected,'invalid connection rejected');
    }
    google_check(google_reviews_settings()['place_id']==='ChIJ-test-listing-2','failed save keeps previous settings');
    google_check(google_review_safe_url('javascript:alert(1)')===''&&google_review_safe_url('http://example.test')==='','unsafe links rejected');
    $payload=['rating'=>4.6,'userRatingCount'=>127,'googleMapsUri'=>'https://maps.google.com/?cid=123','reviews'=>[
        ['authorAttribution'=>['displayName'=>'Fixture Reviewer <unsafe>','uri'=>'https://www.google.com/maps/contrib/123'],'rating'=>4,'text'=>['text'=>'A real response shape, not a published review. <script>bad</script>'],'googleMapsUri'=>'https://www.google.com/maps/reviews/123'],
        ['authorAttribution'=>['displayName'=>'Original language reviewer','uri'=>'javascript:alert(1)'],'rating'=>5,'originalText'=>['text'=>'Original review text.']],
        ['rating'=>3,'text'=>['text'=>'   ']]
    ]];
    $result=google_places_reviews_payload($payload);
    google_check(count($result['reviews'])===2,'text reviews mapped and empty text skipped');
    google_check($result['rating']===4.6&&$result['total']===127,'Google aggregate rating and count preserved');
    google_check($result['reviews'][0]['author_url']==='https://www.google.com/maps/contrib/123','author attribution URL retained');
    google_check($result['reviews'][1]['author_url']===''&&$result['reviews'][1]['review_text']==='Original review text.','original text fallback and unsafe author link handled');
    google_check($result['reviews'][0]['source']==='google','review source is Google');
    $GLOBALS['fixture']=$result+['configured'=>true,'error'=>''];
    $_SERVER['REQUEST_METHOD']='GET';
    foreach(['index.php','gallery.php','review.php'] as $page){
        $_SERVER['SCRIPT_NAME']='/anantha/'.$page;
        $source=file_get_contents(__DIR__.'/../'.$page);
        $source=str_replace(['__DIR__','google_reviews_data()'],[var_export(str_replace('\\','/',realpath(__DIR__.'/..')),true),'$GLOBALS["fixture"]'],$source);
        ob_start();eval('?>'.$source);$html=ob_get_clean();
        google_check(str_contains($html,'Fixture Reviewer &lt;unsafe&gt;')&&!str_contains($html,'<script>bad</script>'),$page.' escapes Google author and text');
        google_check(str_contains($html,'https://www.google.com/maps/contrib/123')&&str_contains($html,'translate="no"'),$page.' displays author attribution and Maps label');
        google_check(!str_contains($html,'Sravani K.')&&!str_contains($html,'Mounika Reddy'),$page.' does not mix in local sample reviews');
    }
    echo 'PASS: '.$checks." Google review checks (fixtures and temporary settings; no Google API calls).\n";
}finally{if(is_file($temporary))unlink($temporary);putenv('ANANTHA_GOOGLE_SETTINGS_PATH');}

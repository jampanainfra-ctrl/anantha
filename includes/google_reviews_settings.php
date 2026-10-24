<?php
/** Server-only Google Maps connection settings. */
function google_reviews_settings_path(): string
{
    return getenv('ANANTHA_GOOGLE_SETTINGS_PATH') ?: __DIR__.'/../storage/google-reviews-settings.php';
}
function google_reviews_settings(): array
{
    $path=google_reviews_settings_path();
    $saved=is_file($path) ? require $path : [];
    return array_replace(['api_key'=>'','place_id'=>'','review_url'=>'https://maps.app.goo.gl/dug6jos7PEtAs1Qq6'],is_array($saved)?$saved:[]);
}
function google_review_safe_url(string $url): string
{
    return filter_var($url,FILTER_VALIDATE_URL) && strtolower((string)parse_url($url,PHP_URL_SCHEME))==='https' ? $url : '';
}
function save_google_reviews_settings(array $input): void
{
    reject_required_leading_spaces($input,['place_id'=>'Google Place ID']);
    $saved=google_reviews_settings();
    $key=(string)($input['api_key'] ?? '');
    if($key==='')$key=$saved['api_key'];
    if($key==='' && defined('GOOGLE_PLACES_API_KEY'))$key=GOOGLE_PLACES_API_KEY;
    if(!preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$key))throw new RuntimeException('Please enter a valid Google API key.');
    $placeId=trim((string)($input['place_id'] ?? ''));
    if(!preg_match('/^[A-Za-z0-9_-]{5,255}$/D',$placeId))throw new RuntimeException('Please enter the Google Place ID, rather than a Maps URL.');
    $settings=['api_key'=>$key,'place_id'=>$placeId,'review_url'=>$saved['review_url']];
    $path=google_reviews_settings_path();
    $temporary=tempnam(dirname($path),'google-settings-');
    if($temporary===false)throw new RuntimeException('Google connection could not be saved.');
    try{
        if(file_put_contents($temporary,"<?php\n// Private Google connection. Do not commit.\nreturn ".var_export($settings,true).";\n",LOCK_EX)===false)throw new RuntimeException('Google connection could not be saved.');
        @chmod($temporary,0600);
        if(!rename($temporary,$path))throw new RuntimeException('Google connection could not be saved.');
        if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
    }finally{if(is_file($temporary))unlink($temporary);}
}

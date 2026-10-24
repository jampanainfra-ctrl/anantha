<?php
/** Public URL generation is independent of the internal PHP filenames. */
function app_base_path(): string
{
    $override=getenv('ANANTHA_BASE_PATH');
    if($override!==false)return rtrim('/'.trim($override,'/'),' /');
    static $base=null;
    if($base!==null)return $base;
    $root=str_replace('\\','/',realpath(dirname(__DIR__))?:dirname(__DIR__));
    $documentRoot=!empty($_SERVER['DOCUMENT_ROOT'])?realpath((string)$_SERVER['DOCUMENT_ROOT']):false;
    if($documentRoot){
        $documentRoot=rtrim(str_replace('\\','/',$documentRoot),'/');
        if(strcasecmp($root,$documentRoot)===0)return $base='';
        if(strncasecmp($root,$documentRoot.'/',strlen($documentRoot)+1)===0)return $base='/'.trim(substr($root,strlen($documentRoot)),'/');
    }
    return $base='/anantha';
}

function app_url(string $url='',string $context=''): string
{
    if($url!==''&&(preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i',$url)||$url[0]==='#'||$url[0]==='?'))return $url;
    preg_match('/^([^?#]*)(.*)$/s',$url,$parts);
    $path=$parts[1]??'';$suffix=$parts[2]??'';$base=app_base_path();
    if($path!==''&&$path[0]==='/'){
        if($base!==''&&($path===$base||str_starts_with($path,$base.'/'))){$path=substr($path,strlen($base));$context='';}
        elseif(!preg_match('~^/(?:admin|ceoadmin|api|galary|gallery)(?:/|$)~',$path))return $url;
        $path=ltrim($path,'/');
    }
    $fromRoot=str_starts_with($path,'../');
    while(str_starts_with($path,'../'))$path=substr($path,3);
    while(str_starts_with($path,'./'))$path=substr($path,2);
    if($context==='admin'&&!$fromRoot&&$path!==''&&!preg_match('~^(?:admin|ceoadmin|api|assets|uploads|storage)(?:/|$)~',$path))$path='admin/'.$path;
    $path=preg_replace('~^admin(?:/|$)~','ceoadmin/',$path);
    if(preg_match('~^ceoadmin/(?:login|index)(?:\.php|/)?$~',$path))$path='ceoadmin/';
    elseif(preg_match('~^api/index(?:\.php|/)?$~',$path))$path='api/';
    elseif(preg_match('~^(?:index(?:\.php|/)?)?$~',$path))$path='';
    elseif(preg_match('~^(?:gallery(?:\.php|/)?|galary/?)$~',$path))$path='galary/';
    elseif(str_ends_with($path,'.php'))$path=substr($path,0,-4).'/';
    elseif($path!==''&&pathinfo(rtrim($path,'/'),PATHINFO_EXTENSION)==='')$path=rtrim($path,'/').'/';
    return $base.'/'.ltrim($path,'/').$suffix;
}

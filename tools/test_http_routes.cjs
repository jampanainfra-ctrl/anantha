// Read-only local HTTP checks. Run while Apache is serving this checkout.
const fs=require('node:fs');
const path=require('node:path');
const base=(process.argv[2]||'http://localhost/anantha').replace(/\/$/,'');
(async()=>{
  const failures=[];let checks=0;const assets=new Set();
  const get=async(route,expected=200,redirect='follow')=>{
    const response=await fetch(base+route,{redirect,signal:AbortSignal.timeout(15000)});const body=await response.text();checks++;
    if(response.status!==expected||/Fatal error:|Parse error:|Warning: (?:Undefined|Cannot|Trying)/.test(body))failures.push({route,status:response.status,expected});
    return {response,body};
  };
  const pages=fs.readdirSync(path.resolve(__dirname,'..')).filter(name=>name.endsWith('.php')).map(name=>name==='index.php'?'/':name==='gallery.php'?'/galary/':'/'+name.slice(0,-4)+'/');
  for(let i=0;i<pages.length;i+=4){
    const results=await Promise.all(pages.slice(i,i+4).map(route=>get(route)));
    for(const {body} of results)for(const match of body.matchAll(/(?:src|href)=["']([^"']+)["']/g)){
      try{const url=new URL(match[1],base+'/');if(url.origin===new URL(base).origin&&/\/(?:assets|uploads)\//.test(url.pathname))assets.add(url.pathname+url.search);}catch{}
    }
  }
  const basePath=new URL(base).pathname;
  for(const asset of assets){const route=asset.startsWith(basePath)?asset.slice(basePath.length):asset;await get(route);}
  for(const page of ['dashboard','bookings','booking-form','packages','referrals','rate-card','terms','reviews','gallery','blog-images','email','actions','logout']){
    const {response}=await get('/ceoadmin/'+page+'/',302,'manual');if(!(response.headers.get('location')||'').endsWith('/ceoadmin/'))failures.push({page,error:'unauthenticated admin route did not require login'});
  }
  for(const endpoint of ['bookings','payments','referral-lookup','client-document'])await get('/api/'+endpoint+'/',401);
  await get('/api/customer-lookup/',405);await get('/api/availability/?month=2026-99',422);await get('/api/availability/?date=2026-02-31',422);await get('/api/pincode/?pin=12',422);
  const {body}=await get('/api/reviews/');if(JSON.parse(body).source!=='google')failures.push({route:'/api/reviews/',error:'review source is not Google'});
  for(const route of ['/.git/config','/.env','/database.sql','/composer.lock','/includes/config.php','/tools/test_email.php','/storage/mail-settings.php','/uploads/clients/no-document.png'])await get(route,403);
  if(failures.length)throw Error(JSON.stringify(failures));
  console.log('PASS: '+checks+' read-only HTTP checks ('+pages.length+' public pages, '+assets.size+' local assets, admin/API access and private files).');
})().catch(error=>{console.error(error);process.exitCode=1;});

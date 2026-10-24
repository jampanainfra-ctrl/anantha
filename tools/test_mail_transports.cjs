// Local-only SMTP sink for PHPMailer SMTP and Windows PHP mail() integration.
const net=require('node:net');
const {spawn}=require('node:child_process');
const path=require('node:path');
if(process.platform!=='win32')throw Error('Run this native PHP mail integration test on the Windows/XAMPP test environment.');
let messages=0;
const server=net.createServer(socket=>{
  socket.setEncoding('utf8');socket.write('220 localhost test SMTP\r\n');let buffer='',data=false;
  socket.on('data',chunk=>{
    buffer+=chunk;let end;
    while((end=buffer.indexOf('\r\n'))>=0){
      const line=buffer.slice(0,end);buffer=buffer.slice(end+2);
      if(data){if(line==='.'){data=false;messages++;socket.write('250 accepted locally\r\n');}continue;}
      if(/^(EHLO|HELO)/i.test(line))socket.write('250-localhost\r\n250 SIZE 10485760\r\n');
      else if(/^(MAIL FROM|RCPT TO|RSET|NOOP)/i.test(line))socket.write('250 OK\r\n');
      else if(/^DATA/i.test(line)){data=true;socket.write('354 End with dot\r\n');}
      else if(/^QUIT/i.test(line))socket.end('221 bye\r\n');
      else socket.write('502 unsupported\r\n');
    }
  });
});
server.listen(0,'127.0.0.1',()=>{
  const port=String(server.address().port);
  const php=spawn(process.argv[2]||'D:/xampp/php/php.exe',['-d','SMTP=127.0.0.1','-d','smtp_port='+port,path.join(__dirname,'test_email.php'),port],{cwd:path.resolve(__dirname,'..'),windowsHide:true,stdio:'inherit'});
  php.on('error',error=>{console.error(error.message);server.close();process.exitCode=1;});
  php.on('exit',code=>{server.close();if(code!==0||messages!==2){console.error('Mail transport test failed:',code,messages);process.exitCode=1;}else console.log('Local sink accepted two test emails. No external messages were sent.');});
});

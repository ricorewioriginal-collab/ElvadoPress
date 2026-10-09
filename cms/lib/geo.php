<?php
// Minimaler MaxMind-DB-(mmdb)-Leser für die DB-IP-Lite-Stadtdatenbank. Liest per fseek, lädt die Datei nie komplett in den Speicher.
// IP-Adressen werden nur für die Abfrage umgerechnet und nirgends gespeichert. Daten: IP Geolocation by DB-IP (CC BY 4.0).
final class ElvadoMmdb {
    private $fh;private array $meta=[];private int $nodes=0;private int $recBits=0;private int $nodeBytes=0;private int $treeSize=0;private int $dataStart=0;private ?int $v4Start=null;
    public static function open(string $file): ?self {
        if(!is_file($file)||filesize($file)<1000)return null;
        $o=new self();$o->fh=@fopen($file,'rb');if(!$o->fh)return null;
        $size=filesize($file);$tail=min($size,131072);fseek($o->fh,$size-$tail);$buf=(string)fread($o->fh,$tail);
        $p=strrpos($buf,"\xAB\xCD\xEFMaxMind.com");if($p===false)return null;
        $off=0;$o->meta=$o->decode(substr($buf,$p+14),$off,0);
        $o->nodes=(int)($o->meta['node_count']??0);$o->recBits=(int)($o->meta['record_size']??0);
        if($o->nodes<1||!in_array($o->recBits,[24,28,32],true))return null;
        $o->nodeBytes=intdiv($o->recBits*2,8);$o->treeSize=$o->nodes*$o->nodeBytes;$o->dataStart=$o->treeSize+16;
        return $o;
    }
    public function __destruct(){if($this->fh)fclose($this->fh);}
    public function lookup(string $ip): ?array {
        $bin=@inet_pton($ip);if($bin===false)return null;
        $bits=strlen($bin)===4?32:128;$node=0;
        if(strlen($bin)===4&&(int)($this->meta['ip_version']??6)===6){
            if($this->v4Start===null){$n=0;for($i=0;$i<96&&$n<$this->nodes;$i++)$n=$this->record($n,0);$this->v4Start=$n;}
            $node=$this->v4Start;
        }
        for($i=0;$i<$bits&&$node<$this->nodes;$i++){$bit=(ord($bin[$i>>3])>>(7-($i&7)))&1;$node=$this->record($node,$bit);}
        if($node<=$this->nodes)return null;
        $ptr=$node-$this->nodes-16;if($ptr<0)return null;
        fseek($this->fh,$this->dataStart+$ptr);$chunk=(string)fread($this->fh,4096);$off=0;
        $r=$this->decode($chunk,$off,$this->dataStart+$ptr);return is_array($r)?$r:null;
    }
    private function record(int $node,int $bit): int {
        fseek($this->fh,$node*$this->nodeBytes);$b=(string)fread($this->fh,$this->nodeBytes);if(strlen($b)<$this->nodeBytes)return $this->nodes;
        $a=array_values(unpack('C*',$b));
        if($this->recBits===24)return $bit?($a[3]<<16|$a[4]<<8|$a[5]):($a[0]<<16|$a[1]<<8|$a[2]);
        if($this->recBits===28)return $bit?((($a[3]&0x0F)<<24)|$a[4]<<16|$a[5]<<8|$a[6]):((($a[3]&0xF0)<<20)|$a[0]<<16|$a[1]<<8|$a[2]);
        return $bit?($a[4]<<24|$a[5]<<16|$a[6]<<8|$a[7]):($a[0]<<24|$a[1]<<16|$a[2]<<8|$a[3]);
    }
    // Decoder; $abs = absolute file offset of $buf[0] (nur für Pointer in den Datenbereich), 0 bei Metadaten
    private function decode(string $buf,int &$off,int $abs,int $depth=0){
        if($depth>8||$off>=strlen($buf))return null;
        $c=ord($buf[$off++]);$type=$c>>5;
        if($type===1){ // Pointer
            $sz=($c>>3)&3;$v=$c&7;
            if($sz===0){$p=($v<<8)|ord($buf[$off]);$off+=1;}
            elseif($sz===1){$p=(($v<<16)|(ord($buf[$off])<<8)|ord($buf[$off+1]))+2048;$off+=2;}
            elseif($sz===2){$p=(($v<<24)|(ord($buf[$off])<<16)|(ord($buf[$off+1])<<8)|ord($buf[$off+2]))+526336;$off+=3;}
            else{$p=(ord($buf[$off])<<24)|(ord($buf[$off+1])<<16)|(ord($buf[$off+2])<<8)|ord($buf[$off+3]);$off+=4;}
            if($abs===0){$o2=$p;return $this->decode($buf,$o2,0,$depth+1);}
            fseek($this->fh,$this->dataStart+$p);$ch=(string)fread($this->fh,2048);$o2=0;
            return $this->decode($ch,$o2,$this->dataStart+$p,$depth+1);
        }
        if($type===0){$type=7+ord($buf[$off++]);}
        $len=$c&31;
        if($len===29){$len=29+ord($buf[$off++]);}
        elseif($len===30){$len=285+((ord($buf[$off])<<8)|ord($buf[$off+1]));$off+=2;}
        elseif($len===31){$len=65821+((ord($buf[$off])<<16)|(ord($buf[$off+1])<<8)|ord($buf[$off+2]));$off+=3;}
        switch($type){
            case 2: $s=substr($buf,$off,$len);$off+=$len;return $s;
            case 3: $s=substr($buf,$off,8);$off+=8;return unpack('E',$s)[1];
            case 4: $off+=$len;return '';
            case 5: case 6: case 9: case 10: $v=0;for($i=0;$i<$len;$i++)$v=($v<<8)|ord($buf[$off+$i]);$off+=$len;return $v;
            case 8: $v=0;for($i=0;$i<$len;$i++)$v=($v<<8)|ord($buf[$off+$i]);$off+=$len;return $v;
            case 14: return $len===1;
            case 15: $s=substr($buf,$off,4);$off+=4;return unpack('G',$s)[1];
            case 7: $m=[];for($i=0;$i<$len;$i++){$k=$this->decode($buf,$off,$abs,$depth+1);$m[(string)$k]=$this->decode($buf,$off,$abs,$depth+1);}return $m;
            case 11: $a=[];for($i=0;$i<$len;$i++)$a[]=$this->decode($buf,$off,$abs,$depth+1);return $a;
            default: return null;
        }
    }
}

const ELVADO_GEO_DE_STATES=['Baden-Württemberg'=>'Baden-Württemberg','Bavaria'=>'Bayern','Berlin'=>'Berlin','Brandenburg'=>'Brandenburg','Bremen'=>'Bremen','Hamburg'=>'Hamburg','Hesse'=>'Hessen','Mecklenburg-Vorpommern'=>'Mecklenburg-Vorpommern','Mecklenburg-Western Pomerania'=>'Mecklenburg-Vorpommern','Lower Saxony'=>'Niedersachsen','North Rhine-Westphalia'=>'Nordrhein-Westfalen','Rhineland-Palatinate'=>'Rheinland-Pfalz','Saarland'=>'Saarland','Saxony'=>'Sachsen','Saxony-Anhalt'=>'Sachsen-Anhalt','Schleswig-Holstein'=>'Schleswig-Holstein','Thuringia'=>'Thüringen','Baden-Wurttemberg'=>'Baden-Württemberg'];

function elvado_geo_file(string $dataDir): string { return elvado_apps_dir($dataDir).'/geo.mmdb'; }
function elvado_geo_status(string $dataDir): array {
    $f=elvado_geo_file($dataDir);return ['installed'=>is_file($f)&&filesize($f)>1000000,'size'=>is_file($f)?(int)filesize($f):0,'updated'=>is_file($f)?gmdate('c',(int)filemtime($f)):''];
}
// IP → "DE|Bayern|München" (Land|Bundesland/Region|Stadt); '' wenn unbekannt oder keine Datenbank
function elvado_geo_lookup(string $dataDir,string $ip): string {
    static $db=false;if($db===false)$db=ElvadoMmdb::open(elvado_geo_file($dataDir));
    if(!$db||$ip==='')return '';
    try{$r=$db->lookup($ip);}catch(Throwable $e){return '';}
    if(!$r)return '';
    $cc=strtoupper((string)($r['country']['iso_code']??''));if(!preg_match('/^[A-Z]{2}$/',$cc))return '';
    $reg=(string)($r['subdivisions'][0]['names']['en']??'');if($cc==='DE')$reg=ELVADO_GEO_DE_STATES[$reg]??$reg;
    $city=(string)($r['city']['names']['de']??$r['city']['names']['en']??'');
    $clean=fn($s)=>str_replace('|','',mb_substr(trim(preg_replace('/\s*\(.*$/u','',strip_tags($s))),0,60));
    return $cc.'|'.$clean($reg).'|'.$clean($city);
}
// Aktuelle Monatsdatei laden (bei Fehler Vormonat); streamt gzip → Datei, ersetzt die alte erst nach Erfolg
function elvado_geo_download(string $dataDir): array {
    @set_time_limit(600);$dest=elvado_geo_file($dataDir);$tmp=$dest.'.part';
    foreach([gmdate('Y-m'),gmdate('Y-m',strtotime('first day of last month'))] as $ym){
        $url="https://download.db-ip.com/free/dbip-city-lite-$ym.mmdb.gz";
        $in=@gzopen($url,'rb');if(!$in)continue;$out=@fopen($tmp,'wb');if(!$out){gzclose($in);return ['ok'=>false,'message'=>'Datei nicht schreibbar.'];}
        $n=0;while(!gzeof($in)){$c=gzread($in,1048576);if($c===false||$c==='')break;fwrite($out,$c);$n+=strlen($c);}
        gzclose($in);fclose($out);
        if($n>20000000&&ElvadoMmdb::open($tmp)){@rename($tmp,$dest);return ['ok'=>true,'month'=>$ym,'size'=>$n];}
        @unlink($tmp);
    }
    return ['ok'=>false,'message'=>'Download nicht möglich (Netzwerk gesperrt oder Datei nicht erreichbar).'];
}

<?php
// WordPress-Verbinder (Einstellungen → Verbinder / Connectors) und KI-Zentrale: die API-Schlüssel der KI-Anbieter stehen nur an einer Stelle,
// in der KI-Zentrale (cms/data/.ai/gateway.json). Lesen liefert den zentralen Schlüssel; Speichern über die WordPress-Oberfläche oder -REST-API
// schreibt in die KI-Zentrale und legt keine zweite Kopie in den WordPress-Optionen ab.
if(!function_exists('rrw_wp_ai_central')){
    function rrw_wp_ai_central(){
        if(!function_exists('rrw_wp_cms_dir'))return null;
        if(!class_exists('Elvado\\Ai\\AiGatewayConfig',false)){$auto=dirname(__DIR__,3).'/src/autoload.php';if(!is_file($auto))return null;require_once $auto;}
        try{return \Elvado\Ai\AiGatewayConfig::load(rrw_wp_cms_dir(),[]);}catch(Throwable $e){return null;}
    }
    /** Anbieter-Kennung des Verbinders → Kennung der KI-Zentrale (nur Anbieter, die es dort gibt). */
    function rrw_wp_ai_central_id(string $connectorId): string {
        $c=rrw_wp_ai_central();if($c===null)return '';
        $id=$connectorId==='gemini'?'google':$connectorId;
        return isset($c->catalog()[$id])?$id:'';
    }
    foreach(['anthropic','google','openai','openrouter','deepseek','groq','mistral','cerebras','huggingface','nvidia','sambanova','github'] as $rrwAiId){
        $opt='connectors_ai_'.$rrwAiId.'_api_key';
        add_filter('pre_option_'.$opt,static function($pre) use($rrwAiId){
            $c=rrw_wp_ai_central();if($c===null)return $pre;$id=rrw_wp_ai_central_id($rrwAiId);
            $k=$id!==''?$c->ownKey($id):'';return $k!==''?$k:$pre;
        });
        add_filter('pre_update_option_'.$opt,static function($value) use($rrwAiId){
            $c=rrw_wp_ai_central();$id=rrw_wp_ai_central_id($rrwAiId);if($c===null||$id==='')return $value;
            $v=trim((string)$value);if($v===''||preg_match('/^\x{2022}+/u',$v))return $value;   // leer/maskiert: nichts ändern
            try{$c->save(['providers'=>[$id=>['api_key'=>$v]]]);return '';}catch(Throwable $e){return $value;}
        },10,1);
    }
}

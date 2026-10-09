<?php
// WordPress-Verbinder (Einstellungen → Verbinder / Connectors) und KI-Zentrale: die API-Schlüssel der KI-Anbieter stehen nur an einer Stelle,
// in der KI-Zentrale (cms/data/.ai/gateway.json). Lesen liefert den zentralen Schlüssel; Speichern über die WordPress-Oberfläche oder -REST-API
// schreibt in die KI-Zentrale und legt keine zweite Kopie in den WordPress-Optionen ab.
if(!function_exists('elvado_wp_ai_central')){
    function elvado_wp_ai_central(){
        if(!function_exists('elvado_wp_cms_dir'))return null;
        if(!class_exists('Elvado\\Ai\\AiGatewayConfig',false)){$auto=dirname(__DIR__,3).'/src/autoload.php';if(!is_file($auto))return null;require_once $auto;}
        try{return \Elvado\Ai\AiGatewayConfig::load(elvado_wp_cms_dir(),[]);}catch(Throwable $e){return null;}
    }
    /** Anbieter-Kennung des Verbinders → Kennung der KI-Zentrale (nur Anbieter, die es dort gibt). */
    function elvado_wp_ai_central_id(string $connectorId): string {
        $c=elvado_wp_ai_central();if($c===null)return '';
        $id=$connectorId==='gemini'?'google':$connectorId;
        return isset($c->catalog()[$id])?$id:'';
    }
    foreach(['anthropic','google','openai','openrouter','deepseek','groq','mistral','cerebras','huggingface','nvidia','sambanova','github'] as $elvadoAiId){
        $opt='connectors_ai_'.$elvadoAiId.'_api_key';
        add_filter('pre_option_'.$opt,static function($pre) use($elvadoAiId){
            $c=elvado_wp_ai_central();if($c===null)return $pre;$id=elvado_wp_ai_central_id($elvadoAiId);
            $k=$id!==''?$c->ownKey($id):'';return $k!==''?$k:$pre;
        });
        add_filter('pre_update_option_'.$opt,static function($value) use($elvadoAiId){
            $c=elvado_wp_ai_central();$id=elvado_wp_ai_central_id($elvadoAiId);if($c===null||$id==='')return $value;
            $v=trim((string)$value);if($v===''||preg_match('/^\x{2022}+/u',$v))return $value;   // leer/maskiert: nichts ändern
            try{$c->save(['providers'=>[$id=>['api_key'=>$v]]]);return '';}catch(Throwable $e){return $value;}
        },10,1);
    }
}

<?php
declare(strict_types=1);
// Formulare: Definition und Bereinigung, Ausgabe, Absenden (Validierung, Spam-Schutz, Speichern, E-Mail, Webhook), Einsendungen.

namespace ElvadoPlugin\Forms;

use Elvado\Plugin\Context;
use Elvado\Plugin\Fs;

final class Forms
{
    public const TYPES = ['text' => 'Text', 'email' => 'E-Mail', 'tel' => 'Telefon', 'number' => 'Zahl', 'textarea' => 'Mehrzeiliger Text', 'select' => 'Auswahlliste', 'radio' => 'Auswahl (eine Option)', 'checkbox' => 'Checkbox', 'file' => 'Datei-Upload'];
    private const UPLOAD_EXT = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'], 'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'doc' => ['application/msword', 'application/octet-stream', 'application/x-ole-storage'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream', 'application/x-ole-storage'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream']];

    public function __construct(private readonly Context $np) {}

    private function dir(string $sub = ''): string { return $this->np->dataDir($sub); }
    private function s(string $k): mixed { return $this->np->setting($k); }
    private static function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    // ---------------------------------------------------------------- Definitionen

    public function all(): array
    {
        $d = Fs::readJson($this->dir() . '/forms.json', ['forms' => []]);
        return is_array($d['forms'] ?? null) ? array_values($d['forms']) : [];
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $f) {
            if (($f['id'] ?? '') === $id) {
                return $f;
            }
        }
        return null;
    }

    private static function txt(mixed $v, int $max): string { return mb_substr(trim(strip_tags((string)$v)), 0, $max); }

    /** @return array{0:?array,1:list<string>} bereinigtes Formular oder Fehlertexte */
    public static function clean(array $in, ?array $old = null): array
    {
        $e = [];
        $id = strtolower(trim((string)($in['id'] ?? '')));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,38}$/', $id)) {
            $e[] = 'Die Kennung besteht aus 2–39 Kleinbuchstaben, Ziffern oder Bindestrichen (z. B. kontakt).';
        }
        $title = self::txt($in['title'] ?? '', 80);
        if ($title === '') {
            $e[] = 'Bitte einen Titel angeben.';
        }
        $fields = [];
        $seen = [];
        foreach (array_slice((array)($in['fields'] ?? []), 0, 40) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $type = (string)($f['type'] ?? 'text');
            $fid = strtolower(trim((string)($f['id'] ?? '')));
            $label = self::txt($f['label'] ?? '', 120);
            if (!isset(self::TYPES[$type])) {
                $e[] = 'Unbekannter Feldtyp bei „' . $label . '“.';
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_]{0,30}$/', $fid) || isset($seen[$fid])) {
                $e[] = 'Die Kennung des Feldes „' . $label . '“ ist ungültig oder doppelt (Kleinbuchstaben, Ziffern, _).';
                continue;
            }
            if ($label === '') {
                $e[] = 'Ein Feld hat keine Beschriftung.';
                continue;
            }
            $seen[$fid] = 1;
            $o = ['id' => $fid, 'type' => $type, 'label' => $label, 'required' => !empty($f['required']), 'placeholder' => self::txt($f['placeholder'] ?? '', 120), 'help' => self::txt($f['help'] ?? '', 200)];
            if (in_array($type, ['select', 'radio'], true) || ($type === 'checkbox' && !empty($f['options']))) {
                $opts = [];
                foreach (array_slice(is_array($f['options'] ?? null) ? $f['options'] : preg_split('/\R/', (string)($f['options'] ?? '')), 0, 30) as $op) {
                    $op = self::txt($op, 100);
                    if ($op !== '' && !in_array($op, $opts, true)) {
                        $opts[] = $op;
                    }
                }
                if (!$opts && $type !== 'checkbox') {
                    $e[] = 'Das Feld „' . $label . '“ braucht mindestens eine Option.';
                }
                $o['options'] = $opts;
            }
            $fields[] = $o;
        }
        if (!$fields) {
            $e[] = 'Das Formular braucht mindestens ein Feld.';
        }
        $url = trim((string)($in['redirect_url'] ?? ''));
        if ($url !== '' && !preg_match('~^(/(?!/)[^\s"\'<>]{0,300}|https://[^\s"\'<>]{3,300})$~', $url)) {
            $e[] = 'Die Weiterleitungs-Adresse muss ein Pfad (/danke/) oder eine https-Adresse sein.';
            $url = '';
        }
        $to = trim((string)($in['notify']['to'] ?? ''));
        if ($to !== '' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Der Empfänger ist keine gültige E-Mail-Adresse.';
            $to = '';
        }
        $replyField = (string)($in['notify']['reply_to_field'] ?? '');
        if ($replyField !== '' && !isset($seen[$replyField])) {
            $replyField = '';
        }
        $wh = trim((string)($in['webhook']['url'] ?? ''));
        if ($wh !== '' && !preg_match('~^https://[A-Za-z0-9.-]+(:\d+)?(/[^\s"\'<>]*)?$~', $wh)) {
            $e[] = 'Die Webhook-Adresse muss mit https:// beginnen.';
            $wh = '';
        }
        $secret = (string)($in['webhook']['secret'] ?? '');
        $oldSecret = (string)($old['webhook']['secret'] ?? '');
        $secret = $secret === '' ? $oldSecret : ($secret === '__clear__' ? '' : mb_substr($secret, 0, 100));
        if ($e) {
            return [null, $e];
        }
        $now = date(DATE_ATOM);
        return [[
            'id' => $id, 'title' => $title, 'fields' => $fields, 'submit_label' => self::txt($in['submit_label'] ?? '', 40) ?: 'Absenden',
            'success_message' => self::txt($in['success_message'] ?? '', 300) ?: 'Danke! Deine Nachricht wurde gesendet.', 'redirect_url' => $url,
            'store' => !empty($in['store']), 'retention_days' => max(1, min(3650, (int)($in['retention_days'] ?? 90))),
            'notify' => ['enabled' => !empty($in['notify']['enabled']), 'to' => $to, 'subject' => self::txt($in['notify']['subject'] ?? '', 120) ?: 'Neue Nachricht: {title}', 'reply_to_field' => $replyField],
            'consent' => ['enabled' => !empty($in['consent']['enabled']), 'text' => self::txt($in['consent']['text'] ?? '', 300) ?: 'Ich stimme der Verarbeitung meiner Angaben zur Beantwortung meiner Anfrage zu.', 'link' => preg_match('~^(/(?!/)[^\s"\'<>]{0,200}|https://[^\s"\'<>]{3,200})$~', (string)($in['consent']['link'] ?? '')) ? (string)$in['consent']['link'] : ''],
            'spam' => ['honeypot' => !array_key_exists('honeypot', (array)($in['spam'] ?? [])) || !empty($in['spam']['honeypot']), 'min_seconds' => max(0, min(60, (int)($in['spam']['min_seconds'] ?? 3))), 'rate_limit' => max(1, min(100, (int)($in['spam']['rate_limit'] ?? 5))), 'captcha' => !empty($in['spam']['captcha'])],
            'webhook' => ['url' => $wh, 'secret' => $secret],
            'created' => (string)($old['created'] ?? $now), 'updated' => $now,
        ], []];
    }

    public function save(array $in): array
    {
        $old = $this->find(strtolower(trim((string)($in['id'] ?? ''))));
        [$f, $e] = self::clean($in, $old);
        if ($f === null) {
            return ['ok' => false, 'message' => implode(' ', $e)];
        }
        $all = array_values(array_filter($this->all(), static fn($x) => ($x['id'] ?? '') !== $f['id']));
        if (count($all) >= 50) {
            return ['ok' => false, 'message' => 'Es sind höchstens 50 Formulare möglich.'];
        }
        $all[] = $f;
        usort($all, static fn($a, $b) => strcmp($a['title'], $b['title']));
        Fs::writeJson($this->dir() . '/forms.json', ['forms' => $all]);
        @chmod($this->dir() . '/forms.json', 0600);
        return ['ok' => true, 'message' => 'Formular gespeichert.', 'form' => self::publicForm($f)];
    }

    public function delete(string $id, bool $withData): bool
    {
        $all = $this->all();
        $left = array_values(array_filter($all, static fn($x) => ($x['id'] ?? '') !== $id));
        if (count($left) === count($all)) {
            return false;
        }
        Fs::writeJson($this->dir() . '/forms.json', ['forms' => $left]);
        if ($withData && preg_match('/^[a-z0-9-]+$/', $id)) {
            @unlink($this->dir('submissions') . '/' . $id . '.jsonl');
            Fs::rmTree($this->dir('uploads') . '/' . $id);
        }
        return true;
    }

    /** Für den Browser: Webhook-Geheimnis nie ausliefern. */
    public static function publicForm(array $f): array
    {
        $f['webhook']['secret_set'] = ($f['webhook']['secret'] ?? '') !== '';
        unset($f['webhook']['secret']);
        return $f;
    }

    // ---------------------------------------------------------------- Token (Zeitfalle, Rechenfrage)

    private function salt(): string
    {
        $f = $this->dir() . '/salt.txt';
        $s = is_file($f) ? trim((string)file_get_contents($f)) : '';
        if (strlen($s) < 32) {
            $s = bin2hex(random_bytes(24));
            @file_put_contents($f, $s, LOCK_EX);
            @chmod($f, 0600);
        }
        return $s;
    }

    public function token(string $formId, ?int $ts = null, string $extra = ''): string
    {
        $ts ??= time();
        return $ts . '.' . substr(hash_hmac('sha256', $formId . '|' . $ts . '|' . $extra, $this->salt()), 0, 24);
    }

    /** @return int Alter in Sekunden oder -1, wenn ungültig/abgelaufen */
    public function tokenAge(string $formId, string $token, string $extra = ''): int
    {
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{24})$/', $token, $m)) {
            return -1;
        }
        $age = time() - (int)$m[1];
        return hash_equals($this->token($formId, (int)$m[1], $extra), $token) && $age >= 0 && $age < 43200 ? $age : -1;
    }

    private function honeypotName(string $formId): string { return 'hp_' . substr(hash_hmac('sha256', 'hp|' . $formId, $this->salt()), 0, 8); }

    // ---------------------------------------------------------------- Ausgabe

    /** Shortcode [elvado_form id="…"]. */
    public function render(string $id): string
    {
        $f = $this->find($id);
        if ($f === null) {
            return '<!-- Formular nicht gefunden -->';
        }
        static $assets = false;
        $h = '';
        if (!$assets) {
            $assets = true;
            $h .= $this->assets();
        }
        $uid = 'ef-' . $f['id'] . '-' . bin2hex(random_bytes(2));
        $h .= '<form class="elvado-form" id="' . $uid . '" data-form="' . self::h($f['id']) . '" data-ok="' . self::h($f['success_message']) . '" data-redirect="' . self::h($f['redirect_url']) . '" novalidate method="post" action="#">';
        $h .= '<div class="ef-msg" role="status" aria-live="polite" hidden></div>';
        foreach ($f['fields'] as $fl) {
            $fid = $uid . '-' . $fl['id'];
            $req = $fl['required'] ? ' required aria-required="true"' : '';
            $star = $fl['required'] ? ' <span class="ef-req" aria-hidden="true">*</span>' : '';
            $h .= '<div class="ef-field ef-' . self::h($fl['type']) . '">';
            switch ($fl['type']) {
                case 'textarea':
                    $h .= '<label for="' . $fid . '">' . self::h($fl['label']) . $star . '</label><textarea id="' . $fid . '" name="' . self::h($fl['id']) . '" rows="5" maxlength="5000" placeholder="' . self::h($fl['placeholder']) . '"' . $req . '></textarea>';
                    break;
                case 'select':
                    $h .= '<label for="' . $fid . '">' . self::h($fl['label']) . $star . '</label><select id="' . $fid . '" name="' . self::h($fl['id']) . '"' . $req . '><option value="">Bitte wählen …</option>';
                    foreach ($fl['options'] as $o) {
                        $h .= '<option>' . self::h($o) . '</option>';
                    }
                    $h .= '</select>';
                    break;
                case 'radio':
                    $h .= '<fieldset><legend>' . self::h($fl['label']) . $star . '</legend>';
                    foreach ($fl['options'] as $i => $o) {
                        $h .= '<label class="ef-opt"><input type="radio" name="' . self::h($fl['id']) . '" value="' . self::h($o) . '"' . ($i === 0 ? $req : '') . '> ' . self::h($o) . '</label>';
                    }
                    $h .= '</fieldset>';
                    break;
                case 'checkbox':
                    if (!empty($fl['options'])) {
                        $h .= '<fieldset><legend>' . self::h($fl['label']) . $star . '</legend>';
                        foreach ($fl['options'] as $o) {
                            $h .= '<label class="ef-opt"><input type="checkbox" name="' . self::h($fl['id']) . '[]" value="' . self::h($o) . '"> ' . self::h($o) . '</label>';
                        }
                        $h .= '</fieldset>';
                    } else {
                        $h .= '<label class="ef-opt"><input type="checkbox" name="' . self::h($fl['id']) . '" value="1"' . $req . '> ' . self::h($fl['label']) . $star . '</label>';
                    }
                    break;
                case 'file':
                    $h .= '<label for="' . $fid . '">' . self::h($fl['label']) . $star . '</label><input type="file" id="' . $fid . '" name="' . self::h($fl['id']) . '"' . $req . '>';
                    break;
                default:
                    $h .= '<label for="' . $fid . '">' . self::h($fl['label']) . $star . '</label><input type="' . ($fl['type'] === 'number' ? 'number' : ($fl['type'] === 'tel' ? 'tel' : ($fl['type'] === 'email' ? 'email' : 'text'))) . '" id="' . $fid . '" name="' . self::h($fl['id']) . '" maxlength="500" placeholder="' . self::h($fl['placeholder']) . '"' . ($fl['type'] === 'email' ? ' autocomplete="email"' : '') . $req . '>';
            }
            if ($fl['help'] !== '') {
                $h .= '<small class="ef-help">' . self::h($fl['help']) . '</small>';
            }
            $h .= '</div>';
        }
        if ($f['spam']['captcha']) {
            $a = random_int(2, 9);
            $b = random_int(2, 9);
            $ts = time();
            $h .= '<div class="ef-field ef-captcha"><label for="' . $uid . '-cap">Sicherheitsfrage: Wie viel ist ' . $a . ' + ' . $b . '? <span class="ef-req" aria-hidden="true">*</span></label><input type="text" inputmode="numeric" id="' . $uid . '-cap" name="_captcha" autocomplete="off" required>'
                . '<input type="hidden" name="_ctoken" value="' . self::h($a . '.' . $b . '.' . $this->token($f['id'], $ts, $a . '+' . $b)) . '"></div>';
        }
        if ($f['consent']['enabled']) {
            $link = $f['consent']['link'] !== '' ? ' <a href="' . self::h($f['consent']['link']) . '" target="_blank" rel="noopener">Datenschutzerklärung</a>' : '';
            $h .= '<div class="ef-field ef-consent"><label class="ef-opt"><input type="checkbox" name="_consent" value="1" required> ' . self::h($f['consent']['text']) . $link . ' <span class="ef-req" aria-hidden="true">*</span></label></div>';
        }
        if ($f['spam']['honeypot']) {
            $h .= '<div class="ef-hp" aria-hidden="true" style="position:absolute;left:-5000px;width:1px;height:1px;overflow:hidden"><label>Bitte leer lassen<input type="text" name="' . $this->honeypotName($f['id']) . '" tabindex="-1" autocomplete="off"></label></div>';
        }
        $h .= '<input type="hidden" name="_token" value="' . self::h($this->token($f['id'])) . '">';
        $h .= '<div class="ef-actions"><button type="submit" class="ef-submit">' . self::h($f['submit_label']) . '</button></div></form>';
        return $h;
    }

    private function assets(): string
    {
        $css = '.elvado-form{max-width:640px;display:grid;gap:14px;position:relative}.elvado-form .ef-field{display:grid;gap:6px}.elvado-form label,.elvado-form legend{font-weight:600}.elvado-form .ef-opt{font-weight:400;display:flex;gap:8px;align-items:center}'
            . '.elvado-form input[type=text],.elvado-form input[type=email],.elvado-form input[type=tel],.elvado-form input[type=number],.elvado-form select,.elvado-form textarea,.elvado-form input[type=file]{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #b9bdc9;border-radius:8px;font:inherit;background:#fff;color:#111}'
            . '.elvado-form fieldset{border:0;padding:0;margin:0;display:grid;gap:6px}.elvado-form .ef-req{color:#c0392b}.elvado-form .ef-help{opacity:.75}.elvado-form .ef-submit{padding:11px 22px;border:0;border-radius:8px;font:inherit;font-weight:700;cursor:pointer;background:var(--ef-accent,#2563eb);color:#fff}'
            . '.elvado-form .ef-submit[disabled]{opacity:.6;cursor:wait}.elvado-form .ef-msg{padding:10px 14px;border-radius:8px}.elvado-form .ef-msg.ok{background:#e6f6ec;color:#14532d}.elvado-form .ef-msg.err{background:#fdecea;color:#7f1d1d}';
        $js = "(function(){var API=" . json_encode('/cms/api.php?action=np_public') . ";document.addEventListener('submit',function(ev){var f=ev.target;if(!f||!f.classList||!f.classList.contains('elvado-form'))return;ev.preventDefault();var msg=f.querySelector('.ef-msg'),btn=f.querySelector('.ef-submit');"
            . "function say(t,ok){msg.hidden=false;msg.className='ef-msg '+(ok?'ok':'err');msg.textContent=t;msg.scrollIntoView({block:'nearest'});}"
            . "var fd=new FormData(f),vals={},files=new FormData();fd.forEach(function(v,k){if(v instanceof File){if(v.name)files.append('f_'+k,v);return;}if(k.slice(-2)==='[]'){k=k.slice(0,-2);(vals[k]=vals[k]||[]).push(v);}else vals[k]=v;});"
            . "files.append('id','elvado-forms');files.append('call','submit');files.append('args',JSON.stringify({form:f.dataset.form,values:vals}));btn.disabled=true;msg.hidden=true;"
            . "fetch(API,{method:'POST',body:files,credentials:'omit'}).then(function(r){return r.json().catch(function(){return{status:'error',message:'Unerwartete Antwort.'};});}).then(function(d){btn.disabled=false;if(d&&d.status==='ok'&&d.ok){if(f.dataset.redirect){location.href=f.dataset.redirect;return;}f.reset();say(d.message||f.dataset.ok,true);}else say((d&&d.message)||'Das Formular konnte nicht gesendet werden.',false);}).catch(function(){btn.disabled=false;say('Keine Verbindung. Bitte später erneut versuchen.',false);});});})();";
        return '<style>' . $css . '</style><script>' . $js . '</script>';
    }

    // ---------------------------------------------------------------- Absenden

    private function ipHash(string $ip): string { return substr(hash_hmac('sha256', $ip . '|' . date('Y-m-d'), $this->salt()), 0, 20); }

    private function rateOk(string $formId, string $ip, int $limit): bool
    {
        $f = $this->dir() . '/rate.json';
        $h = @fopen($f, 'c+');
        if (!$h) {
            return true;
        }
        flock($h, LOCK_EX);
        $d = json_decode((string)stream_get_contents($h), true);
        $d = is_array($d) ? $d : [];
        $now = time();
        foreach ($d as $k => $ts) {
            $d[$k] = array_values(array_filter((array)$ts, static fn($t) => $t > $now - 600));
            if (!$d[$k]) {
                unset($d[$k]);
            }
        }
        $key = $formId . '|' . $this->ipHash($ip);
        $mine = $d[$key] ?? [];
        $ok = count($mine) < $limit;
        if ($ok) {
            $mine[] = $now;
            $d[$key] = $mine;
        }
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_slice($d, -2000, null, true)));
        flock($h, LOCK_UN);
        fclose($h);
        return $ok;
    }

    /**
     * Einsendung verarbeiten.
     * @return array{ok:bool,message:string,spam?:bool}
     */
    public function submit(array $a, array $files, string $ip): array
    {
        $f = $this->find((string)($a['form'] ?? ''));
        if ($f === null) {
            return ['ok' => false, 'message' => 'Dieses Formular gibt es nicht mehr.'];
        }
        $v = is_array($a['values'] ?? null) ? $a['values'] : [];
        $generic = ['ok' => false, 'message' => 'Das Formular konnte nicht gesendet werden. Bitte lade die Seite neu und versuche es erneut.'];
        // Spam-Schutz (stille Ablehnung bzw. allgemeine Meldung, damit Bots nichts lernen)
        if ($f['spam']['honeypot'] && trim((string)($v[$this->honeypotName($f['id'])] ?? '')) !== '') {
            return ['ok' => true, 'message' => $f['success_message'], 'spam' => true];
        }
        $age = $this->tokenAge($f['id'], (string)($v['_token'] ?? ''));
        if ($age < 0 || $age < (int)$f['spam']['min_seconds']) {
            return $age < 0 ? $generic : ['ok' => false, 'message' => 'Bitte einen Moment warten und erneut absenden.'];
        }
        if ($f['spam']['captcha']) {
            $ct = explode('.', (string)($v['_ctoken'] ?? ''), 3);
            if (count($ct) !== 3 || !ctype_digit($ct[0]) || !ctype_digit($ct[1]) || $this->tokenAge($f['id'], $ct[2], $ct[0] . '+' . $ct[1]) < 0 || trim((string)($v['_captcha'] ?? '')) !== (string)((int)$ct[0] + (int)$ct[1])) {
                return ['ok' => false, 'message' => 'Die Sicherheitsfrage wurde nicht richtig beantwortet.'];
            }
        }
        if (!$this->rateOk($f['id'], $ip, (int)$f['spam']['rate_limit'])) {
            return ['ok' => false, 'message' => 'Zu viele Einsendungen in kurzer Zeit. Bitte später erneut versuchen.'];
        }
        if ($f['consent']['enabled'] && empty($v['_consent'])) {
            return ['ok' => false, 'message' => 'Bitte stimme der Datenverarbeitung zu.'];
        }
        // Felder prüfen
        $data = [];
        $links = 0;
        $saved = [];
        $errors = [];
        foreach ($f['fields'] as $fl) {
            $k = $fl['id'];
            $raw = $v[$k] ?? null;
            $label = $fl['label'];
            if ($fl['type'] === 'file') {
                $up = $files['f_' . $k] ?? null;
                if (!$up || (int)($up['error'] ?? 4) === UPLOAD_ERR_NO_FILE) {
                    if ($fl['required']) {
                        $errors[] = '„' . $label . '“: Bitte eine Datei auswählen.';
                    }
                    continue;
                }
                $r = $this->storeUpload($f['id'], $up);
                if (!$r['ok']) {
                    $errors[] = '„' . $label . '“: ' . $r['message'];
                    continue;
                }
                $saved[] = $r['file'];
                $data[$label] = $r['file']['name'] . ' (' . number_format($r['file']['size'] / 1024, 0, ',', '.') . ' KB)';
                continue;
            }
            if ($fl['type'] === 'checkbox' && !empty($fl['options'])) {
                $vals = array_values(array_filter(array_map('strval', is_array($raw) ? $raw : ($raw !== null ? [$raw] : [])), static fn($x) => $x !== ''));
                if (array_diff($vals, $fl['options'])) {
                    $errors[] = '„' . $label . '“: Ungültige Auswahl.';
                } elseif ($fl['required'] && !$vals) {
                    $errors[] = '„' . $label . '“ ist ein Pflichtfeld.';
                } else {
                    $data[$label] = implode(', ', $vals);
                }
                continue;
            }
            if ($fl['type'] === 'checkbox') {
                $on = !empty($raw) && $raw !== '0';
                if ($fl['required'] && !$on) {
                    $errors[] = '„' . $label . '“ muss bestätigt werden.';
                }
                $data[$label] = $on ? 'Ja' : 'Nein';
                continue;
            }
            $val = is_scalar($raw) ? trim(str_replace("\0", '', (string)$raw)) : '';
            $max = $fl['type'] === 'textarea' ? 5000 : 500;
            if (mb_strlen($val) > $max) {
                $errors[] = '„' . $label . '“ ist zu lang (höchstens ' . $max . ' Zeichen).';
                continue;
            }
            if ($val === '') {
                if ($fl['required']) {
                    $errors[] = '„' . $label . '“ ist ein Pflichtfeld.';
                }
                $data[$label] = '';
                continue;
            }
            if ($fl['type'] === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $errors[] = '„' . $label . '“: Bitte eine gültige E-Mail-Adresse eingeben.';
            } elseif ($fl['type'] === 'number' && !is_numeric($val)) {
                $errors[] = '„' . $label . '“: Bitte eine Zahl eingeben.';
            } elseif ($fl['type'] === 'tel' && !preg_match('/^[0-9+()\/\-.\s]{5,30}$/', $val)) {
                $errors[] = '„' . $label . '“: Bitte eine gültige Telefonnummer eingeben.';
            } elseif (in_array($fl['type'], ['select', 'radio'], true) && !in_array($val, $fl['options'], true)) {
                $errors[] = '„' . $label . '“: Ungültige Auswahl.';
            }
            if (in_array($fl['type'], ['text', 'textarea'], true)) {
                $links += preg_match_all('~https?://|www\.~i', $val);
            }
            $data[$label] = $val;
        }
        if (!$errors && $links > 2) {
            $errors[] = 'Bitte keine Links in den Text einfügen.';
        }
        if ($errors) {
            foreach ($saved as $s) {
                @unlink($this->dir('uploads') . '/' . $f['id'] . '/' . $s['stored']);
            }
            return ['ok' => false, 'message' => implode(' ', array_slice($errors, 0, 4))];
        }
        // Speichern, E-Mail, Webhook
        $stored = false;
        $sub = ['id' => bin2hex(random_bytes(6)), 't' => date('Y-m-d H:i:s'), 'values' => $data, 'files' => $saved, 'consent' => !empty($v['_consent'])];
        if ($f['store']) {
            $stored = @file_put_contents($this->dir('submissions') . '/' . $f['id'] . '.jsonl', json_encode($sub, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX) !== false;
        } else {
            foreach ($saved as $s) {   // ohne Speicherung gibt es keinen Ort für Uploads
                @unlink($this->dir('uploads') . '/' . $f['id'] . '/' . $s['stored']);
            }
        }
        $mailed = false;
        $mailErr = '';
        if ($f['notify']['enabled']) {
            $to = $f['notify']['to'] !== '' ? $f['notify']['to'] : (string)$this->s('default_to');
            if ($to !== '') {
                try {
                    $this->mail($f, $to, $data, (string)($data[$this->labelOf($f, $f['notify']['reply_to_field'])] ?? ''));
                    $mailed = true;
                } catch (\Throwable $e) {
                    $mailErr = $e->getMessage();
                    $this->np->log('Mailversand (' . $f['id'] . ') fehlgeschlagen: ' . $mailErr);
                }
            }
        }
        $hooked = false;
        if ($f['webhook']['url'] !== '') {
            $hooked = $this->webhook($f, $sub);
        }
        if (!$stored && !$mailed && !$hooked) {
            $this->np->log('Einsendung (' . $f['id'] . ') nicht zugestellt – weder gespeichert, noch gesendet.');
            return ['ok' => false, 'message' => 'Deine Nachricht konnte leider nicht zugestellt werden. Bitte versuche es später erneut.'];
        }
        return ['ok' => true, 'message' => $f['success_message'], 'mail_error' => $mailErr !== ''];
    }

    private function labelOf(array $f, string $fieldId): string
    {
        foreach ($f['fields'] as $fl) {
            if ($fl['id'] === $fieldId) {
                return $fl['label'];
            }
        }
        return '';
    }

    // ---------------------------------------------------------------- Mail

    public function sendMail(string $to, string $subject, string $body, string $replyTo = ''): void
    {
        $fromEmail = trim((string)$this->s('from_email'));
        if ($fromEmail === '') {
            $host = preg_replace('/^www\./', '', (string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
            $fromEmail = 'noreply@' . (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host) ? $host : 'localhost.localdomain');
        }
        $fromName = trim((string)$this->s('from_name')) ?: (string)($GLOBALS['ELVADO_SITE']['portal']['site_name'] ?? 'ElvadoPress');
        $host = trim((string)$this->s('smtp_host'));
        $subject = trim((string)preg_replace('/[\r\n]+/', ' ', $subject));
        if ($host !== '') {
            (new Smtp($host, (int)$this->s('smtp_port'), (string)$this->s('smtp_security'), (string)$this->s('smtp_user'), (string)$this->s('smtp_password')))->send($to, $fromEmail, $fromName, $subject, $body, $replyTo);
            return;
        }
        require_once $this->np->cmsDir() . '/lib/mail.php';
        if (!elvado_send_mail($to, $subject, $body, $fromEmail, $fromName)) {
            throw new \RuntimeException('Der Server konnte die E-Mail nicht senden (PHP mail()). Bitte einen SMTP-Server in den Plugin-Einstellungen eintragen.');
        }
    }

    private function mail(array $f, string $to, array $data, string $replyTo): void
    {
        $subject = str_replace('{title}', $f['title'], $f['notify']['subject']);
        $body = "Neue Einsendung über das Formular „" . $f['title'] . "“:\n\n";
        foreach ($data as $label => $val) {
            $body .= $label . ":\n" . $val . "\n\n";
        }
        $body .= "--\n" . date('d.m.Y H:i') . "\n";
        $this->sendMail($to, $subject, $body, filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : '');
    }

    // ---------------------------------------------------------------- Webhook

    /** Zieladresse sicher? (https, öffentliche IP – schützt interne Dienste.) */
    public static function webhookTargetOk(string $url): bool
    {
        $p = parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
            return false;
        }
        $ips = filter_var($p['host'], FILTER_VALIDATE_IP) ? [$p['host']] : array_map(static fn($r) => $r['ip'] ?? ($r['ipv6'] ?? ''), (array)@dns_get_record($p['host'], DNS_A + DNS_AAAA));
        if (!$ips) {
            $ip = gethostbyname($p['host']);
            $ips = $ip !== $p['host'] ? [$ip] : [];
        }
        foreach ($ips as $ip) {
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return (bool)$ips;
    }

    private function webhook(array $f, array $sub): bool
    {
        $url = (string)$f['webhook']['url'];
        if (!self::webhookTargetOk($url) || !function_exists('curl_init')) {
            $this->np->log('Webhook (' . $f['id'] . ') nicht gesendet: Ziel unsicher oder cURL fehlt.');
            return false;
        }
        $body = json_encode(['form' => $f['id'], 'title' => $f['title'], 'submitted_at' => $sub['t'], 'fields' => $sub['values']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $h = ['Content-Type: application/json', 'User-Agent: ElvadoPress-Forms/1.0'];
        if (($f['webhook']['secret'] ?? '') !== '') {
            $h[] = 'X-Elvado-Signature: sha256=' . hash_hmac('sha256', (string)$body, (string)$f['webhook']['secret']);
        }
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);
        if ($code < 200 || $code >= 300) {
            $this->np->log('Webhook (' . $f['id'] . ') Antwort ' . $code);
        }
        return $code >= 200 && $code < 300;
    }

    // ---------------------------------------------------------------- Uploads

    /** @return array{ok:bool,message:string,file?:array} */
    private function storeUpload(string $formId, array $up): array
    {
        if (!$this->s('allow_uploads')) {
            return ['ok' => false, 'message' => 'Datei-Uploads sind ausgeschaltet.'];
        }
        if ((int)$up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$up['tmp_name']) && !defined('ELVADO_FORMS_TEST')) {
            return ['ok' => false, 'message' => 'Die Datei konnte nicht hochgeladen werden.'];
        }
        $max = max(1, (int)$this->s('upload_max_mb')) * 1048576;
        if ((int)$up['size'] <= 0 || (int)$up['size'] > $max) {
            return ['ok' => false, 'message' => 'Die Datei ist zu groß (höchstens ' . (int)$this->s('upload_max_mb') . ' MB).'];
        }
        $ext = strtolower((string)pathinfo((string)$up['name'], PATHINFO_EXTENSION));
        if (!isset(self::UPLOAD_EXT[$ext])) {
            return ['ok' => false, 'message' => 'Dieser Dateityp ist nicht erlaubt (erlaubt: ' . implode(', ', array_keys(self::UPLOAD_EXT)) . ').'];
        }
        $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file((string)$up['tmp_name']);
        if (!in_array($mime, self::UPLOAD_EXT[$ext], true)) {
            return ['ok' => false, 'message' => 'Der Inhalt der Datei passt nicht zur Endung.'];
        }
        $head = (string)file_get_contents((string)$up['tmp_name'], false, null, 0, 2048);
        if (preg_match('/<\?php|<script\b/i', $head) && !in_array($ext, ['txt', 'csv', 'pdf'], true)) {
            return ['ok' => false, 'message' => 'Die Datei enthält unzulässigen Inhalt.'];
        }
        $dir = $this->dir('uploads') . '/' . $formId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'message' => 'Speicherordner nicht verfügbar.'];
        }
        $stored = bin2hex(random_bytes(10)) . '.' . $ext;
        $moved = defined('ELVADO_FORMS_TEST') ? @copy((string)$up['tmp_name'], $dir . '/' . $stored) : @move_uploaded_file((string)$up['tmp_name'], $dir . '/' . $stored);
        if (!$moved) {
            return ['ok' => false, 'message' => 'Die Datei konnte nicht gespeichert werden.'];
        }
        @chmod($dir . '/' . $stored, 0640);
        $name = preg_replace('/[^A-Za-z0-9._ -]/u', '_', mb_substr((string)pathinfo((string)$up['name'], PATHINFO_FILENAME), 0, 60)) . '.' . $ext;
        return ['ok' => true, 'message' => '', 'file' => ['name' => $name, 'stored' => $stored, 'size' => (int)$up['size']]];
    }

    public function uploadPath(string $formId, string $stored): ?string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $formId) || !preg_match('/^[a-f0-9]{20}\.[a-z0-9]{2,4}$/', $stored)) {
            return null;
        }
        $p = $this->dir('uploads') . '/' . $formId . '/' . $stored;
        return is_file($p) ? $p : null;
    }

    // ---------------------------------------------------------------- Einsendungen

    public function submissions(string $formId, int $limit = 200): array
    {
        $f = $this->dir('submissions') . '/' . preg_replace('/[^a-z0-9-]/', '', $formId) . '.jsonl';
        $out = [];
        foreach (array_reverse(is_file($f) ? (file($f, FILE_IGNORE_NEW_LINES) ?: []) : []) as $l) {
            $e = json_decode($l, true);
            if (is_array($e)) {
                $out[] = $e;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public function deleteSubmission(string $formId, string $subId): bool
    {
        $f = $this->dir('submissions') . '/' . preg_replace('/[^a-z0-9-]/', '', $formId) . '.jsonl';
        if (!is_file($f)) {
            return false;
        }
        $keep = [];
        $found = false;
        foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $l) {
            $e = json_decode($l, true);
            if (is_array($e) && ($e['id'] ?? '') === $subId) {
                $found = true;
                foreach ((array)($e['files'] ?? []) as $fl) {
                    if ($p = $this->uploadPath($formId, (string)($fl['stored'] ?? ''))) {
                        @unlink($p);
                    }
                }
                continue;
            }
            $keep[] = $l;
        }
        if ($found) {
            @file_put_contents($f, $keep ? implode("\n", $keep) . "\n" : '', LOCK_EX);
        }
        return $found;
    }

    public function count(string $formId): int
    {
        $f = $this->dir('submissions') . '/' . preg_replace('/[^a-z0-9-]/', '', $formId) . '.jsonl';
        return is_file($f) ? count(file($f, FILE_SKIP_EMPTY_LINES) ?: []) : 0;
    }

    /** CSV-Export (mit Schutz vor Formel-Einschleusung in Tabellenprogrammen). @return string Dateipfad */
    public function exportCsv(string $formId): ?string
    {
        $f = $this->find($formId);
        if ($f === null) {
            return null;
        }
        $path = $this->dir('exports') . '/' . $formId . '-' . date('Ymd-His') . '.csv';
        $h = @fopen($path, 'w');
        if (!$h) {
            return null;
        }
        $labels = array_map(static fn($x) => $x['label'], $f['fields']);
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_merge(['Zeit'], $labels, ['Einwilligung']), ';');
        foreach (array_reverse($this->submissions($formId, 100000)) as $s) {
            $row = [$s['t'] ?? ''];
            foreach ($labels as $l) {
                $v = (string)($s['values'][$l] ?? '');
                $row[] = preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
            }
            $row[] = !empty($s['consent']) ? 'Ja' : 'Nein';
            fputcsv($h, $row, ';');
        }
        fclose($h);
        return $path;
    }

    /** Tick: Einsendungen nach der Aufbewahrungsdauer löschen (samt Dateien), alte Exporte entfernen. */
    public function tick(): void
    {
        foreach ($this->all() as $f) {
            if (!$f['store']) {
                continue;
            }
            $file = $this->dir('submissions') . '/' . $f['id'] . '.jsonl';
            if (!is_file($file)) {
                continue;
            }
            $cut = date('Y-m-d H:i:s', time() - (int)$f['retention_days'] * 86400);
            $keep = [];
            $changed = false;
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $l) {
                $e = json_decode($l, true);
                if (is_array($e) && ($e['t'] ?? '9') < $cut) {
                    $changed = true;
                    foreach ((array)($e['files'] ?? []) as $fl) {
                        if ($p = $this->uploadPath($f['id'], (string)($fl['stored'] ?? ''))) {
                            @unlink($p);
                        }
                    }
                    continue;
                }
                $keep[] = $l;
            }
            if ($changed) {
                @file_put_contents($file, $keep ? implode("\n", $keep) . "\n" : '', LOCK_EX);
            }
        }
        foreach (glob($this->dir('exports') . '/*.csv') ?: [] as $x) {
            if (filemtime($x) < time() - 3600) {
                @unlink($x);
            }
        }
    }
}

<?php
declare(strict_types=1);
if(is_file(__DIR__.'/lib/demo.json')){ require_once __DIR__.'/lib/demo.php';rrw_demo_boot(); }   // Demo-Betrieb (nur mit cms/lib/demo.json)
require_once __DIR__.'/lib/auth.php';
require_once __DIR__.'/lib/system.php';
require_once __DIR__.'/lib/pack.php';
require_once __DIR__.'/lib/product.php';
// Frische Installation: zuerst den Einrichtungsassistenten durchlaufen (bestehende Installationen sind nie betroffen).
if(rrw_install_needed()){header('Location: install.php',true,302);exit;}
$sa=rrw_standalone();
$pr=rrw_product();
$ph=fn(string $k)=>rrw_product_h($pr[$k]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?=$ph('title')?></title>
<?php if($pr['logo']!==''): ?><link rel="icon" href="<?=$ph('logo')?>"><?php endif; ?>
<script>window.RRW_PRODUCT=<?=json_encode(rrw_product_public()+['standalone'=>$sa],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window.CMS_PACKS_AVAILABLE=<?=json_encode([RRW_PACK_RADIO=>rrw_pack_available()])?>;</script>
<?php if(defined('RRW_DEMO')): ?><script>window.RRW_DEMO=<?=json_encode(rrw_demo_public(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="assets/demo.js?v=1" defer></script><?php endif; ?>
<script src="assets/auth-guard.js?v=5"></script>
<script src="assets/cms-toast.js?v=2"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php if(!$sa): ?><link rel="stylesheet" href="/control/shared.css"><?php endif; ?>
<link rel="stylesheet" href="assets/cms.css?v=43">
<link rel="stylesheet" href="assets/cms-broadcast.css?v=12">
<link rel="stylesheet" href="assets/baukasten.css?v=1">
<script>try{var m=localStorage.getItem("ep_admin_theme");if(m==="auto")m=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";if(m==="light"||m==="dark")document.documentElement.setAttribute("data-admin-theme",m)}catch(e){}</script>

</head>
<body>
<div id="navbarContainer"></div>
<main class="cms">
  <div id="cmsDenied" class="card access-denied" style="display:none">
    <i class="fas fa-lock"></i><h2>Kein Zugriff</h2>
    <p>Du hast keine Berechtigung für diese Verwaltung. Melde dich mit einem berechtigten ElvadoPress-Benutzerkonto an.</p>
    <?php if($sa): ?><a class="btn-g" href="/"><i class="fas fa-arrow-left"></i> Zur Website</a><?php else: ?><a class="btn-g" href="/control/"><i class="fas fa-arrow-left"></i> Zurück zum Dashboard</a><?php endif; ?>
  </div>
  <div id="cmsLogin" class="card access-denied" style="display:none">
    <i class="fas fa-right-to-bracket"></i>
    <h2 id="cmsLoginTitle">Anmeldung erforderlich</h2>
    <?php if($pr['logo']!==''): ?><img src="<?=$ph('logo')?>" alt="" style="max-width:220px;max-height:80px;margin:0 auto 10px;display:block"><?php endif; ?>
    <p id="cmsLoginDesc"><?=($sa||!rrw_pack_available())?'Melde dich mit deinem lokalen '.$ph('access_name').'-Zugang an.':'Melde dich mit dem lokalen '.$ph('access_name').'-Zugang an oder nutze das '.$ph('control_center').'.'?></p>
    <form id="cmsLoginForm" class="cms-login-form" data-mode="login" onsubmit="return cmsHandleLogin(event)">
      <input type="text" id="cmsLoginUser" class="fc" placeholder="Benutzername" autocomplete="username" required>
      <input type="password" id="cmsLoginPass" class="fc" placeholder="Passwort" autocomplete="current-password" required minlength="1">
      <button type="submit" id="cmsLoginSubmit" class="btn-a"><i class="fas fa-right-to-bracket"></i> Anmelden</button>
    </form>
    <p id="cmsLoginMsg" class="cms-login-msg"></p>
    <?php if(!$sa&&rrw_pack_available()): ?><a class="btn-g" href="#" onclick="cmsLoginRedirect();return false"><i class="fas fa-arrow-up-right-from-square"></i> Mit <?=$ph('control_center')?> anmelden</a><?php endif; ?>
  </div>
  <div id="cmsApp" style="display:none">
    <section class="hero">
      <div><div class="k"><i class="fas fa-shield-halved"></i> Verwaltung</div><h1><?=$ph('heading')?></h1><p>Hier verwaltest du Inhalte, Design, Plugins, Apps und Einstellungen deiner Website.<?=$sa?'':' Das '.$ph('control_center').' liefert nur Login und Berechtigungen.'?></p><div id="cmsVerBadge" class="hint" style="margin-top:4px"></div></div>
      <div class="hero-actions"><span id="cmsFsState" class="publish-state bad" hidden></span><span id="cmsPublishState" class="publish-state"><i class="fas fa-circle-check"></i> Alles gespeichert</span><a class="btn-g" href="<?=rrw_pack_available()?'https://www.ricorewi-radio.de/':'/'?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Website ansehen</a><button class="btn-a" onclick="cmsReload()"><i class="fas fa-rotate"></i> Aktualisieren</button></div>
    </section>
    <?php require __DIR__.'/views/sidebar.php'; ?>

    <?php require __DIR__.'/views/panel-overview.php'; ?>

    <?php require __DIR__.'/views/panel-profile.php'; ?>

    <?php require __DIR__.'/views/panel-tools.php'; ?>

    <?php require __DIR__.'/views/panel-tags.php'; ?>

    <?php require __DIR__.'/views/panel-baukasten.php'; ?>

    <?php require __DIR__.'/views/panel-radio.php'; ?>

    <?php require __DIR__.'/views/panel-themeconf.php'; ?>

    <?php require __DIR__.'/views/panel-ai.php'; ?>

    <?php require __DIR__.'/views/panel-aicenter.php'; ?>

    <?php require __DIR__.'/views/panel-aibuilder.php'; ?>

    <?php require __DIR__.'/views/panel-aidev.php'; ?>
    <?php require __DIR__.'/views/panel-aimedia.php'; ?>

    <?php require __DIR__.'/views/panel-contents.php'; ?>

    <?php require __DIR__.'/views/panel-forms.php'; ?>

    <?php require __DIR__.'/views/panel-polls.php'; ?>

    <?php require __DIR__.'/views/panel-community.php'; ?>

    <?php require __DIR__.'/views/panel-news.php'; ?>

    <?php require __DIR__.'/views/panel-feeds.php'; ?>

    <?php require __DIR__.'/views/panel-pages.php'; ?>

    <?php require __DIR__.'/views/panel-menus.php'; ?>

    <?php require __DIR__.'/views/panel-widgets.php'; ?>

    <?php require __DIR__.'/views/panel-legal.php'; ?>

    <?php require __DIR__.'/views/panel-navigation.php'; ?>

    <?php require __DIR__.'/views/panel-portal.php'; ?>

    <?php require __DIR__.'/views/panel-brands.php'; ?>
    <?php require __DIR__.'/views/panel-directory.php'; ?>

    <?php require __DIR__.'/views/panel-social.php'; ?>

    <?php require __DIR__.'/views/panel-apps.php'; ?>

    <?php require __DIR__.'/views/panel-alexa.php'; ?>

    <?php require __DIR__.'/views/panel-assistant.php'; ?>

    <?php require __DIR__.'/views/panel-branding.php'; ?>
    <?php require __DIR__.'/views/panel-headerbuilder.php'; ?>

    <?php require __DIR__.'/views/panel-media.php'; ?>

    <?php require __DIR__.'/views/panel-themes.php'; ?>

    <?php require __DIR__.'/views/panel-network.php'; ?>

    <?php require __DIR__.'/views/panel-services.php'; ?>

    <?php require __DIR__.'/views/panel-plugins.php'; ?>

    <?php require __DIR__.'/views/panel-seo.php'; ?>

    <?php require __DIR__.'/views/panel-contentfiles.php'; ?>

    <?php require __DIR__.'/views/panel-database.php'; ?>

    <?php require __DIR__.'/views/panel-backups.php'; ?>

    <?php require __DIR__.'/views/panel-architecture.php'; ?>

    <?php require __DIR__.'/views/panel-system.php'; ?>

    <?php require __DIR__.'/views/panel-users.php'; ?>
    <?php require __DIR__.'/views/panel-comments.php'; ?>
    <?php require __DIR__.'/views/panel-settings.php'; ?>
    <?php require __DIR__.'/views/panel-wptools.php'; ?>

    <?php require __DIR__.'/views/panel-activity.php'; ?>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="assets/block-editor.css?v=1"><link rel="stylesheet" href="assets/admin-themes.css?v=1"><script src="assets/admin-theme.js?v=2"></script><script src="assets/ai-center.js?v=4"></script><script src="assets/ai-media.js?v=4"></script><script src="assets/ai-builder.js?v=3"></script><script src="assets/ai-dev.js?v=2"></script><script src="assets/ai-nav.js?v=1"></script><script src="assets/customizer-extras.js?v=1"></script><script src="assets/blocks/core.js?v=1"></script><script src="assets/blocks/types.js?v=2"></script><script src="assets/blocks/editor.js?v=2"></script>
<script src="assets/news-editor.js?v=19"></script>
<script src="assets/stock-media.js?v=2"></script>
<script src="assets/media-manager.js?v=8"></script>
<script src="assets/theme-manager.js?v=9"></script>
<script src="assets/brands-manager.js?v=4"></script>
<script src="assets/directory-manager.js?v=2"></script><script src="assets/services-manager.js?v=1"></script>
<script src="assets/app-build.js?v=9"></script><script src="assets/app-builder.js?v=4"></script><script src="assets/apps-manager.js?v=8"></script>
<script src="assets/assistant-manager.js?v=7"></script><script src="assets/alexa-manager.js?v=4"></script>
<script src="assets/widgets-manager.js?v=2"></script>
<script src="assets/plugin-manager.js?v=4"></script>
<script src="assets/system-manager.js?v=2"></script>
<script src="assets/users-manager.js?v=2"></script>
<script src="assets/standalone-manager.js?v=1"></script><script src="assets/update-manager.js?v=1"></script>
<script src="assets/activity-log.js?v=2"></script>
<script src="assets/tools-manager.js?v=3"></script>
<script src="assets/tags-manager.js?v=1"></script>
<script src="assets/home-builder.js?v=4"></script>
<script src="assets/radio-manager.js?v=1"></script>
<script src="assets/theme-config.js?v=1"></script>
<script src="assets/ai-lovable-admin.js?v=1"></script>
<script src="assets/contents-manager.js?v=1"></script>
<script src="assets/forms-manager.js?v=1"></script>
<script src="assets/polls-manager.js?v=1"></script>
<script src="assets/community-manager.js?v=1"></script>
<script src="assets/wpplugins-manager.js?v=3"></script>
<script src="assets/wpthemes-manager.js?v=10"></script>
<script src="assets/wp-links.js?v=2"></script>
<script src="assets/wp-settings.js?v=2"></script><script src="assets/comments-manager.js?v=1"></script><script src="assets/wp-tools.js?v=1"></script>
<script src="assets/sandbox.js?v=1"></script>
<script src="assets/design-hub.js?v=3"></script>
<script src="assets/pages-manager.js?v=1"></script>
<script src="assets/header-builder.js?v=1"></script>
<script src="assets/cms-app.js?v=44"></script>
<script src="assets/cms-nav.js?v=3"></script>
<script src="assets/cms-search.js?v=2"></script>
</body>
</html>
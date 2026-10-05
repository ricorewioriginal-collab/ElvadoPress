<section id="panel-social" class="panel">
      <div class="card"><div class="th"><div class="tt"><i class="fas fa-share-nodes"></i>Social-Profile</div><button class="btn-a" onclick="saveSocial()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
        <div class="section-grid">
          <?php $__rp=rrw_pack_available(); ?>
          <div><label class="news-lbl"><?=$__rp?'RicoReWi · TikTok':'TikTok'?></label><input id="cmsRt" class="fc w-100"></div>
          <div><label class="news-lbl"><?=$__rp?'RicoReWi · Instagram':'Instagram'?></label><input id="cmsRi" class="fc w-100"></div>
          <?php if($__rp): ?>
          <div><label class="news-lbl">AnMaCha · TikTok</label><input id="cmsAt" class="fc w-100"></div>
          <div><label class="news-lbl">AnMaCha · Instagram</label><input id="cmsAi" class="fc w-100"></div>
          <?php else: ?>
          <div style="display:none"><input id="cmsAt"><input id="cmsAi"></div>
          <?php endif; ?>
        </div>
        <p class="hint" style="margin-top:12px">Nur Profilnamen eingeben, ohne @ und ohne vollständige URL.</p>
      </div>
    </section>

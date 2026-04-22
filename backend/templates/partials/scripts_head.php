<?php
if (!defined('CARAMAGNOLS_TITLE_TAG_RENDERED')) {
    define('CARAMAGNOLS_TITLE_TAG_RENDERED', true);
}
?>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars((string) ($pageTitle ?? t('TXT_PAGE_DEFAULT_TITLE')), ENT_QUOTES, 'UTF-8') ?></title>
  <?php
  $pageMetaDescriptionValue = trim((string) ($pageMetaDescription ?? ''));
  if ($pageMetaDescriptionValue === '') {
      $fallbackDescription = function_exists('t') ? trim((string) t('TXT_SCHEMA_ORG_DESCRIPTION')) : '';
      if ($fallbackDescription !== '' && !str_starts_with($fallbackDescription, '[[')) {
          $pageMetaDescriptionValue = $fallbackDescription;
      }
  }
  ?>
  <?php if ($pageMetaDescriptionValue !== ''): ?>
  <meta name="description" content="<?= htmlspecialchars($pageMetaDescriptionValue, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php if (!empty($pageMetaImage)): ?>
  <meta property="og:image" content="<?= htmlspecialchars((string) $pageMetaImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:image" content="<?= htmlspecialchars((string) $pageMetaImage, ENT_QUOTES, 'UTF-8') ?>" />
  <?php if (!empty($pageMetaImageAlt)): ?>
  <meta name="twitter:image:alt" content="<?= htmlspecialchars((string) $pageMetaImageAlt, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php endif; ?>
  <link rel="icon" href="/assets/images/structure/favicon.ico" />
  <?php $globalHeadMetadataHtml = trim((string) app_config('site.head_metadata_html', '')); ?>
  <?php $tarteaucitronSettings = is_array(app_config('site.tarteaucitron', [])) ? app_config('site.tarteaucitron', []) : []; ?>
  <?php $discussionSettings = is_array(app_config('site.discussions', [])) ? app_config('site.discussions', []) : []; ?>
  <?php $discussionRecaptcha = is_array($discussionSettings['recaptcha'] ?? null) ? $discussionSettings['recaptcha'] : []; ?>
  <?php $discussionRecaptchaEnabled = !empty($discussionRecaptcha['enabled']) && trim((string) ($discussionRecaptcha['site_key'] ?? '')) !== ''; ?>
  <?php if ($discussionRecaptchaEnabled): ?>
  <?php
  $configuredServices = array_values(array_filter(
      is_array($tarteaucitronSettings['services'] ?? null) ? $tarteaucitronSettings['services'] : [],
      static fn ($service): bool => is_string($service) && trim($service) !== ''
  ));
  if (!in_array('recaptcha', array_map('strtolower', $configuredServices), true)) {
      $configuredServices[] = 'recaptcha';
  }
  $tarteaucitronSettings['services'] = $configuredServices;
  ?>
  <?php endif; ?>
  <?php
  $siteUrlSettings = is_array(app_config('site.url', [])) ? app_config('site.url', []) : [];
  $siteBasePath = normalize_public_route((string) ($siteUrlSettings['base_path'] ?? '/')) ?? '/';
  $langApiPath = ($siteBasePath === '/' ? '' : rtrim($siteBasePath, '/')) . '/core/api/lang.php';
  $runtimeConfig = [
      'tarteaucitron' => $tarteaucitronSettings,
      'discussions' => [
          'enabled' => !empty($discussionSettings['enabled']),
          'require_account' => !empty($discussionSettings['require_account']),
          'recaptcha' => [
              'enabled' => $discussionRecaptchaEnabled,
              'site_key' => trim((string) ($discussionRecaptcha['site_key'] ?? '')),
          ],
      ],
      'i18n' => [
          'youtube_title_fallback' => (string) t('TXT_YOUTUBE_VIDEO_FALLBACK_TITLE'),
      ],
      'api' => [
          'lang' => $langApiPath,
      ],
  ];
  ?>
  <?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
  <?php if ($globalHeadMetadataHtml !== ''): ?>
  <?php echo $globalHeadMetadataHtml . "\n"; ?>
  <?php endif; ?>
  <script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    window.caramagnolsRuntime = <?php echo json_encode($runtimeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>

  <style<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    #entete{position:sticky;top:.25rem;z-index:20;width:auto;margin:.25rem 1% 0}
    #breadcrumb-mobile{display:none}
    .site-header{--site-header-logo-offset-y:2rem;display:grid;gap:.35rem;padding:.3rem;border-radius:.95rem;background:linear-gradient(180deg,rgba(0,80,239,.96),rgba(0,80,239,.9));box-shadow:0 .8rem 1.5rem rgba(0,0,0,.16)}
    .site-header-utility{display:grid;grid-template-columns:auto minmax(10rem,1fr) auto;gap:.45rem;align-items:center}
    .site-utility-list,.site-language-list,.site-nav-list{list-style:none;margin:0;padding:0}
    .site-utility-list,.site-language-list{display:flex;flex-wrap:wrap;gap:.22rem;align-items:center}
    .site-utility-link,.site-language-link{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border-radius:999px;background:rgba(255,255,255,.14)}
    .site-utility-link{min-width:1.55rem;min-height:1.55rem;padding:.2rem}
    .site-language-link{min-width:1.2rem;min-height:1.2rem;padding:.08rem}
    .site-utility-icon{display:block;width:.76rem;height:.76rem;object-fit:contain}
    .site-language-flag{display:block;width:.66rem;height:.46rem;object-fit:contain;border-radius:.15rem}
    .site-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.25rem;align-items:center}
    .site-header-utility>.site-search{justify-self:end;width:clamp(10.75rem,16vw,13.5rem);gap:.18rem}
    .site-search input{width:100%;min-width:0;padding:.38rem .7rem;border:0;border-radius:999px}
    .site-header-utility>.site-search input{padding:.28rem .68rem;background:rgba(233,239,248,.82);box-shadow:inset 0 0 0 1px rgba(19,41,75,.12);font-size:.74rem}
    .site-search-button{min-width:2.15rem;padding:.38rem .72rem;border:0;border-radius:999px}
    .site-header-utility>.site-search .site-search-button{min-width:1.9rem;padding:.28rem .58rem;font-size:.72rem}
    .site-header-banner{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.45rem;align-items:center;min-height:2.55rem;padding:.25rem .55rem;border-radius:.7rem;background-color:rgba(19,34,74,.45);background-image:var(--site-header-banner-image,none);background-position:left top;background-repeat:repeat;background-size:auto;overflow:visible}
    .site-brand,.site-mobile-brand{display:inline-flex;align-items:center;gap:.45rem;color:#fff;text-decoration:none;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
    .site-brand{position:relative;justify-self:start;z-index:2;transform:translateY(var(--site-header-logo-offset-y))}
    .site-header-marquee{min-width:0;overflow:hidden}
    .site-brand img,.site-mobile-brand img{display:block;width:2rem;height:2rem;padding:.12rem;border-radius:999px;background:rgba(255,255,255,.94)}
    .site-brand span{position:absolute;top:50%;left:calc(100% + .45rem);padding:.2rem .45rem;border-radius:999px;background:rgba(19,34,74,.92);color:#fff;font-size:.78rem;line-height:1.1;white-space:nowrap;box-shadow:0 .35rem .8rem rgba(0,0,0,.18);opacity:0;visibility:hidden;pointer-events:none;transform:translate(0,-45%);transition:opacity .18s ease,visibility .18s ease,transform .18s ease}
    .site-brand:hover span,.site-brand:focus-visible span,.site-brand:focus-within span{opacity:1;visibility:visible;transform:translate(0,-50%)}
    .site-banner-icon{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;padding:.12rem;border-radius:999px;background:rgba(255,255,255,.94);box-shadow:0 .3rem .65rem rgba(0,0,0,.18);z-index:2;transform:translateY(var(--site-header-logo-offset-y))}
    .site-banner-icon img{display:block;width:100%;height:100%;object-fit:contain}
    .site-header-headline{display:flex;width:max-content;gap:2rem;margin:0;color:#fff;line-height:1;font-size:clamp(.82rem,1.4vw,1.45rem);white-space:nowrap;animation:site-banner-marquee 24s linear infinite}
    .site-header-headline span{display:block;padding-right:1.25rem}
    .site-header-banner:hover .site-header-headline,.site-header-banner:focus-within .site-header-headline{animation-play-state:paused}
    @keyframes site-banner-marquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .site-header-nav-shell{border-radius:.7rem;background:rgba(19,34,74,.26)}
    .site-nav{--site-nav-root-count:5;--site-nav-root-width:55.2rem;--site-nav-root-gap:.3rem;--site-nav-primary-button-width:10.8rem;--site-nav-primary-toggle-width:2.45rem;position:relative}
    .site-nav-list{display:grid;grid-template-columns:repeat(var(--site-nav-root-count),minmax(0,1fr));align-items:stretch;gap:var(--site-nav-root-gap);width:min(100%,var(--site-nav-root-width));margin:0 auto;padding:.18rem}
    .site-nav-list>.site-nav-item{display:flex;align-items:stretch;min-width:0;width:auto}
    .site-nav-row{display:flex;align-items:stretch;width:100%;min-width:0}
    .site-nav-list>.site-nav-item>.site-nav-row{height:100%}
    .site-nav-link,.site-nav-link:link,.site-nav-link:visited,.site-nav-link:hover,.site-nav-link:focus,.site-nav-link:active,.site-nav-label-button,.site-nav-label-static,.site-nav-toggle{box-sizing:border-box;color:#e3c800;font-family:"Segoe UI",sans-serif;font-size:.86rem;font-weight:700;font-style:normal;text-decoration:none;border:0;background:rgba(255,255,255,.08)}
    .site-nav-link,.site-nav-label-button,.site-nav-label-static{display:inline-flex;flex:1 1 auto;align-items:center;justify-content:center;min-height:2rem;padding:.42rem .68rem;border-radius:.62rem 0 0 .62rem;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .site-nav-list>.site-nav-item>.site-nav-row>.site-nav-link,.site-nav-list>.site-nav-item>.site-nav-row>.site-nav-label-button,.site-nav-list>.site-nav-item>.site-nav-row>.site-nav-label-static,.site-nav-list>.site-nav-item>.site-nav-row>.site-nav-toggle{min-height:100%}
    .site-nav-item:not(.site-nav-item-has-children) .site-nav-link,.site-nav-item:not(.site-nav-item-has-children) .site-nav-label-static{width:100%;border-radius:.62rem}
    .site-nav-item-toggleless>.site-nav-row>.site-nav-link,.site-nav-item-toggleless>.site-nav-row>.site-nav-label-button,.site-nav-item-toggleless>.site-nav-row>.site-nav-label-static{width:100%;border-radius:.62rem}
    .site-nav-toggle{display:inline-flex;flex:0 0 var(--site-nav-primary-toggle-width);align-items:center;justify-content:center;min-width:var(--site-nav-primary-toggle-width);min-height:2rem;padding:.42rem .55rem;border-radius:0 .62rem .62rem 0}
    .site-nav-item-active>.site-nav-row .site-nav-link,.site-nav-item-active>.site-nav-row .site-nav-label-button,.site-nav-item-active>.site-nav-row .site-nav-label-static,.site-nav-item-active>.site-nav-row .site-nav-toggle{background:rgba(255,255,255,.18);color:#e3c800;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
    .site-nav-item:not(.site-nav-item-active)>.site-nav-row .site-nav-link:hover,.site-nav-item:not(.site-nav-item-active)>.site-nav-row .site-nav-label-button:hover,.site-nav-item:not(.site-nav-item-active)>.site-nav-row .site-nav-toggle:hover,.site-nav-item.is-open:not(.site-nav-item-active)>.site-nav-row .site-nav-link,.site-nav-item.is-open:not(.site-nav-item-active)>.site-nav-row .site-nav-label-button,.site-nav-item.is-open:not(.site-nav-item-active)>.site-nav-row .site-nav-label-static,.site-nav-item.is-open:not(.site-nav-item-active)>.site-nav-row .site-nav-toggle{background:rgba(255,255,255,.18);color:#e3c800;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
    .site-nav-row .site-nav-link:focus-visible,.site-nav-row .site-nav-label-button:focus-visible,.site-nav-row .site-nav-toggle:focus-visible{outline:2px solid rgba(255,255,255,.78);outline-offset:2px}
    .site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:hover>.site-nav-row .site-nav-link,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:hover>.site-nav-row .site-nav-label-button,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:hover>.site-nav-row .site-nav-label-static,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:hover>.site-nav-row .site-nav-toggle,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:focus-within>.site-nav-row .site-nav-link,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:focus-within>.site-nav-row .site-nav-label-button,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:focus-within>.site-nav-row .site-nav-label-static,.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:focus-within>.site-nav-row .site-nav-toggle{background:rgba(255,255,255,.18);color:#e3c800;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
    .site-nav-panel{position:absolute;top:calc(100% + .06rem);left:0;z-index:35;min-width:11rem;padding:.35rem;border-radius:.7rem;background:rgba(14,30,61,.96);box-shadow:0 1.2rem 2.1rem rgba(0,0,0,.24)}
    .site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:hover>.site-nav-panel[hidden],.site-header-nav-shell[data-nav-scope-root="desktop"] .site-nav-item-has-children:focus-within>.site-nav-panel[hidden]{display:block}
    .site-nav-panel-mega{left:0;right:0;min-width:0;width:auto;max-width:100%}
    .site-nav-panel[hidden]{display:none}
    .site-nav-panel-mega .site-nav-mega{width:100%;max-width:100%}
    .site-nav-mega-columns{width:100%}
    .site-nav-mega-columns-compact{grid-template-columns:repeat(var(--site-nav-mega-columns,2),minmax(12rem,17rem));justify-content:flex-start;justify-items:stretch;width:fit-content;max-width:100%}
    .site-nav-mega-section{align-content:start}
    @media screen and (width <= 900px){#entete{display:none}#breadcrumb-mobile{position:sticky;top:.5rem;z-index:25;display:block;width:min(97%,48rem);margin:.5rem auto 0;padding:.75rem;border-radius:1.35rem;background:linear-gradient(180deg,rgba(0,80,239,.95),rgba(0,80,239,.9));box-shadow:0 1.3rem 2.3rem rgba(0,0,0,.2)}}
    @media screen and (width <= 640px){#breadcrumb-mobile{width:calc(100% - 1rem);margin:.5rem}.site-header-utility,.site-mobile-header-bar{grid-template-columns:1fr}}
  </style>

  <?php if (!empty($tarteaucitronSettings['enabled'])): ?>
  <script src="/tarteaucitron/tarteaucitron.min.js"></script>
  <?php endif; ?>

  <?php
  echo vite_tags('src/js/main.ts');
  ?>

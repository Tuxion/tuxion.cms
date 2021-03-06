<?php namespace components\cms; if(!defined('TX')) die('No direct access.'); ?>

<?php tx('Ob')->script('cms'); ?>
  <script type="text/javascript">
  $(function(){
    window.app = new Cms({
      menu_id: <?php echo $app->menu_id; ?>,
      site_id: <?php echo $app->site_id; ?>,
      url_base: '<?php echo URL_BASE; ?>',
      language_id: <?php echo mk('Language')->id; ?>
    });
  });
  </script>
<?php tx('Ob')->end(); ?>

<div id="page-main"<?php if($data->sites->size() > 1){ ?> class="multisite"<?php } ?>>

  <div id="page-main-left">
    <?php echo $app->menus; ?>
  </div>
  <!-- /PAGE-MAIN-LEFT -->

  <div id="page-main-right">
    <?php echo $app->app; ?>
  </div>
  <!-- /PAGE-MAIN-RIGHT -->

</div>
<!-- /PAGE-MAIN -->

<div id="page-topbar">

  <!-- LOGO -->
  <h1 id="logo"><a href="<?php echo url('', true); ?>"><?php echo !HIDE_LOGO_IN_BACKEND ? 'Mokuji' : ''; ?></a></h1>
  <!-- /LOGO -->

  <?php echo $app->topbar; ?>

  <div class="clear"></div>

</div>
<!-- /PAGE-TOPBAR -->

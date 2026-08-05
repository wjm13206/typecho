<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$actionUrl = $security->getTokenUrl(
    \Typecho\Router::url('do', ['action' => 'upgrade', 'widget' => 'Upgrade'],
        \Typecho\Common::url('index.php', $options->rootUrl)));
?>

<main class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 col-tb-8">
                <div id="typecho-welcome">
                    <form action="<?php echo $actionUrl; ?>" method="post" enctype="multipart/form-data">
                        <h3><?php _e('在线升级'); ?></h3>
                        <ul>
                            <li><?php _e('当前版本: <strong>%s</strong>', $options->version); ?></li>
                            <li><?php _e('请上传 Typecho 官方发布的完整升级包, 支持 <strong>.zip</strong> 与 <strong>.tar.gz</strong> 格式'); ?></li>
                            <li><?php _e('升级将覆盖系统核心文件(<strong>admin / var / install / index.php / install.php</strong>), 不会影响 <strong>usr</strong> 目录与 <strong>config.inc.php</strong>'); ?></li>
                            <li><?php _e('被覆盖的核心文件会自动备份到 <strong>%s</strong> 目录', '/usr/upgrade'); ?></li>
                            <li><strong class="warning"><?php _e('升级前请务必备份您的数据, 升级过程中请勿关闭页面, 完成后将自动执行数据库升级'); ?></strong></li>
                        </ul>
                        <ul class="typecho-option">
                            <li>
                                <label class="typecho-label" for="upgrade-file"><?php _e('升级包文件'); ?></label>
                                <input tabindex="1" id="upgrade-file" name="file" type="file" class="file" accept=".zip,.tar,.tar.gz,.tgz">
                            </li>
                        </ul>
                        <ul class="typecho-option typecho-option-submit">
                            <li>
                                <button tabindex="2" type="submit" class="btn primary"><?php _e('上传并升级 &raquo;'); ?></button>
                                <input type="hidden" name="do" value="online">
                            </li>
                        </ul>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
?>
<script>
    $('#typecho-welcome form').submit(function () {
        if (!$('#upgrade-file').val()) {
            alert('<?php _e('请先选择要上传的升级包文件'); ?>');
            return false;
        }

        return confirm('<?php _e('升级将覆盖系统核心文件, 是否继续?'); ?>');
    });
</script>
<?php include 'footer.php'; ?>

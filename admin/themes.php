<?php
include 'common.php';
include 'header.php';
include 'menu.php';
?>

<main class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <?php include 'theme-tabs.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <table class="typecho-list-table typecho-theme-list">
                    <colgroup>
                        <col width="35%"/>
                        <col/>
                    </colgroup>

                    <thead>
                    <th><?php _e('截图'); ?></th>
                    <th><?php _e('详情'); ?></th>
                    </thead>

                    <tbody>
                    <?php if ($options->missingTheme): ?>
                        <tr id="theme-<?php $options->missingTheme; ?>" class="current">
                            <td colspan="2" class="warning">
                                <p><strong><?php _e('检测到您之前使用的 "%s" 外观文件不存在，您可以重新上传此外观或者启用其他外观。', $options->missingTheme); ?></strong></p>
                                <ul>
                                    <li><?php _e('重新上传此外观后刷新当前页面，此提示将会消失。'); ?></li>
                                    <li><?php _e('启用新外观后，当前外观的设置数据将被删除。'); ?></li>
                                </ul>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php \Widget\Themes\Rows::alloc()->to($themes); ?>
                    <?php while ($themes->next()): ?>
                        <tr id="theme-<?php echo htmlspecialchars($themes->name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            class="<?php if ($themes->activated && !$options->missingTheme): ?>current<?php endif; ?>">
                            <td valign="top"><img src="<?php echo htmlspecialchars($themes->screen ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                  alt="<?php echo htmlspecialchars($themes->name ?? '', ENT_QUOTES, 'UTF-8'); ?>"/></td>
                            <td valign="top">
                                <h3><?php echo htmlspecialchars('' != $themes->title ? $themes->title : $themes->name, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <cite>
                                    <?php if ($themes->author): ?><?php _e('作者'); ?>: <?php if ($themes->homepage): ?><a href="<?php echo htmlspecialchars(\Typecho\Common::safeUrl($themes->homepage), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?><?php echo htmlspecialchars($themes->author, ENT_QUOTES, 'UTF-8'); ?><?php if ($themes->homepage): ?></a><?php endif; ?> &nbsp;&nbsp;<?php endif; ?>
                                    <?php if ($themes->version): ?><?php _e('版本'); ?>: <?php echo htmlspecialchars($themes->version, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                </cite>
                                <p><?php echo nl2br(htmlspecialchars($themes->description ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
                                <?php if ($options->theme != $themes->name || $options->missingTheme): ?>
                                    <p>
                                        <?php if (\Widget\Themes\Files::isWriteable()): ?>
                                            <a class="edit"
                                               href="<?php $options->adminUrl('theme-editor.php?theme=' . $themes->name); ?>"><?php _e('编辑'); ?></a> &nbsp;
                                        <?php endif; ?>
                                        <a class="activate"
                                           href="<?php $security->index('/action/themes-edit?change=' . $themes->name); ?>"><?php _e('启用'); ?></a>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>

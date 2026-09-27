<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php $content = !empty($post) ? $post : $page; ?>
<script>
(function () {
    $('#text').on('change', function (e) {
        e.preventDefault();
        e.stopPropagation();
    }).on('input', function () {
        $(this).parents('form').trigger('write');
    });
})();
</script>
<?php if (!$options->markdown): ?>
<script>
(function () {
    const textarea = $('#text');

    // 原始的插入图片和文件
    Typecho.insertFileToEditor = function (file, url, isImage) {
        const sel = textarea.getSelection(),
            html = isImage ? '<img src="' + url + '" alt="' + file + '" />'
                : '<a href="' + url + '">' + file + '</a>',
            offset = (sel ? sel.start : 0) + html.length;

        textarea.replaceSelection(html);
        textarea.setSelection(offset, offset);
    };
})();
</script>
<?php else: ?>
<link rel="stylesheet" href="<?php $options->adminStaticUrl('css', 'easymde.min.css'); ?>">
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/7.3.1/css/all.min.css">
<script src="<?php $options->adminStaticUrl('js', 'hyperdown.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'easymde.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'purify.js'); ?>"></script>
<script>
$(document).ready(function () {
    const textarea = $('#text'),
        isMarkdown = <?php echo json_encode(!$content->have() || $content->isMarkdown); ?>;

    // 预览渲染仍走与 PHP 同构的 HyperDown，保证所见即所得，
    // 摘要分割与 iframe 占位逻辑与原来保持一致
    const converter = new HyperDown();
    converter.enableHtml(true);

    function renderPreview(plainText) {
        let html = converter.makeHtml(plainText);
        html = html.replace('<p><!--more--></p>', '<!--more-->');

        if (html.indexOf('<!--more-->') > 0) {
            var parts = html.split(/\s*<\!\-\-more\-\->\s*/),
                summary = parts.shift(),
                details = parts.join('');

            html = '<div class="summary">' + summary + '</div>'
                + '<div class="details">' + details + '</div>';
        }

        // 替换block
        html = html.replace(/<(iframe|embed)\s+([^>]*)>/ig, function (all, tag, src) {
            if (src[src.length - 1] === '/') {
                src = src.substring(0, src.length - 1);
            }

            return '<div class="embed"><strong>'
                + tag + '</strong> : ' + src.trim() + '</div>';
        });

        return DOMPurify.sanitize(html, {USE_PROFILES: {html: true}});
    }

    function insertMore(editor) {
        editor.codemirror.replaceSelection('<!--more-->');
        editor.codemirror.focus();
    }

    let easyMDE = null;

    Typecho.insertFileToEditor = function (file, url, isImage) {
        if (!easyMDE) {
            return;
        }

        const text = isImage ? '![' + file + '](' + url + ')' : '[' + file + '](' + url + ')',
            cm = easyMDE.codemirror;

        cm.replaceSelection(text);
        cm.focus();
    };

    Typecho.uploadComplete = function (attachment) {
        Typecho.insertFileToEditor(attachment.title, attachment.url, attachment.isImage);
    };

    <?php \Typecho\Plugin::factory('admin/editor-js.php')->call('markdownEditor', $content); ?>

    function initMarkdown() {
        easyMDE = new EasyMDE({
            element: textarea[0],
            forceSync: true,
            spellChecker: false,
            status: false,
            autoDownloadFontAwesome: false,
            uploadImage: true,
            // 上传失败已在附件列表中展示，这里不再弹框打扰
            errorCallback: function () {},
            imageUploadFunction: function (file, onSuccess, onError) {
                // 图片走回调插回编辑器，非图片走附件列表（与原来粘贴上传行为一致）
                if (/^image\//.test(file.type)) {
                    Typecho.uploadFile(file, {
                        onSuccess: function (attachment) {
                            onSuccess(attachment.url);
                        },
                        onError: function () {
                            onError('upload failed');
                        }
                    });
                } else {
                    Typecho.uploadFile(file);
                }
            },
            previewRender: function (plainText) {
                return renderPreview(plainText);
            },
            extraKeys: {
                'Ctrl-S': function () {
                    Typecho.savePost();
                },
                'Cmd-S': function () {
                    Typecho.savePost();
                }
            },
            toolbar: [
                {name: 'bold', action: EasyMDE.toggleBold, title: '<?php _e('加粗'); ?>', className: 'fa fa-bold'},
                {name: 'italic', action: EasyMDE.toggleItalic, title: '<?php _e('斜体'); ?>', className: 'fa fa-italic'},
                {name: 'quote', action: EasyMDE.toggleBlockquote, title: '<?php _e('引用'); ?>', className: 'fa fa-quote-left'},
                {name: 'code', action: EasyMDE.toggleCodeBlock, title: '<?php _e('代码'); ?>', className: 'fa fa-code'},
                '|',
                {name: 'link', action: EasyMDE.drawLink, title: '<?php _e('链接'); ?>', className: 'fa fa-link'},
                {name: 'image', action: EasyMDE.drawImage, title: '<?php _e('图片'); ?>', className: 'fa fa-image'},
                {name: 'upload-image', action: EasyMDE.drawUploadedImage, title: '<?php _e('上传图片'); ?>', className: 'fa fa-upload'},
                {name: 'table', action: EasyMDE.drawTable, title: '<?php _e('表格'); ?>', className: 'fa fa-table'},
                '|',
                {name: 'unordered-list', action: EasyMDE.toggleUnorderedList, title: '<?php _e('普通列表'); ?>', className: 'fa fa-list-ul'},
                {name: 'ordered-list', action: EasyMDE.toggleOrderedList, title: '<?php _e('数字列表'); ?>', className: 'fa fa-list-ol'},
                {name: 'heading', action: EasyMDE.toggleHeadingBigger, title: '<?php _e('标题'); ?>', className: 'fa fa-header'},
                {name: 'horizontal-rule', action: EasyMDE.drawHorizontalRule, title: '<?php _e('分割线'); ?>', className: 'fa fa-minus'},
                {name: 'more', action: insertMore, title: '<?php _e('摘要分割线'); ?>', className: 'fa fa-scissors'},
                '|',
                {name: 'preview', action: EasyMDE.togglePreview, title: '<?php _e('预览'); ?>', className: 'fa fa-eye'},
                {name: 'side-by-side', action: EasyMDE.toggleSideBySide, title: '<?php _e('分屏预览'); ?>', className: 'fa fa-columns'},
                {name: 'fullscreen', action: EasyMDE.toggleFullScreen, title: '<?php _e('全屏'); ?>', className: 'fa fa-arrows-alt'},
                '|',
                {name: 'undo', action: EasyMDE.undo, title: '<?php _e('撤销'); ?>', className: 'fa fa-undo'},
                {name: 'redo', action: EasyMDE.redo, title: '<?php _e('重做'); ?>', className: 'fa fa-repeat'}
            ]
        });

        // 内容变化时同步 textarea 并触发自动保存链
        easyMDE.codemirror.on('change', function () {
            textarea.trigger('input');
        });

        const uploadBtn = $('<button type="button" id="btn-fullscreen-upload" class="btn btn-link">'
                + '<i class="i-upload"><?php _e('附件'); ?></i></button>')
                .prependTo('.submit .right')
                .click(function() {
                    $('a', $('.typecho-option-tabs li').not('.active')).trigger('click');
                    return false;
                });

        $('.typecho-option-tabs li').click(function () {
            uploadBtn.find('i').toggleClass('i-upload-active',
                $('#tab-files-btn', this).length > 0);
        });
    }

    if (isMarkdown) {
        initMarkdown();
    } else {
        const notice = $('<div class="message notice"><?php _e('这篇文章不是由Markdown语法创建的, 继续使用Markdown编辑它吗?'); ?> '
            + '<button class="btn btn-xs primary yes"><?php _e('是'); ?></button> '
            + '<button class="btn btn-xs no"><?php _e('否'); ?></button></div>')
            .hide().insertBefore(textarea).slideDown();

        $('.yes', notice).click(function () {
            notice.remove();
            $('<input type="hidden" name="markdown" value="1" />').appendTo('.submit');
            initMarkdown();
        });

        $('.no', notice).click(function () {
            notice.remove();
        });
    }
});
</script>
<?php endif; ?>
